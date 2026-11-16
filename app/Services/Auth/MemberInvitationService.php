<?php

namespace App\Services\Auth;

use App\Models\MemberInvitationToken;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MemberInvitationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberInvitationService
{
    public function invite(User $user, Tenant $tenant): void
    {
        MemberInvitationToken::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(64);
        MemberInvitationToken::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            . '/accept-invite?token=' . urlencode($token);

        Notification::route('mail', $user->email)
            ->notify(new MemberInvitationNotification($url, $tenant->name));
    }

    public function accept(string $token, string $password): void
    {
        $invitation = MemberInvitationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->first();

        if (! $invitation || $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages(['token' => 'This invitation is invalid or has expired.']);
        }

        $user = User::query()->findOrFail($invitation->user_id);
        $user->forceFill([
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $invitation->update(['used_at' => now()]);
    }
}
