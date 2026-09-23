<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;

trait SafeBroadcasts
{
    /**
     * Dispatch a broadcast event without letting transport errors
     * break the underlying business transaction.
     */
    protected function safeBroadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            /** @var LogManager|LoggerInterface $log */
            $log = app(LoggerInterface::class);
            $log->warning('Broadcast gagal: '.$e->getMessage());
        }
    }
}
