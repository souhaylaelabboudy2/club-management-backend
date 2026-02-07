<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Club_member extends Model
{
    protected $fillable = [
        'person_id',
        'club_id',
        'role',
        'status',
        'position',
        'joined_at',
        'left_at',
        'leave_reason',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship to Person
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    // Relationship to Club
    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
