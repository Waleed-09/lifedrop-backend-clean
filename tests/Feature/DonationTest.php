<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_can_record_donation_and_fulfill_request(): void
    {
        $requester = User::factory()->create();
        $bloodRequest = BloodRequest::create([
            'requester_id' => $requester->id,
            'blood_group' => 'A+',
            'units' => 1,
            'hospital' => 'City Hospital',
            'latitude' => 34.15,
            'longitude' => 73.21,
            'urgency' => 'normal',
            'status' => 'open',
        ]);

        $donor = User::factory()->create([
            'role' => 'donor',
            'blood_group' => 'A+',
            'donation_count' => 0,
        ]);
        $token = $donor->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/donations', [
                'blood_request_id' => $bloodRequest->id,
                'units' => 1,
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['units' => 1, 'status' => 'completed']);

        $this->assertDatabaseHas('donations', [
            'donor_id' => $donor->id,
            'blood_request_id' => $bloodRequest->id,
        ]);

        $this->assertDatabaseHas('blood_requests', [
            'id' => $bloodRequest->id,
            'status' => 'fulfilled',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $donor->id,
            'donation_count' => 1,
        ]);

        $donor->refresh();
        $this->assertNotNull($donor->last_donated_at);
        $this->assertFalse($donor->is_eligible);
    }

    public function test_cooling_down_donor_cannot_record_new_donation(): void
    {
        $donor = User::factory()->create([
            'role' => 'donor',
            'last_donated_at' => now()->subDays(30), // In 90-day cooldown
        ]);
        $token = $donor->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/donations', [
                'units' => 1,
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => "Donor is currently in a 90-day recovery cooldown period ({$donor->days_until_eligible} days remaining)."]);
    }
}
