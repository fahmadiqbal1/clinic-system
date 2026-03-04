<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{

    public function test_guest_cannot_access_owner_dashboard(): void
    {
        $response = $this->get('/owner/dashboard');
        // Auth middleware redirects unauthenticated users to login (302)
        $response->assertRedirect('/login');
    }

    public function test_owner_can_access_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/owner/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('owner.dashboard');
    }

    public function test_doctor_cannot_access_owner_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Doctor');

        $response = $this->actingAs($user)->get('/owner/dashboard');
        $response->assertStatus(403);
    }

    public function test_receptionist_cannot_access_owner_dashboard(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Receptionist');

        $response = $this->actingAs($user)->get('/owner/dashboard');
        $response->assertStatus(403);
    }
}
