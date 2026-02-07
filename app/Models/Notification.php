<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
     public $timestamps = false;

    protected $fillable = [
        'person_id',
        'type',
        'title',
        'message',
        'dashboard_link',
        'data',
        'read',
        'email_sent',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'email_sent' => 'boolean',
        'created_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}
