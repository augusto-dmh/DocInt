<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->setTestingEnvironment();

        /** @var Application $application */
        $application = parent::createApplication();

        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'null',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);

        app('cache')->setDefaultDriver('array');
        Cache::flush();
        Queue::fake();
    }

    protected function setTestingEnvironment(): void
    {
        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'FILESYSTEM_DISK' => 's3',
            'MAIL_MAILER' => 'array',
            'PROCESSING_QUEUE_CONNECTION' => 'sync',
            'PROCESSING_OCR_PROVIDER' => 'openai',
            'PROCESSING_CLASSIFICATION_PROVIDER' => 'openai',
            'PROCESSING_RETRY_ATTEMPTS' => '3',
            'PROCESSING_RETRY_BACKOFF' => '5,15,45',
            'PROCESSING_SCAN_WAIT_DELAY_SECONDS' => '5',
            'PROCESSING_PROVIDER_DEGRADED_REQUEUE_DELAY_SECONDS' => '30',
            'PROCESSING_PROVIDER_CIRCUIT_FAILURE_THRESHOLD' => '3',
            'PROCESSING_PROVIDER_CIRCUIT_COOLDOWN_SECONDS' => '60',
            'PROCESSING_OPENAI_MODEL' => 'gpt-4o-mini',
            'PROCESSING_OPENAI_OCR_MODEL' => 'gpt-4o-mini',
            'PROCESSING_OPENAI_TIMEOUT' => '30',
            'OPENAI_API_KEY' => 'test-openai-key',
            'OPENAI_BASE_URL' => 'https://api.openai.com/v1',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];

        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
