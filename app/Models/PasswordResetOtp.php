<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PasswordResetOtp extends Model
{
    protected $fillable = ['email', 'otp_hash', 'expires_at', 'verified_at', 'attempts'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function verified(): bool
    {
        return $this->verified_at instanceof Carbon;
    }
}
