<?php

namespace DevFactory\AiSpam;

use DevFactory\AiSpam\Commands\ReleaseCommand;
use DevFactory\AiSpam\View\Fields;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AiSpamServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ai-spam')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_ai_spam_submissions_table')
            ->hasCommand(ReleaseCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(Honeypot::class, fn () => new Honeypot(config('ai-spam.honeypot')));
        $this->app->bind(AiSpam::class);
        $this->app->singleton(Classifier::class);
    }

    public function packageBooted(): void
    {
        Blade::component('ai-spam', Fields::class);
    }
}
