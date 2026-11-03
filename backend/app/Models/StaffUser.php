<?php

namespace App\Models;

use App\Notifications\StaffPasswordReset;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffUser extends Authenticatable
{
    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'two_factor_secret' => 'encrypted', 'two_factor_confirmed_at' => 'datetime', 'last_totp_step' => 'integer', 'auth_version' => 'integer'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function canAccess(string $permission): bool
    {
        return $this->active && in_array($permission, $this->role->permissions, true);
    }

    public function publicIdentity(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role->name, 'permissions' => $this->role->permissions];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffPasswordReset($token));
    }
}
