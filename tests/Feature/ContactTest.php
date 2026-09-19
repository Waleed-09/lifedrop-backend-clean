<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_submit_contact_form(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Jane Doe',
            'email' => 'janedoe@example.com',
            'subject' => 'Volunteering Inquiry',
            'message' => 'Hello, I would like to volunteer for blood drive events.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contact message received successfully!',
            ]);
    }
}
