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
        'pssh',
        'catchup',
        'catchup_days',
        'catchup_source',
        'catchup_pssh',
        'catchup_api_key',
        'order',
        'is_active',
        'apply_token',
        'parental_control',
		'tvg_type',
    ];

    public $timestamps = true;
    
    protected static function booted()
    {
        static::updated(function ($channel) {
            if ($channel->wasChanged(['pssh', 'api_key'])) {
                ChannelHistory::create([
                    'channel_id' => $channel->id,
                    'pssh' => $channel->pssh,
                    'api_key' => $channel->api_key,
                    'is_vod' => false,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($channel->wasChanged(['catchup_pssh', 'catchup_api_key'])) {
                ChannelHistory::create([
                    'channel_id' => $channel->id,
                    'pssh' => $channel->catchup_pssh,
                    'api_key' => $channel->catchup_api_key,
                    'is_vod' => true,
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(ChannelCategory::class, 'category_id');
    }
}
