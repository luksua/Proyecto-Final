<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentidadExterna extends Model
{
    protected $table = 'identidades_externas';

    protected $fillable = ['user_id', 'issuer', 'subject'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
