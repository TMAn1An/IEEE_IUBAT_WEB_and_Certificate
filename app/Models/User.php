<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. Safe to include `role` here
     * because the actual protection boundary is the Form Request's
     * validated()-whitelisted input plus the UserPolicy check that gates
     * every admin-user route — not $fillable itself. There is no public
     * registration and no route ever passes raw request input straight to
     * create()/update().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /** @return HasMany<CertificateTemplate, $this> */
    public function createdTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class, 'created_by');
    }

    /** @return HasMany<CertificateBatch, $this> */
    public function createdBatches(): HasMany
    {
        return $this->hasMany(CertificateBatch::class, 'created_by');
    }

    /** @return HasMany<Certificate, $this> */
    public function createdCertificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'created_by');
    }
}
