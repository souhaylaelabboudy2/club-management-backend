<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Digital_card extends Model
{
     public $timestamps = false;

    protected $fillable = [
        'person_id',
        'club_id',
        'member_number',
        'qr_code',
        'photo',
        'is_active',
        'issued_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'issued_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
