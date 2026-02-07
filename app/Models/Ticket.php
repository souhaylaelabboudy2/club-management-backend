<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
     public $timestamps = false;

    protected $fillable = [
        'event_id',
        'person_id',
        'qr_code',
        'status',
        'auto_generated',
        'generated_by',
        'generated_at',
        'sent_at',
        'scanned_at',
        'scanned_by',
    ];

    protected $casts = [
        'auto_generated' => 'boolean',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'scanned_at' => 'datetime',
    ];
}
