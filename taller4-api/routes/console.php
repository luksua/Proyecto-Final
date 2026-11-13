<?php

use App\Jobs\EntregarNotificacion;
use App\Models\IdentidadExterna;
use App\Models\Pago;
use App\Models\User;
use App\Services\MercadoPagoService;
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
Artisan::command('pagos:conciliar-mercadopago', function () {
    $service = app(MercadoPagoService::class);
    $pagos = Pago::where('proveedor', 'mercadopago')->whereNotNull('mp_external_reference')
        ->whereIn('estado', ['PENDIENTE', 'APROBADO'])->orderBy('updated_at')->limit(20)->get();
    $failures = 0;
    foreach ($pagos as $pago) {
        try {
            $service->sincronizar($pago);
            $this->info('Orden '.$pago->id.': estado consultado');
        } catch (Throwable $e) {
            $failures++;
            $this->warn('Orden '.$pago->id.': no se pudo conciliar; reintentar más tarde');
        }
    }

    return $failures ? 1 : 0;
})->purpose('Consulta pagos de Mercado Pago sin iniciar cobros ni confiar en la URL de retorno');

Schedule::command('pagos:conciliar-mercadopago')->everyFiveMinutes()->withoutOverlapping();
Artisan::command('mercado-pago:estado', function () {
    $cfg = config('integrations.mercadopago');
    $this->info('Proveedor seleccionado: '.config('integrations.payments.driver'));
    $this->info('Modo de prueba: '.($cfg['test_mode'] ? 'sí' : 'no'));
    foreach (['public_key', 'access_token', 'collector_id', 'webhook_secret', 'public_url'] as $key) {
        $this->line($key.': '.(filled($cfg[$key]) ? 'configurado' : 'FALTA CONFIGURAR'));
    }
    $this->line('Este diagnóstico no consulta Mercado Pago ni muestra secretos.');
})->purpose('Comprueba presencia de configuración de Mercado Pago sin mostrar credenciales');
Schedule::call(fn () => DB::table('identity_flows')->where('expires_at', '<', now()->subDay())->delete())->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
