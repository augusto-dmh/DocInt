<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasSuperAdminRole(): bool
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (
            ! is_array($tableNames)
            || ! isset($tableNames['model_has_roles'], $tableNames['roles'])
            || ! is_array($columnNames)
            || ! isset($columnNames['model_morph_key'])
        ) {
            return $this->hasRole('super-admin');
        }

        $rolePivotKey = (string) ($columnNames['role_pivot_key'] ?? 'role_id');
        $modelMorphKey = (string) $columnNames['model_morph_key'];
        $modelHasRolesTable = (string) $tableNames['model_has_roles'];
        $rolesTable = (string) $tableNames['roles'];

        return DB::table($modelHasRolesTable)
            ->join($rolesTable, $rolesTable.'.id', '=', $modelHasRolesTable.'.'.$rolePivotKey)
            ->where($modelHasRolesTable.'.model_type', $this::class)
            ->where($modelHasRolesTable.'.'.$modelMorphKey, $this->getKey())
            ->where($rolesTable.'.name', 'super-admin')
            ->exists();
    }
}
