<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $table = 'accounts';

    protected $fillable = [
        'username',
        'password',
        'name',
        'device_id',
        'folder',
        'parental_control',
        'token',
        'token_expires_at',
    ];

    public $timestamps = true;

    protected $dates = [
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];
}
