<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_ppc_user_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'ppc']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_role_values_are_normalized_before_authorization(): void
    {
        $user = User::factory()->create(['role' => ' PPC ']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->assertSame('ppc', $user->fresh()->role);
    }
}
