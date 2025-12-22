<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
        'current_company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'smtp_password',
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
            'smtp_port' => 'integer',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies');
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasModulePermission(string $module, string $ability): bool
    {
        $ability = match ($ability) {
            'view', 'list', 'create', 'edit', 'delete', 'edit_completed', 'edit_salesperson' => $ability,
            default => 'view',
        };

        return $this->groups()
            ->whereHas('permissions', function ($q) use ($module, $ability) {
                $q->where('module', $module)->where("can_{$ability}", true);
            })
            ->exists();
    }

    public function canEditCompletedJobcards(): bool
    {
        return $this->hasModulePermission('jobcards', 'edit_completed');
    }

    public function canEditSalesperson(string $module): bool
    {
        return $this->hasModulePermission($module, 'edit_salesperson');
    }

    public function isAdministrator(): bool
    {
        return $this->groups()
            ->where('is_administrator', true)
            ->exists();
    }

    public function hasAccessToCompany(int $companyId): bool
    {
        // If user has no companies assigned, they have access to all companies
        if ($this->companies()->count() === 0) {
            return true;
        }
        
        return $this->companies()->where('company_id', $companyId)->exists();
    }

    public function getCurrentCompany(): ?Company
    {
        // If user has a current company set, check if they still have access to it
        if ($this->current_company_id) {
            $currentCompany = $this->currentCompany;
            if ($currentCompany && $this->hasAccessToCompany($currentCompany->id) && $currentCompany->is_active) {
                return $currentCompany;
            }
        }

        // If user has companies assigned, return the first active one
        if ($this->companies()->count() > 0) {
            $firstCompany = $this->companies()->where('companies.is_active', true)->first();
            if ($firstCompany) {
                return $firstCompany;
            }
        }

        // Otherwise, return the default company (only if user has access to all companies)
        if ($this->companies()->count() === 0) {
            return Company::getDefault();
        }

        // If user has restricted access but no valid companies, return null
        return null;
    }
}
