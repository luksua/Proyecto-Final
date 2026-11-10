<?php

use App\Jobs\EntregarNotificacion;
use App\Models\IdentidadExterna;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('notificaciones:despachar', function () {
    $ids = DB::table('notification_outbox')->whereNull('delivered_at')
        ->where(fn ($q) => $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<', now()->subMinutes(5)))
        ->orderBy('id')->limit(100)->pluck('id');
    foreach ($ids as $id) {
        Bus::dispatch(new EntregarNotificacion($id));
        DB::table('notification_outbox')->where('id', $id)->update(['dispatched_at' => now()]);
    }
    $this->info('Notificaciones enviadas a la cola: '.$ids->count());
})->purpose('Despacha el outbox de notificaciones sin duplicar efectos');

Artisan::command('identidad:vincular {usuario} {subject} {--issuer=}', function () {
    $user = User::findOrFail($this->argument('usuario'));
    $issuer = $this->option('issuer') ?: config('integrations.identity.issuer');
    if (! $issuer || strlen($issuer) > 255 || strlen($this->argument('subject')) > 255) {
        $this->error('Configura issuer y subject válidos');

        return 1;
    }
    $existing = IdentidadExterna::where('issuer', $issuer)->where('subject', $this->argument('subject'))->first();
    if ($existing && $existing->user_id !== $user->id) {
        $this->error('La identidad ya pertenece a otra cuenta');

        return 1;
    }
    IdentidadExterna::firstOrCreate(['issuer' => $issuer, 'subject' => $this->argument('subject')], ['user_id' => $user->id]);
    $this->info('Vínculo registrado. El rol sigue siendo el de la cuenta local.');
})->purpose('Aprovisiona un vínculo OIDC desde una consola administrativa');

Schedule::command('notificaciones:despachar')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => DB::table('identity_flows')->where('expires_at', '<', now()->subDay())->delete())->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
