<?php

namespace OnrampLab\Transcription\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OnrampLab\Transcription\Facades\Transcription;

class ConfirmTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly string $externalId,
    ) {
        $this->tries = config('transcription.confirmation.tries');
        $this->onQueue(config('transcription.confirmation.queue'));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $transcript = Transcription::confirm($this->type, $this->externalId);

        if (! $transcript->isFinished()) {
            /** @var int $interval */
            $interval = config('transcription.confirmation.interval');

            $this->release($this->attempts() * $interval);
        }
    }
}
