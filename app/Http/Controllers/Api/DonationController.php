<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->donations()->latest('date')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'blood_request_id' => ['nullable', 'exists:blood_requests,id'],
            'blood_bank_id' => ['nullable', 'exists:users,id'],
            'units' => ['required', 'integer', 'min:1', 'max:5'],
            'date' => ['required', 'date'],
        ]);

        $donor = $request->user();

        $donation = DB::transaction(function () use ($data, $donor) {
            $donation = Donation::create([
                ...$data,
                'donor_id' => $donor->id,
                'status' => 'completed',
            ]);

            $donor->increment('donation_count');
            $donor->update(['last_donation_date' => $data['date']]);

            if (! empty($data['blood_bank_id'])) {
                Inventory::where('blood_bank_id', $data['blood_bank_id'])
                    ->where('blood_group', $donor->blood_group)
                    ->increment('units_available', $data['units']);
            }

            if (! empty($data['blood_request_id'])) {
                $donation->bloodRequest()->update(['status' => 'fulfilled']);
            }

            return $donation;
        });

        return response()->json($donation, 201);
    }
}
