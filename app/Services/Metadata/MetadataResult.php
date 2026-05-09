<?php

namespace App\Services\Metadata;

class MetadataResult
{
    public function __construct(
        public string  $provider,
        public string  $externalId,
        public ?string $imdbId,
        public string  $title,
        public ?string $originalTitle,
        public ?string $overview,
        public ?int    $releaseYear,
        public ?int    $runtimeMinutes,
        public ?string $posterUrl,
        public ?string $backdropUrl,
        public ?float  $rating,
        public ?int    $ratingCount,
        public array   $genres = [],
        public array   $cast = [],
        public ?string $trailerUrl = null,
    ) {}
}
