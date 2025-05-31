<?php

namespace App\Models;

class DownloadLog extends Model
{
    protected $fillable = [
        'ip', 'list', 'city', 'region', 'country', 'user_agent',
    ];
}
