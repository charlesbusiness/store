<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * One shared ledger behind a single login, with no self-service signup.
 *
 * A failed attempt reports against the email field on purpose: it avoids
 * telling an attacker which half of the credentials was wrong.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    public function test_a_valid_sign_in_reaches_the_reports(): void
    {
        $user = User::factory()->create(['email' => 'trader@example.com']);

        $this->post(route('login.store'), [
            'email' => 'trader@example.com',
            'password' => 'password',
        ])->assertRedirect(route('reports.index'));

        $this->assertAuthenticatedAs($user);

        // Already signed in, the login page is not worth showing.
        $this->get(route('login'))->assertRedirect(route('reports.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_failed_sign_in_is_reported_against_the_email_field(): void
    {
        User::factory()->create(['email' => 'trader@example.com']);

        // A wrong password and an unknown address are refused identically, so the
        // form never reveals which half of the credentials was wrong.
        foreach ([['trader@example.com', 'not-the-password'], ['nobody@example.com', 'password']] as [$email, $password]) {
            $this->post(route('login.store'), ['email' => $email, 'password' => $password])
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }
}
