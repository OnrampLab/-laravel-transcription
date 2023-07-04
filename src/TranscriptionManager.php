<?php

namespace OnrampLab\Transcription;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OnrampLab\Transcription\Contracts\TranscriptionManager as TranscriptionManagerContract;
use OnrampLab\Transcription\Contracts\TranscriptionProvider;
use OnrampLab\Transcription\Enums\TranscriptionStatusEnum;
use OnrampLab\Transcription\Jobs\ConfirmTranscriptionJob;
use OnrampLab\Transcription\Models\Transcript;

class TranscriptionManager implements TranscriptionManagerContract
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The array of resolved transcription providers.
     */
    protected array $providers = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Add a transcription provider resolver.
     */
    public function addProvider(string $driverName, Closure $resolver): void
    {
        $this->providers[$driverName] = $resolver;
    }

    /**
     * Make transcription for audio file in specific language
     */
    public function make(string $audioUrl, string $languageCode, ?string $providerName = null): void
    {
        $type = Str::kebab(Str::camel($providerName ?: $this->getDefaultProvider()));
        $provider = $this->resolveProvider($providerName);
        $transcription = $provider->transcribe($audioUrl, $languageCode);

        $transcript = Transcript::create([
            'type' => $type,
            'external_id' => $transcription->id,
            'status' => $transcription->status->value,
            'audio_file_url' => $audioUrl,
            'language_code' => $languageCode,
        ]);

        ConfirmTranscriptionJob::dispatch($transcript)
            ->delay(now()->addSeconds(config('transcription.confirmation.interval')));
    }

    /**
     * Confirm asynchronous transcription process
     */
    public function confirm(Transcript $transcript): Transcript
    {
        $providerName = Str::snake(Str::camel($transcript->type));
        $provider = $this->resolveProvider($providerName);
        $transcription = $provider->fetch($transcript->external_id);

        if ($transcription->status === TranscriptionStatusEnum::COMPLETED) {
            $provider->parse($transcription, $transcript);
        }

        $transcript->status = $transcription->status->value;
        $transcript->save();

        return $transcript;
    }

    /**
     * Resolve a transcription provider.
     */
    protected function resolveProvider(?string $providerName): TranscriptionProvider
    {
        $config = $this->getProviderConfig($providerName);
        $name = $config['driver'];

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("No transcription provider for [{$name}].");
        }

        return call_user_func($this->providers[$name], $config);
    }

    /**
     * Get the transcription provider configuration.
     */
    protected function getProviderConfig(?string $providerName): array
    {
        $name = $providerName ?: $this->getDefaultProvider();
        $config = $this->app['config']["transcription.providers.{$name}"] ?? null;

        if (is_null($config)) {
            throw new InvalidArgumentException("The [{$name}] transcription provider has not been configured.");
        }

        return $config;
    }

    /**
     * Get the name of default transcription provider.
     */
    protected function getDefaultProvider(): string
    {
        return $this->app['config']['transcription.default'] ?? '';
    }
}
