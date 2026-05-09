<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelMetadata extends Model
{
    protected $table = 'channel_metadata';

    protected $fillable = [
        'channel_id',
        'provider',
        'external_id',
        'imdb_id',
        'title',
        'original_title',
        'overview',
        'release_year',
        'runtime_minutes',
        'poster_url',
        'backdrop_url',
        'rating',
        'rating_count',
        'rating_imdb',
        'rating_imdb_count',
        'rating_filmaffinity',
        'genres',
        'cast',
        'trailer_url',
        'match_status',
        'enriched_at',
    ];

    protected $casts = [
        'genres'      => 'array',
        'cast'        => 'array',
        'rating'               => 'float',
        'rating_imdb'          => 'float',
        'rating_imdb_count'    => 'integer',
        'rating_filmaffinity'  => 'float',
        'enriched_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}
