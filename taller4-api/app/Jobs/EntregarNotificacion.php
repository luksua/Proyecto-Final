<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EntregarNotificacion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function __construct(public int $outboxId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $event = DB::table('notification_outbox')->where('id', $this->outboxId)->lockForUpdate()->first();
            if (! $event || $event->delivered_at) {
                return;
            }
            DB::table('notificaciones')->insertOrIgnore(['event_key' => $event->event_key, 'user_id' => $event->user_id,
                'tipo' => $event->tipo, 'resource_id' => $event->resource_id, 'created_at' => now()]);
            DB::table('notification_outbox')->where('id', $event->id)->update(['delivered_at' => now()]);
        }, 3);
    }
}
