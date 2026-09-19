<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBloodRequestRequest;
use App\Jobs\MatchDonorsJob;
use App\Models\BloodRequest;
use Illuminate\Http\Request;

class BloodRequestController extends Controller
{
    /**
     * Get all active emergency requests for all users and donors.
     */
    public function index(Request $request)
    {
        // Fetch open or matched requests, newest first
        $requests = BloodRequest::with('requester')
            ->whereIn('status', ['open', 'matched'])
            ->latest()
            ->get();

        return response()->json($requests, 200);
    }

    public function store(StoreBloodRequestRequest $request)
    {
        $bloodRequest = BloodRequest::create([
            ...$request->validated(),
            'requester_id' => $request->user()->id,
            'status' => 'open',
        ]);

        // Queued so the API responds instantly even for large donor pools.
        MatchDonorsJob::dispatch($bloodRequest);

        return response()->json($bloodRequest, 201);
    }

    public function show(BloodRequest $request_)
    {
        return response()->json($request_->load('matchedDonors', 'requester'));
    }

    public function update(Request $request, BloodRequest $request_)
    {
        $request->validate(['status' => ['required', 'in:open,matched,fulfilled,cancelled']]);
        $request_->update(['status' => $request->string('status')]);

        return response()->json($request_);
    }

    /**
     * A donor accepts a matched request.
     */
    public function accept(Request $request, BloodRequest $bloodRequest)
    {
        $donor = $request->user();
        abort_unless($donor->role === 'donor', 403, 'Only donors can accept requests.');

        $bloodRequest->matchedDonors()->syncWithoutDetaching([
            $donor->id => ['status' => 'accepted', 'responded_at' => now()],
        ]);

        $bloodRequest->update(['status' => 'matched']);

        // Contact details are safe to reveal now that both sides are matched.
        return response()->json([
            'request' => $bloodRequest,
            'donor_contact' => ['name' => $donor->name, 'phone' => $donor->phone],
            'requester_contact' => [
                'name' => $bloodRequest->requester->name,
                'phone' => $bloodRequest->requester->phone,
            ],
        ]);
    }
}