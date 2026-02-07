<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Person extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'persons';

    protected $fillable = [
        'last_name',
        'first_name',
        'email',
        'password',
        'google_id',
        'google_email',          // Added for Google OAuth
        'google_token',          // Added for Google OAuth
        'google_refresh_token',  // Added for Google OAuth
        'member_code',
        'cne',
        'avatar',
        'phone',
        'role',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'google_token',          // Hide sensitive tokens
        'google_refresh_token',  // Hide sensitive tokens
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}