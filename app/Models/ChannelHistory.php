<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelHistory extends Model
{
    protected $table = 'channel_history';

    protected $fillable = [
        'channel_id',
        'pssh',
        'api_key',
        'is_vod',
        'created_by',
    ];

    public $timestamps = true;

    // Only created_at is needed as per migration, but standard Laravel might expect both.
    // However, I defined only created_at in migration. 
    // To avoid errors with updated_at:
    const UPDATED_AT = null;

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
