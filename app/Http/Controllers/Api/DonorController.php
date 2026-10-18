<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DonorController extends Controller
{
    /**
     * Search and list active donors with strict multi-criteria AND filtering and 90-day cooldown eligibility.
     * GET /donors?blood_group=B+&city=Abbottabad
     */
    public function index(Request $request)
    {
        if ($request->has('blood_group')) {
            $request->merge([
                'blood_group' => str_replace(' ', '+', trim($request->string('blood_group'))),
            ]);
        }

        $query = User::query()->where('role', 'donor');

        // Availability filter
        if (Schema::hasColumn('users', 'availability')) {
            $query->where('availability', true);
        } elseif (Schema::hasColumn('users', 'is_available')) {
            $query->where('is_available', true);
        }

        // Only return eligible donors (cooldown expired / never donated) unless explicitly requested
        if (! $request->boolean('include_ineligible')) {
            $query->eligible();
        }

        // Strict Blood Group filter (AND condition)
        if ($request->filled('blood_group') && strtolower($request->string('blood_group')) !== 'all') {
            $query->where('blood_group', $request->string('blood_group'));
        }

        // Strict Location filter (AND condition with nested OR across city, address, district)
        if ($request->filled('city') && strtolower(trim($request->string('city'))) !== 'all') {
            $city = trim($request->string('city'));
            $cleanCity = trim(explode(',', $city)[0]);

            $query->where(function ($q) use ($city, $cleanCity) {
                if (Schema::hasColumn('users', 'city')) {
                    $q->where('city', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('city', 'LIKE', '%' . $city . '%');
                }
                if (Schema::hasColumn('users', 'address')) {
                    $q->orWhere('address', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('address', 'LIKE', '%' . $city . '%');
                }
                if (Schema::hasColumn('users', 'district')) {
                    $q->orWhere('district', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('district', 'LIKE', '%' . $city . '%');
                }
            });
        }

        $donors = $query->latest()->get();

        return response()->json($donors);
    }

    /**
     * Get distinct active donor locations/cities available in database.
     * GET /donors/locations
     */
    public function locations()
    {
        $cities = User::where('role', 'donor')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->pluck('city')
            ->map(fn ($c) => trim(explode(',', $c)[0]))
            ->map(fn ($c) => ucwords(strtolower($c)))
            ->filter()
            ->unique()
            ->values();

        return response()->json($cities);
    }

    /**
     * Mark a donor as completed donation (triggers 90-day recovery cooldown).
     * POST /donors/{id}/mark-donated
     */
    public function markDonated(Request $request, $id)
    {
        $donor = User::where('role', 'donor')->findOrFail($id);

        $now = now();
        $donor->last_donated_at = $now;
        $donor->last_donation_date = $now->toDateString();
        $donor->donation_count = ((int) $donor->donation_count) + 1;
        $donor->save();

        return response()->json([
            'message' => 'Donor marked as donated successfully. 90-day cooldown period active.',
            'donor' => $donor,
            'is_eligible' => false,
            'days_until_eligible' => 90,
            'next_eligible_date' => $now->copy()->addDays(90)->toDateString(),
        ]);
    }

    /**
     * Toggle the authenticated donor's availability.
     */
    public function toggleAvailability(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'donor', 403, 'Only donors have availability status.');

        $user->update(['availability' => ! $user->availability]);

        return response()->json(['availability' => $user->availability]);
    }

    /**
     * Find nearby, eligible, blood-group-compatible donors.
     * GET /donors/nearby?blood_group=O+&lat=..&lng=..&radius_km=10
     * If lat/lng are omitted, falls back to multi-criteria search.
     */
    public function nearby(Request $request)
    {
        if ($request->has('blood_group')) {
            $request->merge([
                'blood_group' => str_replace(' ', '+', trim($request->string('blood_group'))),
            ]);
        }

        // If coordinates are not provided, fall back to strict criteria search
        if (! $request->filled('lat') || ! $request->filled('lng')) {
            return $this->index($request);
        }

        $request->validate([
            'blood_group' => ['required', 'in:O+,O-,A+,A-,B+,B-,AB+,AB-'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ]);

        $lat = $request->float('lat');
        $lng = $request->float('lng');
        $radiusKm = $request->float('radius_km', 10);
        $compatibleGroups = User::compatibleDonorGroups($request->string('blood_group'));

        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $query = User::query()
            ->donors()
            ->available()
            ->eligible()
            ->whereIn('blood_group', $compatibleGroups)
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat]);

        if ($request->filled('city') && strtolower(trim($request->string('city'))) !== 'all') {
            $city = trim($request->string('city'));
            $cleanCity = trim(explode(',', $city)[0]);

            $query->where(function ($q) use ($city, $cleanCity) {
                if (Schema::hasColumn('users', 'city')) {
                    $q->where('city', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('city', 'LIKE', '%' . $city . '%');
                }
                if (Schema::hasColumn('users', 'address')) {
                    $q->orWhere('address', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('address', 'LIKE', '%' . $city . '%');
                }
                if (Schema::hasColumn('users', 'district')) {
                    $q->orWhere('district', 'LIKE', '%' . $cleanCity . '%')
                      ->orWhere('district', 'LIKE', '%' . $city . '%');
                }
            });
        }

        $donors = $query->orderBy('distance_km')
            ->get()
            ->filter(fn ($donor) => $donor->distance_km <= $radiusKm)
            ->values();

        return response()->json($donors);
    }
}
