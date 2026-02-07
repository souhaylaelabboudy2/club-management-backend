<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
     protected $fillable = [
        'name',
        'code',
        'description',
        'mission',
        'logo',
        'cover_image',
        'category',
        'founding_year',
        'is_public',
        'total_members',
        'active_members',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'total_members' => 'integer',
        'active_members' => 'integer',
        'founding_year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
