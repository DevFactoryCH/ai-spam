<?php

namespace DevFactory\AiSpam\Tests;

use DevFactory\AiSpam\AiSpamServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LivewireServiceProvider::class,
            AiSpamServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        (include __DIR__.'/../database/migrations/create_ai_spam_submissions_table.php.stub')->up();
    }
}
