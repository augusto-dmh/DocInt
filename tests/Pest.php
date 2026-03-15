<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function tenantContextSessionKey(): string
{
    return config('tenancy.tenant_context.session_key', 'active_tenant_id');
}

function createSuperAdmin(App\Models\Tenant $tenant): App\Models\User
{
    $superAdmin = App\Models\User::factory()->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');
    setPermissionsTeamId(null);

    return $superAdmin;
}

function createTenantAdmin(App\Models\Tenant $tenant): App\Models\User
{
    $tenantAdmin = App\Models\User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $tenantAdmin->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    return $tenantAdmin;
}
