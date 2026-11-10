<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoTransaccion extends Model
{
    protected $table = 'pago_transacciones';

    protected $guarded = ['id'];

    protected $hidden = ['idempotency_key', 'solicitud_hash'];

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }
}
