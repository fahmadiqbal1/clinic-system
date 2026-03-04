<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

class UserTourTest extends TestCase
{

    public function test_new_user_has_tour_flag_false(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->assertEquals(false, $user->has_completed_tour);
    }

    public function test_user_can_complete_tour(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->post(route('user.complete-tour'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertTrue($user->fresh()->has_completed_tour);
    }

    public function test_guest_cannot_complete_tour(): void
    {
        $response = $this->post(route('user.complete-tour'));

        $response->assertRedirect('/login');
    }

    public function test_different_users_have_independent_tour_flags(): void
    {
        /** @var User|Authenticatable $user1 */
        $user1 = User::factory()->create();
        $user1->assignRole('Owner');

        /** @var User|Authenticatable $user2 */
        $user2 = User::factory()->create();
        $user2->assignRole('Doctor');

        // User 1 completes tour
        $this->actingAs($user1)->post(route('user.complete-tour'));

        // User 1 completed, User 2 hasn't
        $this->assertTrue($user1->fresh()->has_completed_tour);
        $this->assertFalse($user2->fresh()->has_completed_tour);

        // User 2 completes tour
        $this->actingAs($user2)->post(route('user.complete-tour'));
        $this->assertTrue($user2->fresh()->has_completed_tour);
    }
}
