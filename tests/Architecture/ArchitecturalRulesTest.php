<?php

declare(strict_types=1);

arch('policies depend only on models and the permission system')
    ->expect('App\Policies')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Support\Facades\DB',
        'App\Http',
    ]);

arch('models do not depend on http')
    ->expect('App\Models')
    ->not->toUse([
        'Illuminate\Http',
        'App\Http',
    ]);

arch('controllers do not bypass eloquent with the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('production code does not call env() directly')
    ->expect('App')
    ->not->toUse('env');

arch('superadmin checks live in a reviewed allowlist')
    ->expect('hasSuperAdminRole')
    ->toOnlyBeUsedIn([
        'App\Models\User',
        'App\Providers\AppServiceProvider',
        'App\Http\Middleware\HandleInertiaRequests',
        'App\Http\Middleware\EnsureSuperAdmin',
        'App\Http\Middleware\InitializeTenantContext',
        'App\Http\Controllers\DashboardController',
        'App\Http\Controllers\Admin\QueueHealthController',
        'App\Broadcasting\BroadcastChannelAuthorizer',
    ]);
