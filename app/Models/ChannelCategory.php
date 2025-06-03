<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelCategory extends Model
{
    use SoftDeletes;

    protected $table = 'channel_categories';

    protected $fillable = [
        'name',
        'order',
    ];

    public $timestamps = true;

    public function channels()
    {
        return $this->hasMany(Channel::class, 'category_id');
    }
}
