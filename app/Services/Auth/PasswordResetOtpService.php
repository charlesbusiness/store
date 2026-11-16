<?php

namespace App\Services\Auth;

use App\Models\PasswordResetOtp;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpService
{
    public function request(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return;
        }

        $this->issue($user->email, fn (string $otp) => new PasswordResetOtpNotification($otp));
    }

    public function verify(string $email, string $otp): PasswordResetOtp
    {
        $record = $this->activeRecord($email);

        if ($record->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => 'Too many attempts. Request a new code.']);
        }

        $record->increment('attempts');

        if (! Hash::check($otp, $record->otp_hash)) {
            throw ValidationException::withMessages(['otp' => 'The code is invalid.']);
        }

        $record->update(['verified_at' => now()]);

        return $record;
    }

    public function reset(string $email, string $otp, string $password): void
    {
        $record = $this->activeRecord($email);

        if (! $record->verified() || ! Hash::check($otp, $record->otp_hash)) {
            throw ValidationException::withMessages(['otp' => 'Verify a valid code before resetting your password.']);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->firstOrFail();
        $user->forceFill([
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        $record->delete();
    }

    private function issue(string $email, callable $makeNotification): void
    {
        $email = strtolower(trim($email));

        PasswordResetOtp::query()->where('email', $email)->delete();

        $otp = (string) random_int(100000, 999999);
        Log::info('Otp Data===========================================', [$otp]);
        PasswordResetOtp::create([
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        Notification::route('mail', $email)->notify($makeNotification($otp));
    }

    private function activeRecord(string $email): PasswordResetOtp
    {
        $record = PasswordResetOtp::query()
            ->where('email', strtolower(trim($email)))
            ->latest('id')
            ->first();

        if (! $record || $record->expired()) {
            throw ValidationException::withMessages(['otp' => 'The code has expired. Request a new code.']);
        }

        return $record;
    }
}
