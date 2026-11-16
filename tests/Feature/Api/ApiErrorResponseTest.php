<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_errors_do_not_expose_exception_details(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/purchases');

        $response->assertStatus(403)
            ->assertJsonStructure(['message'])
            ->assertJsonMissing(['trace'])
            ->assertJsonMissing(['exception'])
            ->assertJsonMissing(['file']);
    }
}
