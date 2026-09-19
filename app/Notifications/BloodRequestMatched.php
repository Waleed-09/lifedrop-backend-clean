<?php

namespace App\Notifications;

use App\Models\BloodRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BloodRequestMatched extends Notification
{
    use Queueable;

    public function __construct(public BloodRequest $bloodRequest)
    {
    }

    public function via(object $notifiable): array
    {
        // Add 'vonage' (SMS) and 'fcm' (push) channels once those services are configured.
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New blood request nearby',
            'blood_request_id' => $this->bloodRequest->id,
            'hospital' => $this->bloodRequest->hospital,
            'blood_group' => $this->bloodRequest->blood_group,
            'units' => $this->bloodRequest->units,
            'urgency' => $this->bloodRequest->urgency,
        ];
    }
}
