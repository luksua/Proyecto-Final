<?php

namespace App\Services;

use App\Integrations\Payments\ProveedorPago;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\PagoTransaccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PagoService
{
    public function __construct(private ProveedorPago $proveedor) {}

    public function autorizar(Matricula $matricula, User $actor): void
    {
        abort_unless($actor->rol === 'ADMIN' || ($actor->rol === 'ESTUDIANTE' && $actor->estudiante_id === $matricula->estudiante_id), 403, 'No tienes permisos para acceder a este pago');
    }

    public function crear(int $matriculaId, User $actor): Pago
    {
        return DB::transaction(function () use ($matriculaId, $actor) {
            $matricula = Matricula::lockForUpdate()->findOrFail($matriculaId);
            $this->autorizar($matricula, $actor);
            abort_unless($matricula->estado === 'ACTIVA', 409, 'La matrícula está cancelada');
            $existente = Pago::where('matricula_id', $matricula->id)->first();
            if ($existente) {
                return $existente;
            }
            $centavos = $matricula->curso->creditos * config('integrations.payments.precio_credito_centavos');
            abort_if($centavos <= 0 || $centavos > 999999999999, 503, 'La tarifa de demostración no está configurada correctamente');

            return Pago::create(['matricula_id' => $matricula->id, 'valor' => sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100),
                'moneda' => config('integrations.payments.moneda'), 'estado' => 'PENDIENTE', 'proveedor' => $this->proveedor->nombre()]);
        }, 3);
    }

    public function procesar(Pago $pago, User $actor, string $key, string $resultado, bool $reembolso = false): Pago
    {
        return DB::transaction(function () use ($pago, $actor, $key, $resultado, $reembolso) {
            // Same order as cancellation: enrolment before payment. Provider is LOCAL simulation only.
            $matricula = Matricula::lockForUpdate()->findOrFail($pago->matricula_id);
            $this->autorizar($matricula, $actor);
            if ($reembolso) {
                abort_unless($actor->rol === 'ADMIN', 403, 'Solo ADMIN puede reembolsar');
            }
            $actual = Pago::lockForUpdate()->findOrFail($pago->id);
            $accion = $reembolso ? 'REEMBOLSO' : 'PAGO';
            $hash = hash('sha256', json_encode([$actual->id, $accion, $resultado]));
            $previa = PagoTransaccion::where('actor_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($previa) {
                abort_unless(hash_equals($previa->solicitud_hash, $hash), 409, 'La clave de idempotencia ya se utilizó para otra solicitud');

                return $actual;
            }
            abort_unless($actual->proveedor === $this->proveedor->nombre(), 409, 'La orden pertenece a otro proveedor');
            if ($reembolso) {
                abort_unless($actual->estado === 'APROBADO', 409, 'Solo se puede reembolsar un pago aprobado');
            } else {
                abort_unless($matricula->estado === 'ACTIVA', 409, 'La matrícula está cancelada');
                abort_unless(in_array($actual->estado, ['PENDIENTE', 'RECHAZADO'], true), 409, 'Este pago ya fue aprobado o reembolsado');
            }
            // Real remote providers must reserve an operation, release the SQL lock, and reconcile
            // unknown results. They must not be substituted here without that workflow.
            $operacion = $actor->id.':'.$key;
            $res = $reembolso ? $this->proveedor->reembolsar($actual, $operacion) : $this->proveedor->procesar($actual, $operacion, $resultado);
            abort_unless(in_array($res->estado, $reembolso ? ['REEMBOLSADO'] : ['APROBADO', 'RECHAZADO'], true), 503, 'Respuesta del proveedor no válida');
            $transaccion = PagoTransaccion::create(['pago_id' => $actual->id, 'actor_id' => $actor->id, 'idempotency_key' => $key,
                'solicitud_hash' => $hash, 'accion' => $accion, 'estado' => $res->estado, 'referencia' => $res->referencia]);
            $actual->update(['estado' => $res->estado, 'referencia' => $res->referencia, 'procesado_at' => now()]);
            NotificacionService::registrar($matricula->estudiante_id, 'pago.'.strtolower($res->estado), $actual->id, 'transaccion:'.$transaccion->id);

            return $actual;
        }, 3);
    }
}
