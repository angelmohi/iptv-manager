<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use SoftDeletes;

    protected $table = 'channels';

    protected $fillable = [
        'category_id',
        'name',
        'tvg_id',
        'logo',
        'user_agent',
        'manifest_type',
        'license_type',
        'api_key',
        'url_channel',
        'catchup',
        'catchup_days',
        'catchup_source',
        'order',
        'is_active',
        'apply_token',
    ];

    public $timestamps = true;

    public function category()
    {
        return $this->belongsTo(ChannelCategory::class, 'category_id');
    }
}
