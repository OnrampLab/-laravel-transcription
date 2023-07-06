<?php

namespace OnrampLab\Transcription;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\TranscriptionManager;
use OnrampLab\Transcription\TranscriptionProviders\AwsTranscribeTranscriptionProvider;

class TranscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/transcription.php', 'transcription');

        $this->registerTranscriptionManager();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'transcription-migrations');

        $this->publishes([
            __DIR__ . '/../config/transcription.php' => config_path('transcription.php'),
        ], 'transcription-config');

        $this->registerTranscriptionCallbackRoute();
    }

    protected function registerTranscriptionManager(): void
    {
        $this->app->singleton(TranscriptionManagerContract::class, function ($app) {
            $this->registerTranscriptionProviders(new TranscriptionManager($app));
        });
    }

    protected function registerTranscriptionProviders(TranscriptionManager $manager): void
    {
        $this->registerAwsTranscribeTranscriptionProvider($manager);
    }

    protected function registerAwsTranscribeTranscriptionProvider(TranscriptionManager $manager): void
    {
        $manager->addProvider('aws_transcribe', fn (array $config) => new AwsTranscribeTranscriptionProvider($config));
    }

    protected function registerTranscriptionCallbackRoute(): void
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/callback.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('transcription.callback.prefix'),
            'middleware' => config('transcription.callback.middleware'),
        ];
    }
}
