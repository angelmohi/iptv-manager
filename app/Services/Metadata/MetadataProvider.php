<?php

namespace App\Services\Metadata;

interface MetadataProvider
{
    /**
     * Search for a movie or series by title.
     * $type is 'movie' or 'series'.
     */
    public function search(string $title, string $type, ?int $year = null): ?MetadataResult;

    /**
     * Fetch full detail by external provider id.
     */
    public function fetchById(string $externalId, string $type): ?MetadataResult;
}
