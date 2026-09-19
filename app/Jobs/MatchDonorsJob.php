<?php

namespace App\Jobs;

use App\Models\BloodRequest;
use App\Models\User;
use App\Notifications\BloodRequestMatched;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchDonorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public BloodRequest $bloodRequest)
    {
    }

    public function handle(): void
    {
        // Critical requests search wider and faster.
        $radiusKm = match ($this->bloodRequest->urgency) {
            'critical' => 25,
            'urgent' => 15,
            default => 10,
        };

        $compatibleGroups = User::compatibleDonorGroups($this->bloodRequest->blood_group);

        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';
        $lat = $this->bloodRequest->latitude;
        $lng = $this->bloodRequest->longitude;

        $donors = User::query()
            ->donors()
            ->available()
            ->eligible()
            ->whereIn('blood_group', $compatibleGroups)
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->limit(50)
            ->get()
            ->filter(fn ($donor) => $donor->distance_km <= $radiusKm)
            ->values();

        foreach ($donors as $donor) {
            $this->bloodRequest->matchedDonors()->syncWithoutDetaching([
                $donor->id => ['status' => 'notified'],
            ]);

            $donor->notify(new BloodRequestMatched($this->bloodRequest));
        }
    }
}
