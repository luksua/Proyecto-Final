<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityFlow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['state_hash', 'code_hash', 'nonce', 'challenge'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
