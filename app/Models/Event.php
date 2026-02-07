<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'event';

    protected $fillable = [
        'club_id',
        'created_by',
        'validated_by',
        'title',
        'description',
        'category',
        'event_date',
        'registration_deadline',
        'location',
        'capacity',
        'registered_count',
        'attendees_count',
        'status',
        'is_public',
        'banner_image',
        'requires_ticket',
        'tickets_for_all',
        'price',
        'recap_description',
        'recap_images',
        'completed_at',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'capacity' => 'integer',
        'registered_count' => 'integer',
        'attendees_count' => 'integer',
        'is_public' => 'boolean',
        'requires_ticket' => 'boolean',
        'tickets_for_all' => 'boolean',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime',
        'recap_images' => 'array',
    ];
}