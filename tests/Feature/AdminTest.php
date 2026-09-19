<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BloodRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_overview(): void
    {
        $user = User::factory()->create(['role' => 'donor']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/overview');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_overview_statistics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        User::factory()->create(['role' => 'donor', 'blood_group' => 'B+']);
        User::factory()->create(['role' => 'recipient']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_users',
                'total_donors',
                'total_patients',
                'active_donors',
                'total_requests',
                'pending_requests',
                'fulfilled_requests',
                'emergency_requests',
                'blood_group_stats',
                'recent_activities',
            ]);
    }

    public function test_admin_can_search_users_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        User::factory()->create(['name' => 'Specific Test User Name', 'email' => 'spectest@example.com']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/users?search=spectest&page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure(['total', 'current_page', 'last_page', 'per_page', 'data'])
            ->assertJson(['total' => 1]);
    }
}
