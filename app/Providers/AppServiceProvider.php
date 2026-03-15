<?php

namespace App\Providers;

use App\Services\Processing\ClassificationProvider;
use App\Services\Processing\OcrProvider;
use App\Services\Processing\OpenAiClassificationProvider;
use App\Services\Processing\OpenAiOcrProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Stancl\Tenancy\UUIDGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UniqueIdentifierGenerator::class, UUIDGenerator::class);
        $this->app->bind(OcrProvider::class, function ($app) {
            $provider = (string) config('processing.ocr_provider', 'openai');

            return match ($provider) {
                'openai' => $app->make(OpenAiOcrProvider::class),
                default => throw new InvalidArgumentException("Unsupported OCR provider [{$provider}]."),
            };
        });
        $this->app->bind(ClassificationProvider::class, function ($app) {
            $provider = (string) config('processing.classification_provider', 'openai');

            return match ($provider) {
                'openai' => $app->make(OpenAiClassificationProvider::class),
                default => throw new InvalidArgumentException("Unsupported classification provider [{$provider}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::before(function ($user) {
            return method_exists($user, 'hasSuperAdminRole') && $user->hasSuperAdminRole() ? true : null;
        });
    }
}
