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
        'google_email',
        'google_token',
        'google_refresh_token',
        'member_code',
        'cne',
        'avatar',
        'phone',
        'role',
        'is_active',
        'email_verified_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'google_token',
        'google_refresh_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'is_active'               => 'boolean',
        'two_factor_enabled'      => 'boolean',
        'email_verified_at'       => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];
}