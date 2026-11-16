<?php

namespace Tests\Feature\Api;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_code_can_be_requested_and_used(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'old-password']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $record = PasswordResetOtp::firstOrFail();
        $otp = '123456';
        $record->update(['otp_hash' => Hash::make($otp)]);

        $this->postJson('/api/auth/verify-reset-otp', ['email' => $user->email, 'otp' => $otp])->assertOk();
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk();
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_unknown_email_returns_generic_response(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'If that email exists, a reset code has been sent.']);

        Notification::assertNothingSent();
    }
}
