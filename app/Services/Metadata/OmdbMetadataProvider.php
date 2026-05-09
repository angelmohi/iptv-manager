<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OMDb API provider — http://www.omdbapi.com
 *
 * Used as a fallback after TMDB. The free tier requires a key (1000 requests/day),
 * obtainable at https://www.omdbapi.com/apikey.aspx. OMDb is indexed by IMDb so it
 * tends to find titles that TMDB lists only under their original English name.
 *
 * Returns null gracefully when the key is not configured, so it can be safely
 * chained inside CompositeMetadataProvider without breaking other fallbacks.
 */
class OmdbMetadataProvider implements MetadataProvider
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.omdb.base_url', 'https://www.omdbapi.com/'), '/') . '/';
        $this->apiKey  = config('services.omdb.key') ?: null;
    }

    public function search(string $title, string $type, ?int $year = null): ?MetadataResult
    {
        if (!$this->apiKey) {
            return null;
        }

        $omdbType = $type === 'series' ? 'series' : 'movie';

        // Strategy: try the strictest query first (title + year + type), then relax.
        // OMDb's t= endpoint returns at most one result, so when it misses we fall back
        // to the s= endpoint which returns up to 10 candidates.
        $attempts = [];
        if ($year) {
            $attempts[] = ['mode' => 't', 'query' => $title, 'year' => $year];
        }
        $attempts[] = ['mode' => 't', 'query' => $title, 'year' => null];

        // Strip leading Spanish punctuation that OMDb doesn't index ("¡Vamos!" → "Vamos!")
        $stripped = ltrim($title, '¡¿');
        if ($stripped !== $title) {
            $attempts[] = ['mode' => 't', 'query' => $stripped, 'year' => null];
        }

        // Bare alphanumeric form for dubbed titles whose punctuation differs
        $bareTitle = trim(preg_replace('/\s{2,}/u', ' ',
            preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)));
        if ($bareTitle !== '' && $bareTitle !== $title) {
            $attempts[] = ['mode' => 't', 'query' => $bareTitle, 'year' => null];
        }

        // Strip leading articles before falling back to multi-result search,
        // so "El último samurái" finds "Last Samurai" matches.
        $articlePattern  = '/^(?:el|la|los|las|un|una|unos|unas|the|a|an|le|les|lo|gli|der|die|das|ein|eine|o|os|as|um|uma)\s+/iu';
        $articleStripped = preg_replace($articlePattern, '', $bareTitle);

        foreach ($attempts as $attempt) {
            $data = $this->callOmdb([
                't'    => $attempt['query'],
                'type' => $omdbType,
                'y'    => $attempt['year'],
                'plot' => 'full',
            ]);
            if ($data && ($data['Response'] ?? 'False') === 'True') {
                return $this->parseDetail($data);
            }
        }

        // Last resort: multi-result search and pick the best match by similar_text
        $candidates = $this->searchCandidates($articleStripped !== '' ? $articleStripped : $title, $omdbType, $year);
        if (empty($candidates)) {
            return null;
        }

        $bestImdb = $this->pickBestCandidate($candidates, $bareTitle ?: $title);
        if ($bestImdb === null) {
            return null;
        }

        return $this->fetchById($bestImdb, $type);
    }

    public function fetchById(string $externalId, string $type): ?MetadataResult
    {
        if (!$this->apiKey) {
            return null;
        }

        // OMDb is keyed by IMDb id (ttXXXXXXX). If a non-IMDb id is passed, OMDb returns
        // Response=False — the provider degrades gracefully.
        $data = $this->callOmdb([
            'i'    => $externalId,
            'plot' => 'full',
        ]);
        if (!$data || ($data['Response'] ?? 'False') !== 'True') {
            return null;
        }

        return $this->parseDetail($data);
    }

    /**
     * Run a multi-result search and return the array of candidates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchCandidates(string $title, string $omdbType, ?int $year): array
    {
        $params = ['s' => $title, 'type' => $omdbType];
        if ($year) {
            $params['y'] = $year;
        }
        $data = $this->callOmdb($params);
        if (!$data || ($data['Response'] ?? 'False') !== 'True') {
            // Retry without year if year filter starved the search
            if ($year) {
                unset($params['y']);
                $data = $this->callOmdb($params);
            }
        }
        return ($data && ($data['Response'] ?? 'False') === 'True')
            ? ($data['Search'] ?? [])
            : [];
    }

    /**
     * Pick the candidate whose Title is most similar to the original search term.
     * Returns the IMDb id of the winner, or null if no candidate clears the threshold.
     *
     * @param array<int, array<string, mixed>> $candidates
     */
    private function pickBestCandidate(array $candidates, string $search): ?string
    {
        $searchNorm = mb_strtolower(trim($search));
        $best       = null;
        $bestScore  = 0.0;

        foreach ($candidates as $candidate) {
            $candidateTitle = mb_strtolower($candidate['Title'] ?? '');
            similar_text($searchNorm, $candidateTitle, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best      = $candidate;
            }
        }

        if ($best !== null && $bestScore >= 70.0 && !empty($best['imdbID'])) {
            return $best['imdbID'];
        }
        return null;
    }

    /**
     * Issue an HTTP GET against OMDb, normalising parameters and stripping nulls.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function callOmdb(array $params): ?array
    {
        $params['apikey'] = $this->apiKey;
        $params['r']      = 'json';
        $params           = array_filter($params, fn ($v) => $v !== null && $v !== '');

        $response = Http::timeout(15)->get($this->baseUrl, $params);
        if (!$response->ok()) {
            Log::warning('OMDb request failed', ['status' => $response->status(), 'params' => $params]);
            return null;
        }
        return $response->json();
    }

    /**
     * Convert an OMDb detail response into the unified MetadataResult shape.
     *
     * @param array<string, mixed> $data
     */
    private function parseDetail(array $data): MetadataResult
    {
        $year = null;
        if (!empty($data['Year']) && preg_match('/(19|20)\d{2}/', $data['Year'], $m)) {
            $year = (int) $m[0];
        }

        $runtime = null;
        if (!empty($data['Runtime']) && $data['Runtime'] !== 'N/A'
            && preg_match('/(\d+)/', $data['Runtime'], $m)
        ) {
            $runtime = (int) $m[1];
        }

        $genres = [];
        if (!empty($data['Genre']) && $data['Genre'] !== 'N/A') {
            $genres = array_values(array_filter(array_map('trim', explode(',', $data['Genre']))));
        }

        // OMDb only provides a comma-separated string of actor names, no characters
        // and no profile images — but it's still a useful signal for the catalogue UI.
        $cast = [];
        if (!empty($data['Actors']) && $data['Actors'] !== 'N/A') {
            foreach (array_map('trim', explode(',', $data['Actors'])) as $name) {
                if ($name === '') continue;
                $cast[] = ['name' => $name, 'character' => '', 'profile_url' => null];
            }
        }

        $rating = null;
        if (!empty($data['imdbRating']) && $data['imdbRating'] !== 'N/A') {
            $rating = round((float) $data['imdbRating'], 1);
        }

        $ratingCount = null;
        if (!empty($data['imdbVotes']) && $data['imdbVotes'] !== 'N/A') {
            $ratingCount = (int) str_replace(',', '', $data['imdbVotes']);
        }

        $poster = (!empty($data['Poster']) && $data['Poster'] !== 'N/A') ? $data['Poster'] : null;

        $overview = (!empty($data['Plot']) && $data['Plot'] !== 'N/A') ? $data['Plot'] : null;

        $imdbId = !empty($data['imdbID']) ? $data['imdbID'] : null;

        return new MetadataResult(
            provider:        'omdb',
            externalId:      $imdbId ?? '',
            imdbId:          $imdbId,
            title:           $data['Title'] ?? '',
            originalTitle:   null,
            overview:        $overview,
            releaseYear:     $year,
            runtimeMinutes:  $runtime,
            posterUrl:       $poster,
            backdropUrl:     null,
            rating:          $rating,
            ratingCount:     $ratingCount,
            genres:          $genres,
            cast:            $cast,
            trailerUrl:      null,
        );
    }
}
