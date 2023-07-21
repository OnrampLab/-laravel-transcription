<?php

namespace OnrampLab\Transcription\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \OnrampLab\Transcription\Models\Transcript make(string $audioUrl, string $languageCode, ?bool $shouldRedact = false, ?string $transcriberName = null)
 * @method static void addTranscriber(string $driverName, Closure $resolver)
 * @method static void addDetector(string $driverName, Closure $resolver)
 *
 * @see \OnrampLab\Transcription\TranscriptionManager
 */
class Transcription extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'transcription';
    }
}
