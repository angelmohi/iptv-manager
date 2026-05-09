<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TVmaze API provider — https://www.tvmaze.com/api
 *
 * 100 % free, no API key, generous rate limit (~20 req / 10s). TV-only — for movie
 * lookups this provider returns null and the composite chain moves on to the next.
 *
 * Useful as an additional safety net for niche or country-specific shows that
 * TMDB and OMDb don't index well.
 */
class TvmazeMetadataProvider implements MetadataProvider
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.tvmaze.base_url', 'https://api.tvmaze.com'), '/');
    }

    public function search(string $title, string $type, ?int $year = null): ?MetadataResult
    {
        if ($type !== 'series') {
            return null;
        }

        $candidates = $this->searchShows($title);

        // Spanish exclamation/question marks aren't indexed by TVmaze.
        if (empty($candidates)) {
            $stripped = ltrim($title, '¡¿');
            if ($stripped !== $title) {
                $candidates = $this->searchShows($stripped);
            }
        }

        // Bare alphanumeric form catches dubbed titles whose punctuation differs.
        if (empty($candidates)) {
            $bare = trim(preg_replace('/\s{2,}/u', ' ',
                preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)));
            if ($bare !== '' && $bare !== $title) {
                $candidates = $this->searchShows($bare);
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Prefer a candidate whose premiered year matches the requested year exactly.
        if ($year) {
            foreach ($candidates as $candidate) {
                $premiered = $candidate['show']['premiered'] ?? null;
                if ($premiered && (int) substr($premiered, 0, 4) === $year) {
                    return $this->parseShow($candidate['show']);
                }
            }
        }

        // Fall back to TVmaze's own ranking (the API returns candidates by relevance score).
        return $this->parseShow($candidates[0]['show']);
    }

    public function fetchById(string $externalId, string $type): ?MetadataResult
    {
        if ($type !== 'series') {
            return null;
        }

        $response = Http::timeout(15)
            ->get($this->baseUrl . '/shows/' . urlencode($externalId), ['embed[]' => 'cast']);
        if (!$response->ok()) {
            Log::warning('TVmaze fetch failed', ['status' => $response->status(), 'id' => $externalId]);
            return null;
        }

        return $this->parseShow($response->json());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchShows(string $query): array
    {
        $response = Http::timeout(15)->get($this->baseUrl . '/search/shows', ['q' => $query]);
        if (!$response->ok()) {
            Log::warning('TVmaze search failed', ['status' => $response->status(), 'query' => $query]);
            return [];
        }
        return $response->json() ?? [];
    }

    /**
     * Convert a TVmaze show object into the unified MetadataResult shape. Strips HTML
     * from the summary because TVmaze stores it with <p> tags.
     *
     * @param array<string, mixed> $data
     */
    private function parseShow(array $data): MetadataResult
    {
        $year = null;
        if (!empty($data['premiered'])) {
            $year = (int) substr($data['premiered'], 0, 4);
        }

        $rating      = $data['rating']['average'] ?? null;
        $genres      = $data['genres'] ?? [];
        $poster      = $data['image']['original'] ?? ($data['image']['medium'] ?? null);
        $overview    = !empty($data['summary']) ? trim(strip_tags($data['summary'])) : null;
        $imdb        = $data['externals']['imdb'] ?? null;
        $runtime     = $data['runtime'] ?? ($data['averageRuntime'] ?? null);
        $title       = $data['name'] ?? '';

        // Cast comes embedded only when requested via embed[]=cast (used in fetchById);
        // /search/shows responses don't include it, so this stays empty for search hits.
        $cast = [];
        foreach (($data['_embedded']['cast'] ?? []) as $entry) {
            $person = $entry['person']    ?? [];
            $char   = $entry['character'] ?? [];
            $cast[] = [
                'name'        => $person['name']             ?? '',
                'character'   => $char['name']               ?? '',
                'profile_url' => $person['image']['medium']  ?? null,
            ];
            if (count($cast) >= 10) break;
        }

        return new MetadataResult(
            provider:        'tvmaze',
            externalId:      (string) ($data['id'] ?? ''),
            imdbId:          $imdb ?: null,
            title:           $title,
            originalTitle:   null,
            overview:        $overview,
            releaseYear:     $year,
            runtimeMinutes:  $runtime !== null ? (int) $runtime : null,
            posterUrl:       $poster,
            backdropUrl:     null,
            rating:          $rating !== null ? round((float) $rating, 1) : null,
            ratingCount:     null,
            genres:          $genres,
            cast:            $cast,
            trailerUrl:      null,
        );
    }
}
