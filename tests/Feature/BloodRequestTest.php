<?php

namespace Tests\Feature;

use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BloodRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_active_blood_requests(): void
    {
        $requester = User::factory()->create();
        BloodRequest::create([
            'requester_id' => $requester->id,
            'blood_group' => 'A+',
            'units' => 2,
            'hospital' => 'City Hospital',
            'latitude' => 34.15,
            'longitude' => 73.21,
            'urgency' => 'urgent',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/requests');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['hospital' => 'City Hospital', 'blood_group' => 'A+']);
    }

    public function test_authenticated_user_can_create_blood_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create a nearby available donor
        User::factory()->create([
            'role' => 'donor',
            'blood_group' => 'O+',
            'availability' => true,
            'latitude' => 34.15,
            'longitude' => 73.21,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/requests', [
                'blood_group' => 'O+',
                'units' => 1,
                'hospital' => 'Emergency Center',
                'latitude' => 34.15,
                'longitude' => 73.21,
                'urgency' => 'critical',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'blood_group' => 'O+',
                'hospital' => 'Emergency Center',
                'status' => 'open',
            ]);

        $this->assertDatabaseHas('blood_requests', [
            'hospital' => 'Emergency Center',
            'status' => 'open',
        ]);
    }

    public function test_donor_can_accept_blood_request(): void
    {
        $requester = User::factory()->create(['name' => 'Requester Name', 'phone' => '123456789']);
        $donor = User::factory()->create(['role' => 'donor', 'name' => 'Donor Name', 'phone' => '987654321']);

        $requestItem = BloodRequest::create([
            'requester_id' => $requester->id,
            'blood_group' => 'B+',
            'units' => 1,
            'hospital' => 'General Hospital',
            'latitude' => 34.15,
            'longitude' => 73.21,
            'urgency' => 'normal',
            'status' => 'open',
        ]);

        $token = $donor->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/requests/{$requestItem->id}/accept");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'request',
                'donor_contact' => ['name', 'phone'],
                'requester_contact' => ['name', 'phone'],
            ]);

        $this->assertDatabaseHas('blood_requests', [
            'id' => $requestItem->id,
            'status' => 'matched',
        ]);
    }
}
