<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    
    public $timestamps = false;

    protected $fillable = [
        'club_id',
        'created_by',
        'title',
        'content',
        'requires_approval',
        'status',
        'approved_by',
        'published_at',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
