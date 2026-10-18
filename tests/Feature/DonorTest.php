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

    public function test_strict_multi_criteria_search_returns_only_matching_blood_and_location(): void
    {
        // Donor 1: B+ in Abbottabad
        User::factory()->create([
            'name' => 'Abbottabad B+ Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Abbottabad',
            'address' => 'Main Mansehra Road, Abbottabad',
            'availability' => true,
            'is_available' => true,
        ]);

        // Donor 2: B+ in Buner (Totalai)
        User::factory()->create([
            'name' => 'Buner B+ Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Buner',
            'address' => 'Totalai, Tehsil Mandanr, Buner',
            'availability' => true,
            'is_available' => true,
        ]);

        // Donor 3: A+ in Abbottabad
        User::factory()->create([
            'name' => 'Abbottabad A+ Donor',
            'role' => 'donor',
            'blood_group' => 'A+',
            'city' => 'Abbottabad',
            'address' => 'Supply, Abbottabad',
            'availability' => true,
            'is_available' => true,
        ]);

        // Donor 4: B+ in Abbottabad but unavailable
        User::factory()->create([
            'name' => 'Unavailable Abbottabad B+ Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Abbottabad',
            'availability' => false,
            'is_available' => false,
        ]);

        // Search: blood_group = B+ AND city = Abbottabad
        $response = $this->getJson('/api/v1/donors?blood_group=' . urlencode('B+') . '&city=Abbottabad');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Abbottabad B+ Donor'])
            ->assertJsonMissing(['name' => 'Buner B+ Donor'])
            ->assertJsonMissing(['name' => 'Abbottabad A+ Donor'])
            ->assertJsonMissing(['name' => 'Unavailable Abbottabad B+ Donor']);

        // Same test on /donors/nearby endpoint without coordinates
        $nearbyResponse = $this->getJson('/api/v1/donors/nearby?blood_group=' . urlencode('B+') . '&city=Abbottabad');
        $nearbyResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Abbottabad B+ Donor']);
    }

    public function test_search_returns_empty_when_no_donor_matches_location(): void
    {
        User::factory()->create([
            'name' => 'Buner B+ Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Buner',
            'address' => 'Nawagai, Tehsil Mandanr, Buner',
            'availability' => true,
            'is_available' => true,
        ]);

        // Search for B+ in Karachi (should return empty array, NOT Buner donor)
        $response = $this->getJson('/api/v1/donors?blood_group=' . urlencode('B+') . '&city=Karachi');

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_donor_cooldown_eligibility_logic(): void
    {
        // Eligible donor (never donated)
        $donorNever = User::factory()->create([
            'role' => 'donor',
            'last_donated_at' => null,
            'last_donation_date' => null,
        ]);
        $this->assertTrue($donorNever->is_eligible);
        $this->assertEquals(0, $donorNever->days_until_eligible);

        // Eligible donor (donated 95 days ago)
        $donorOld = User::factory()->create([
            'role' => 'donor',
            'last_donated_at' => now()->subDays(95),
        ]);
        $this->assertTrue($donorOld->is_eligible);
        $this->assertEquals(0, $donorOld->days_until_eligible);

        // Ineligible donor in cooldown (donated 10 days ago)
        $donorCooling = User::factory()->create([
            'role' => 'donor',
            'last_donated_at' => now()->subDays(10),
        ]);
        $this->assertFalse($donorCooling->is_eligible);
        $this->assertGreaterThan(70, $donorCooling->days_until_eligible);
        $this->assertLessThanOrEqual(80, $donorCooling->days_until_eligible);
    }

    public function test_ineligible_donor_in_cooldown_is_excluded_from_search(): void
    {
        // Eligible B+ in Abbottabad
        User::factory()->create([
            'name' => 'Eligible Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Abbottabad',
            'availability' => true,
            'last_donated_at' => now()->subDays(100),
        ]);

        // Cooling down B+ in Abbottabad (donated 20 days ago)
        User::factory()->create([
            'name' => 'Cooldown Donor',
            'role' => 'donor',
            'blood_group' => 'B+',
            'city' => 'Abbottabad',
            'availability' => true,
            'last_donated_at' => now()->subDays(20),
        ]);

        $response = $this->getJson('/api/v1/donors?blood_group=' . urlencode('B+') . '&city=Abbottabad');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Eligible Donor'])
            ->assertJsonMissing(['name' => 'Cooldown Donor']);
    }

    public function test_can_mark_donor_as_donated(): void
    {
        $donor = User::factory()->create([
            'role' => 'donor',
            'last_donated_at' => null,
            'donation_count' => 0,
        ]);

        $response = $this->postJson('/api/v1/donors/' . $donor->id . '/mark-donated');

        $response->assertStatus(200)
            ->assertJson([
                'is_eligible' => false,
                'days_until_eligible' => 90,
            ]);

        $donor->refresh();
        $this->assertNotNull($donor->last_donated_at);
        $this->assertEquals(1, $donor->donation_count);
        $this->assertFalse($donor->is_eligible);
    }

    public function test_can_fetch_distinct_donor_locations(): void
    {
        User::factory()->create(['role' => 'donor', 'city' => 'Abbottabad']);
        User::factory()->create(['role' => 'donor', 'city' => 'Buner']);
        User::factory()->create(['role' => 'donor', 'city' => 'Peshawar']);

        $response = $this->getJson('/api/v1/donors/locations');

        $response->assertStatus(200)
            ->assertJson(['Abbottabad', 'Buner', 'Peshawar']);
    }
}
