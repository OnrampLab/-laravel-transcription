<?php

namespace OnrampLab\Transcription;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OnrampLab\Transcription\AudioTranscribers\AwsTranscribeAudioTranscriber;
use OnrampLab\Transcription\PiiEntityDetectors\AwsComprehendPiiEntityDetector;
use OnrampLab\Transcription\Providers\EventServiceProvider;
use OnrampLab\Transcription\TranscriptionManager;

class TranscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/transcription.php', 'transcription');

        $this->registerTranscriptionManager();
        $this->registerProviders();
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
        $this->app->singleton('transcription', function ($app) {
            return tap(new TranscriptionManager($app), function ($manager) {
                $this->registerAudioTranscribers($manager);
                $this->registerPiiEntityDetectors($manager);
            });
        });
    }

    protected function registerAudioTranscribers(TranscriptionManager $manager): void
    {
        $this->registerAwsTranscribeAudioTranscriber($manager);
    }

    protected function registerAwsTranscribeAudioTranscriber(TranscriptionManager $manager): void
    {
        $manager->addTranscriber('aws_transcribe', fn (array $config) => new AwsTranscribeAudioTranscriber($config));
    }

    protected function registerPiiEntityDetectors(TranscriptionManager $manager): void
    {
        $this->registerAwsComprehendPiiEntityDetector($manager);
    }

    protected function registerAwsComprehendPiiEntityDetector(TranscriptionManager $manager): void
    {
        $manager->addDetector('aws_comprehend', fn (array $config) => new AwsComprehendPiiEntityDetector($config));
    }

    protected function registerProviders(): void
    {
        $this->app->register(EventServiceProvider::class);
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
