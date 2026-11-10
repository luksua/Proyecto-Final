<?php

namespace App\Observers;

use App\Models\Curso;
use App\Support\CursoCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model);
    }

    private function record(string $event, Model $model): void
    {
        if ($model instanceof Curso) {
            CursoCache::invalidar();
        }
        $request = app()->bound('request') ? request() : null;
        $actor = $request?->user();
        $context = ['action' => $model->getTable().'.'.$event, 'resource' => $model->getTable(),
            'resource_id' => $model->getKey(), 'actor_id' => $actor?->id,
            'correlation_id' => $request?->attributes->get('correlation_id')];
        // Explicit allowlist. Never store request bodies, names, passwords, tokens or card data.
        $properties = array_intersect_key($event === 'updated' ? $model->getChanges() : $model->getAttributes(),
            array_flip(['estado', 'periodo', 'cupo_maximo', 'cupo_disponible', 'creditos']));
        DB::table('audit_events')->insert([...$context, 'properties' => json_encode($properties), 'created_at' => now()]);
        Log::channel('structured')->info('domain_event', $context);
    }
}
