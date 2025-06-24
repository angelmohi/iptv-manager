<?php

namespace App\Models;

class DownloadLog extends Model
{
    protected $fillable = [
        'account_id', 'ip', 'list', 'city', 'region', 'country', 'user_agent',
    ];
}
