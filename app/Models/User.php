<?php

namespace App\Models;

use App\Support\DashboardQuickActionCatalog;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Cached result for whether group_permissions has can_approve (avoids SQL errors before migrations run).
     *
     * @var bool|null null = unresolved
     */
    private static ?bool $groupPermissionsHasCanApproveColumn = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'hourly_rate',
        'current_company_id',
        'customer_id',
        'approval_status',
        'approved_at',
        'approved_by',
        'must_reset_password',
        'dashboard_quick_actions',
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
            'hourly_rate' => 'decimal:2',
            'smtp_password' => 'encrypted',
            'approved_at' => 'datetime',
            'must_reset_password' => 'boolean',
            'dashboard_quick_actions' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getResolvedDashboardQuickActions(): array
    {
        return DashboardQuickActionCatalog::resolve($this->dashboard_quick_actions);
    }

    public function isDashboardQuickActionEnabled(string $key): bool
    {
        return $this->getResolvedDashboardQuickActions()[$key] ?? true;
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function assignedJobcards(): HasMany
    {
        return $this->hasMany(Jobcard::class, 'assigned_to_user_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeExcludeClientUsers(Builder $query): Builder
    {
        return $query->where(function (Builder $userQuery) {
            $userQuery->whereNull('users.user_type')
                ->orWhere('users.user_type', '!=', 'client');
        });
    }

    public function scopeStaffSelectableForCompany(Builder $query, int $companyId, bool $includeUnassigned = true): Builder
    {
        return $query->excludeClientUsers()
            ->where(function (Builder $companyQuery) use ($companyId, $includeUnassigned) {
                $companyQuery->whereHas('companies', function (Builder $companyMembershipQuery) use ($companyId) {
                    $companyMembershipQuery->where('company_id', $companyId);
                });

                if ($includeUnassigned) {
                    $companyQuery->orWhereDoesntHave('companies');
                }
            });
    }

    public function customerUpdateRequests(): HasMany
    {
        return $this->hasMany(CustomerUpdateRequest::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function hasModulePermission(string $module, string $ability): bool
    {
        $ability = match ($ability) {
            'view', 'list', 'create', 'edit', 'delete', 'edit_completed', 'edit_salesperson', 'approve' => $ability,
            default => 'view',
        };

        if ($ability === 'approve') {
            if (self::$groupPermissionsHasCanApproveColumn === null) {
                try {
                    self::$groupPermissionsHasCanApproveColumn = Schema::hasColumn('group_permissions', 'can_approve');
                } catch (\Throwable) {
                    self::$groupPermissionsHasCanApproveColumn = false;
                }
            }
            if (! self::$groupPermissionsHasCanApproveColumn) {
                return false;
            }
        }

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

    /**
     * Payment methods (cash, card, eft) this user may record, derived from group flags (union).
     * Administrators and users with no groups get all methods.
     */
    public function getAllowedPaymentMethods(): array
    {
        if ($this->isAdministrator()) {
            return ['cash', 'card', 'eft'];
        }

        $groupIds = $this->groups()->pluck('groups.id');
        if ($groupIds->isEmpty()) {
            return ['cash', 'card', 'eft'];
        }

        $allowed = ['cash' => false, 'card' => false, 'eft' => false];
        foreach (Group::query()->whereIn('id', $groupIds)->get([
            'payment_method_cash',
            'payment_method_card',
            'payment_method_eft',
        ]) as $group) {
            if ($group->payment_method_cash) {
                $allowed['cash'] = true;
            }
            if ($group->payment_method_card) {
                $allowed['card'] = true;
            }
            if ($group->payment_method_eft) {
                $allowed['eft'] = true;
            }
        }

        return array_keys(array_filter($allowed));
    }

    /**
     * @return array{cash: bool, card: bool, eft: bool}
     */
    public function getAllowedPaymentMethodsMap(): array
    {
        $methods = $this->getAllowedPaymentMethods();

        return [
            'cash' => in_array('cash', $methods, true),
            'card' => in_array('card', $methods, true),
            'eft' => in_array('eft', $methods, true),
        ];
    }

    public function canRecordPaymentMethod(string $method): bool
    {
        if (! in_array($method, ['cash', 'card', 'eft'], true)) {
            return true;
        }

        return in_array($method, $this->getAllowedPaymentMethods(), true);
    }

    public function isLimitedUser(): bool
    {
        return $this->user_type === 'limited';
    }

    public function isStandardUser(): bool
    {
        return $this->user_type === 'standard' || $this->user_type === null;
    }

    public function isInfoUser(): bool
    {
        return $this->user_type === 'info';
    }

    public function isClientUser(): bool
    {
        return $this->user_type === 'client';
    }

    public function isClientApproved(): bool
    {
        if (! $this->isClientUser()) {
            return true;
        }

        return $this->approval_status === 'approved';
    }

    public function isClientDeactivated(): bool
    {
        return $this->isClientUser() && $this->approval_status === 'deactivated';
    }

    /**
     * @return list<int>
     */
    private function getClientAccessibleCompanyIds(): array
    {
        $companyIds = $this->companies()
            ->pluck('companies.id')
            ->map(fn ($companyId) => (int) $companyId)
            ->all();

        if ($this->current_company_id) {
            $companyIds[] = (int) $this->current_company_id;
        }

        if ($this->customer?->company_id) {
            $companyIds[] = (int) $this->customer->company_id;
        }

        return array_values(array_unique(array_filter($companyIds, fn ($companyId) => $companyId > 0)));
    }

    public function hasAccessToCompany(int $companyId): bool
    {
        if ($this->isClientUser()) {
            return in_array($companyId, $this->getClientAccessibleCompanyIds(), true);
        }

        // If user has no companies assigned, they have access to all companies
        if ($this->companies()->count() === 0) {
            return true;
        }

        return $this->companies()->where('company_id', $companyId)->exists();
    }

    public function getCurrentCompany(): ?Company
    {
        if ($this->isClientUser()) {
            if ($this->current_company_id) {
                $currentCompany = $this->currentCompany;
                if ($currentCompany && $this->hasAccessToCompany($currentCompany->id) && $currentCompany->is_active) {
                    return $currentCompany;
                }
            }

            $clientCompanyIds = $this->getClientAccessibleCompanyIds();
            if ($clientCompanyIds === []) {
                return null;
            }

            return Company::query()
                ->whereIn('id', $clientCompanyIds)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

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
