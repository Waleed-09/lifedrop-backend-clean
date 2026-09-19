<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_can_toggle_availability(): void
    {
        $donor = User::factory()->create([
            'role' => 'donor',
            'availability' => false,
        ]);
        $token = $donor->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/donors/me/availability');

        $response->assertStatus(200)
            ->assertJson(['availability' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $donor->id,
            'availability' => true,
        ]);
    }

    public function test_can_search_nearby_compatible_donors(): void
    {
        // Eligible donor within 2km
        User::factory()->create([
            'name' => 'Nearby Donor',
            'role' => 'donor',
            'blood_group' => 'O-',
            'availability' => true,
            'latitude' => 34.1500,
            'longitude' => 73.2100,
            'last_donation_date' => null,
        ]);

        // Far away donor (>50km)
        User::factory()->create([
            'name' => 'Far Donor',
            'role' => 'donor',
            'blood_group' => 'O-',
            'availability' => true,
            'latitude' => 35.0000,
            'longitude' => 74.0000,
            'last_donation_date' => null,
        ]);

        $requesterUser = User::factory()->create(['role' => 'recipient', 'availability' => false]);
        $token = $requesterUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/donors/nearby?blood_group=' . urlencode('A+') . '&lat=34.1510&lng=73.2110&radius_km=10');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Nearby Donor']);
    }
}
