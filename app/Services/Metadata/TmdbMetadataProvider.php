<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbMetadataProvider implements MetadataProvider
{
    private string $baseUrl;
    private string $imageUrl;
    private string $language;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.tmdb.base_url', 'https://api.themoviedb.org/3'), '/');
        $this->imageUrl = rtrim(config('services.tmdb.image_url', 'https://image.tmdb.org/t/p'), '/');
        $this->language = config('services.tmdb.language', 'es-ES');
        $this->apiKey   = config('services.tmdb.key');
    }

    public function search(string $title, string $type, ?int $year = null): ?MetadataResult
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('TMDB_API_KEY is not configured.');
        }

        $endpoint = $type === 'series' ? '/search/tv' : '/search/movie';
        $params = [
            'api_key'        => $this->apiKey,
            'language'       => $this->language,
            'query'          => $title,
            'include_adult'  => 'false',
        ];
        if ($year) {
            $params[$type === 'series' ? 'first_air_date_year' : 'year'] = $year;
        }

        $response = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
        if (!$response->ok()) {
            Log::warning('TMDB search failed', ['status' => $response->status(), 'title' => $title]);
            return null;
        }

        $results = $response->json('results') ?? [];

        // If no match with year, retry without it — the catalogue year sometimes
        // differs by one from TMDB (theatrical vs. streaming release, etc.).
        if (empty($results) && $year !== null) {
            $yearKey = $type === 'series' ? 'first_air_date_year' : 'year';
            unset($params[$yearKey]);
            $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
            if ($retry->ok()) {
                $results = $retry->json('results') ?? [];
            }
        }

        // TMDB's tokenizer treats "¡Word" as a single unknown token, so Spanish
        // titles beginning with ¡ or ¿ are never matched. Strip and retry.
        if (empty($results)) {
            $cleanTitle = ltrim($title, '¡¿');
            if ($cleanTitle !== $title) {
                $params['query'] = $cleanTitle;
                $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
                if ($retry->ok()) {
                    $results = $retry->json('results') ?? [];
                }
            }
        }

        // Last-resort: strip all punctuation so "Amor, luces, fiestas!" becomes
        // "Amor luces fiestas" — catches dubbed titles whose special chars differ
        // from TMDB's indexed form.
        if (empty($results)) {
            $bareTitle = trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title));
            $bareTitle = preg_replace('/\s{2,}/u', ' ', $bareTitle);
            if ($bareTitle !== $title) {
                $params['query'] = $bareTitle;
                $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
                if ($retry->ok()) {
                    $results = $retry->json('results') ?? [];
                }
            }
        }

        // Some movies are only indexed in TMDB under their original English title with
        // no Spanish translation stored. Retry bare title with en-US so TMDB searches
        // against English alternate titles as well.
        if (empty($results)) {
            $bareTitle = trim(preg_replace('/\s{2,}/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)));
            $params['query']    = $bareTitle;
            $params['language'] = 'en-US';
            $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
            if ($retry->ok()) {
                $results = $retry->json('results') ?? [];
            }
        }

        // Strip leading grammatical articles — TMDB indexes many titles without them
        // ("Inspector Gourmet", not "El inspector Gourmet"; "Avengers", not "The Avengers").
        // The TMDB website search does this normalisation automatically; the API does not.
        $bareTitle = trim(preg_replace('/\s{2,}/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)));
        $articlePattern = '/^(?:el|la|los|las|un|una|unos|unas|the|a|an|le|les|lo|gli|der|die|das|ein|eine|o|os|as|um|uma)\s+/iu';
        $articleStripped = preg_replace($articlePattern, '', $bareTitle);

        if (empty($results) && $articleStripped !== $bareTitle) {
            $params['query']    = $articleStripped;
            $params['language'] = $this->language;
            $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
            if ($retry->ok()) {
                $results = $retry->json('results') ?? [];
            }
        }

        // Fuzzy fallback: the TMDB website uses stemmed/fuzzy search (Elasticsearch)
        // while the API does exact tokenisation — so "fiestas" never matches "Fiesta".
        // Mimic the fuzzy behaviour by searching with the first 2 words of the
        // article-stripped bare title (broad candidate set) and picking the result
        // with the highest string similarity. Require ≥ 85 % to avoid false hits.
        if (empty($results)) {
            $fuzzyBase  = $articleStripped;  // already has article removed
            $words      = array_values(array_filter(explode(' ', $fuzzyBase)));
            if (count($words) >= 2) {
                $shortQuery = implode(' ', array_slice($words, 0, 2));
                $params['query']    = $shortQuery;
                $params['language'] = $this->language;
                $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
                if ($retry->ok()) {
                    $candidates = $retry->json('results') ?? [];
                    $searchNorm = mb_strtolower($bareTitle);
                    $titleField = $type === 'series' ? 'name' : 'title';
                    $bestResult = null;
                    $bestScore  = 0.0;
                    foreach ($candidates as $candidate) {
                        $cb = trim(preg_replace('/\s{2,}/u', ' ',
                            preg_replace('/[^\p{L}\p{N}\s]/u', ' ',
                                mb_strtolower($candidate[$titleField] ?? ''))));
                        similar_text($searchNorm, $cb, $pct);
                        if ($pct > $bestScore) { $bestScore = $pct; $bestResult = $candidate; }
                    }
                    if ($bestResult !== null && $bestScore >= 85.0) {
                        $results = [$bestResult];
                    }
                }
            }
        }

        // Before-separator fallback: dubbed titles often append a Spanish subtitle
        // after ", " or " – " (e.g. "Red Scorpion, programado para destruir").
        // Extract just the part before the separator and search with year — this
        // is the original title and matches exactly, bypassing similarity thresholds.
        if (empty($results)) {
            $beforeSep = trim(preg_split('/,\s+|\s+[-–]\s+/', $title, 2)[0] ?? '');
            if (mb_strlen($beforeSep) >= 3 && $beforeSep !== $title) {
                $params['query'] = $beforeSep;
                if ($year) {
                    $params[$type === 'series' ? 'first_air_date_year' : 'year'] = $year;
                }
                foreach (['es-ES', 'en-US'] as $lang) {
                    $params['language'] = $lang;
                    $retry = Http::timeout(15)->get($this->baseUrl . $endpoint, $params);
                    if ($retry->ok() && !empty($retry->json('results'))) {
                        $results = $retry->json('results');
                        break;
                    }
                }
            }
        }

        if (empty($results)) {
            return null;
        }

        $first = $results[0];
        $externalId = (string) $first['id'];

        return $this->fetchById($externalId, $type);
    }

    public function fetchById(string $externalId, string $type): ?MetadataResult
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('TMDB_API_KEY is not configured.');
        }

        $endpoint = $type === 'series' ? "/tv/{$externalId}" : "/movie/{$externalId}";
        $response = Http::timeout(15)->get($this->baseUrl . $endpoint, [
            'api_key'              => $this->apiKey,
            'language'             => $this->language,
            'append_to_response'   => 'credits,videos,external_ids',
        ]);

        if (!$response->ok()) {
            Log::warning('TMDB detail fetch failed', ['status' => $response->status(), 'id' => $externalId]);
            return null;
        }

        $data = $response->json();

        $title         = $type === 'series' ? ($data['name']          ?? '') : ($data['title']          ?? '');
        $originalTitle = $type === 'series' ? ($data['original_name'] ?? null) : ($data['original_title'] ?? null);
        $releaseDate   = $type === 'series' ? ($data['first_air_date'] ?? null) : ($data['release_date']  ?? null);
        $releaseYear   = $releaseDate ? (int) substr($releaseDate, 0, 4) : null;

        $runtime = null;
        if ($type === 'series') {
            $runtimes = $data['episode_run_time'] ?? [];
            $runtime = !empty($runtimes) ? (int) $runtimes[0] : null;
        } else {
            $runtime = isset($data['runtime']) ? (int) $data['runtime'] : null;
        }

        $genres = array_map(fn ($g) => $g['name'], $data['genres'] ?? []);

        $cast = [];
        $rawCast = $data['credits']['cast'] ?? [];
        foreach (array_slice($rawCast, 0, 10) as $member) {
            $cast[] = [
                'name'        => $member['name'] ?? '',
                'character'   => $member['character'] ?? '',
                'profile_url' => !empty($member['profile_path'])
                    ? "{$this->imageUrl}/w185{$member['profile_path']}"
                    : null,
            ];
        }

        $trailerUrl = null;
        foreach ($data['videos']['results'] ?? [] as $video) {
            if (($video['site'] ?? '') === 'YouTube' && in_array($video['type'] ?? '', ['Trailer', 'Teaser'], true)) {
                $trailerUrl = "https://www.youtube.com/embed/{$video['key']}";
                break;
            }
        }

        $imdbId = $data['external_ids']['imdb_id'] ?? ($data['imdb_id'] ?? null);

        return new MetadataResult(
            provider:        'tmdb',
            externalId:      (string) ($data['id'] ?? $externalId),
            imdbId:          $imdbId ?: null,
            title:           $title ?: '',
            originalTitle:   $originalTitle,
            overview:        $data['overview'] ?? null,
            releaseYear:     $releaseYear,
            runtimeMinutes:  $runtime,
            posterUrl:       !empty($data['poster_path'])   ? "{$this->imageUrl}/w500{$data['poster_path']}"   : null,
            backdropUrl:     !empty($data['backdrop_path']) ? "{$this->imageUrl}/w1280{$data['backdrop_path']}" : null,
            rating:          isset($data['vote_average']) ? round((float) $data['vote_average'], 1) : null,
            ratingCount:     isset($data['vote_count'])   ? (int) $data['vote_count'] : null,
            genres:          $genres,
            cast:            $cast,
            trailerUrl:      $trailerUrl,
        );
    }
}
