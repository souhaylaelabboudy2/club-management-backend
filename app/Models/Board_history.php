<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Board_history extends Model
{
     protected $table = 'board_history';

    protected $fillable = [
        'club_id',
        'person_id',
        'position',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
