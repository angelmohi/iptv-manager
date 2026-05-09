<?php

namespace App\Services\Metadata;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FilmAffinityService
{
    private const BASE_URL      = 'https://www.filmaffinity.com/es';
    private const SEARCH_URL    = self::BASE_URL . '/search.php';
    private const ADV_SEARCH_URL = self::BASE_URL . '/advsearch.php';

    /** Milliseconds to wait between the search call and the film-page fetch. */
    private const INNER_DELAY_MS = 1000;

    /** Max retries per request before raising RateLimitException. */
    private const MAX_ATTEMPTS = 3;

    /**
     * When the first TMDB cast name appears in a FA search row, it must outweigh
     * year hints (release_year is often wrong).
     */
    private const SCORE_ACTOR_IN_LISTING = 520;

    /** Same scale added when actor is confirmed on the film page (tie-break). */
    private const SCORE_ACTOR_ON_PAGE = 520;

    /** Soft hints from candidate year vs metadata (must stay below SCORE_ACTOR_* ). */
    private const SCORE_YEAR_EXACT = 38;

    private const SCORE_YEAR_OFF_1 = 26;

    private const SCORE_YEAR_OFF_2 = 14;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15',
    ];

    private ?CookieJar $jar = null;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns the FilmAffinity rating or null when not found.
     *
     * @throws RateLimitException when FilmAffinity blocks all retry attempts.
     */
    public function search(string $title, ?int $year = null): ?float
    {
        return $this->searchWithDetails($title, $year)['rating'];
    }

    /**
     * Like search() but also returns process details for display purposes.
     *
     * Returns:
     *   rating      ?float   The extracted rating, or null.
     *   film_url    ?string  URL of the film page fetched.
     *   film_id     ?string  FA film ID chosen from the listing (null on direct redirect).
     *   source      string   'direct' | 'listing' | 'none'
     *   candidates  array    All candidates found in the listing (empty on direct redirect).
     *
     * @throws RateLimitException when FilmAffinity blocks all retry attempts.
     */
    public function searchWithDetails(string $title, ?int $year = null, ?string $firstActor = null): array
    {
        $this->initSession();
        return $this->searchByHtmlPage($title, $year, $firstActor);
    }

    /**
     * Drops the current cookie jar and opens a fresh session.
     * Call this after a RateLimitException before retrying.
     */
    public function resetSession(): void
    {
        $this->jar = null;
        $this->initSession();
    }

    // ── FilmAffinity search ───────────────────────────────────────────────────

    /**
     * Searches FilmAffinity using the advanced search when a year is available
     * (filters by fromyear/toyear to reduce false matches), falling back to
     * the basic search when no year is known or when the advanced search
     * returns no candidates.
     *
     * Returns an array with keys: rating, film_url, film_id, source, candidates.
     *
     * @throws RateLimitException when blocked after all retries.
     */
    private function searchByHtmlPage(string $title, ?int $year, ?string $firstActor = null): array
    {
        $empty = [
            'rating'              => null,
            'film_url'            => null,
            'film_id'             => null,
            'source'              => 'none',
            'candidates'          => [],
            'search_type'         => 'none',
            'release_year_used'   => $year,
        ];

        // ── Build request params ──────────────────────────────────────────────
        if ($year !== null) {
            $searchUrl    = self::ADV_SEARCH_URL;
            $searchParams = [
                'stext'      => $title,
                'stype[]'    => 'title',
                'country'    => '',
                'genre'      => '',
                'fromyear'   => $year - 1,
                'toyear'     => $year + 1,
            ];
            $usedAdvanced = true;
        } else {
            $searchUrl    = self::SEARCH_URL;
            $searchParams = ['stype' => 'title', 'stext' => $title];
            $usedAdvanced = false;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withOptions([
                        'cookies'         => $this->jar,
                        'allow_redirects' => ['max' => 10, 'track_redirects' => true],
                    ])
                    ->withHeaders($this->headers())
                    ->timeout(20)
                    ->get($searchUrl, $searchParams);

                if ($this->isCaptchaRequired($response)) {
                    throw new RateLimitException('captcha');
                }

                if ($this->isBlocked($response)) {
                    Log::info("FilmAffinity: bloqueo buscando '{$title}' (intento {$attempt}) status={$response->status()}");
                    if ($attempt < self::MAX_ATTEMPTS) {
                        $this->backoff($attempt);
                        $this->resetSession();
                        continue;
                    }
                    throw new RateLimitException("FilmAffinity bloqueó todas las peticiones para '{$title}'");
                }

                if (!$response->ok()) {
                    return $empty;
                }

                $html = $response->body();

                // Single result → FA redirected straight to the film page.
                if ($this->isFilmPage($html)) {
                    $filmUrl = $this->extractCanonicalUrl($html);
                    $filmId  = $filmUrl ? $this->filmIdFromUrl($filmUrl) : null;
                    $rating  = $this->parseFilmPage($html);
                    return [
                        'rating'              => $rating,
                        'film_url'            => $filmUrl,
                        'film_id'             => $filmId,
                        'source'              => 'direct',
                        'search_type'         => $usedAdvanced ? 'advanced' : 'basic',
                        'candidates'          => [],
                        'release_year_used'   => $year,
                    ];
                }

                // Multiple results → pick best candidate and fetch its page.
                [$filmId, $candidates] = $this->extractBestFilmId($html, $title, $year, $firstActor);

                if ($filmId !== null && $firstActor !== null) {
                    $filmId = $this->resolveTieByActorPage($filmId, $candidates, $firstActor) ?? $filmId;
                }

                // Advanced search returned no candidates — retry with basic search.
                if ($filmId === null && $usedAdvanced) {
                    Log::debug("FilmAffinity: búsqueda avanzada sin candidatos para '{$title}' ({$year}), reintentando con básica…");
                    $fallback = $this->searchBasic($title, $year, $firstActor);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                    return array_merge($empty, ['candidates' => $candidates, 'search_type' => 'advanced+basic']);
                }

                if ($filmId === null) {
                    return array_merge($empty, ['candidates' => $candidates, 'search_type' => 'basic']);
                }

                $filmUrl = self::BASE_URL . '/film' . $filmId . '.html';
                usleep(self::INNER_DELAY_MS * 1000);

                $rating = $this->fetchFilmPage($filmUrl);
                return [
                    'rating'              => $rating,
                    'film_url'            => $filmUrl,
                    'film_id'             => $filmId,
                    'source'              => 'listing',
                    'search_type'         => $usedAdvanced ? 'advanced' : 'basic',
                    'candidates'          => $candidates,
                    'release_year_used'   => $year,
                ];

            } catch (RateLimitException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::debug("FilmAffinity search error for '{$title}': " . $e->getMessage());
                return $empty;
            }
        }

        return $empty;
    }

    /**
     * Basic /search.php fallback (no year filter).
     * Used when the advanced search returns zero candidates.
     */
    private function searchBasic(string $title, ?int $year, ?string $firstActor = null): ?array
    {
        try {
            $response = Http::withOptions([
                    'cookies'         => $this->jar,
                    'allow_redirects' => ['max' => 10, 'track_redirects' => true],
                ])
                ->withHeaders($this->headers())
                ->timeout(20)
                ->get(self::SEARCH_URL, ['stype' => 'title', 'stext' => $title]);

            if ($this->isCaptchaRequired($response)) {
                throw new RateLimitException('captcha');
            }
            if ($this->isBlocked($response) || !$response->ok()) {
                return null;
            }

            $html = $response->body();

            if ($this->isFilmPage($html)) {
                $filmUrl = $this->extractCanonicalUrl($html);
                $filmId  = $filmUrl ? $this->filmIdFromUrl($filmUrl) : null;
                return [
                    'rating'              => $this->parseFilmPage($html),
                    'film_url'            => $filmUrl,
                    'film_id'             => $filmId,
                    'source'              => 'direct',
                    'search_type'         => 'basic',
                    'candidates'          => [],
                    'release_year_used'   => $year,
                ];
            }

            [$filmId, $candidates] = $this->extractBestFilmId($html, $title, $year, $firstActor);

            if ($filmId !== null && $firstActor !== null) {
                $filmId = $this->resolveTieByActorPage($filmId, $candidates, $firstActor) ?? $filmId;
            }

            if ($filmId === null) {
                return null;
            }

            $filmUrl = self::BASE_URL . '/film' . $filmId . '.html';
            usleep(self::INNER_DELAY_MS * 1000);

            return [
                'rating'              => $this->fetchFilmPage($filmUrl),
                'film_url'            => $filmUrl,
                'film_id'             => $filmId,
                'source'              => 'listing',
                'search_type'         => 'basic',
                'candidates'          => $candidates,
                'release_year_used'   => $year,
            ];
        } catch (RateLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::debug("FilmAffinity searchBasic error for '{$title}': " . $e->getMessage());
            return null;
        }
    }

    private function fetchFilmPage(string $url): ?float
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $this->http()->get($url);

                if ($this->isCaptchaRequired($response)) {
                    throw new RateLimitException('captcha');
                }

                if ($this->isBlocked($response)) {
                    Log::info("FilmAffinity: bloqueo en film page (intento {$attempt}) · {$url} status={$response->status()}");
                    if ($attempt < self::MAX_ATTEMPTS) {
                        $this->backoff($attempt);
                        $this->resetSession();
                        continue;
                    }
                    throw new RateLimitException("FilmAffinity bloqueó la descarga de '{$url}'");
                }

                return $response->ok() ? $this->parseFilmPage($response->body()) : null;

            } catch (RateLimitException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::debug("FilmAffinity film page error '{$url}': " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    // ── HTML parsers ──────────────────────────────────────────────────────────

    private function isFilmPage(string $html): bool
    {
        if (
            str_contains($html, 'Resultados de la búsqueda') ||
            str_contains($html, '>Resultados para')
        ) {
            return false;
        }

        if (str_contains($html, 'id="movie-rat-avg"') || str_contains($html, 'avgrat-box')) {
            return true;
        }

        if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\'][^"\']*?/film\d+\.html["\']#i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Returns [bestFilmId|null, candidates[]] from a search-results listing page.
     * Each candidate: ['id', 'title', 'year', 'score', 'actor_match'].
     *
     * Candidates are deduplicated by film ID. When $firstActor is provided, a
     * listing hit uses SCORE_ACTOR_IN_LISTING (well above year hints). If at
     * least one row matches the actor, the winner is chosen only among those
     * rows. Page-level confirmation adds SCORE_ACTOR_ON_PAGE in resolveTieByActorPage().
     */
    private function extractBestFilmId(string $html, string $title, ?int $year, ?string $firstActor = null): array
    {
        $xpath = $this->xpath($html);

        $candidates = [];
        $seenIds    = [];

        $seItems = $xpath->query('//*[contains(@class,"se-it")]');
        foreach ($seItems as $item) {
            $linkNode = $xpath->query('.//a[contains(@href,"/film") and contains(@href,".html")]', $item)->item(0);
            if (!$linkNode instanceof \DOMElement) {
                continue;
            }
            $href = $linkNode->getAttribute('href');
            if (!preg_match('#/film(\d+)\.html#', $href, $m)) {
                continue;
            }
            $filmId = $m[1];
            if (isset($seenIds[$filmId])) {
                continue;
            }
            $seenIds[$filmId] = true;

            $itemTitle = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));
            $itemText  = trim(preg_replace('/\s+/', ' ', $item->textContent));
            $itemYear  = null;
            if (preg_match('/\b(19\d{2}|20[012]\d)\b/', $itemText, $ym)) {
                $itemYear = (int) $ym[1];
            }
            $candidates[] = ['id' => $filmId, 'title' => $itemTitle, 'year' => $itemYear, 'score' => 0, 'item_text' => $itemText, 'actor_match' => false];
        }

        if (empty($candidates)) {
            $allLinks = $xpath->query('//a[contains(@href,"/film") and contains(@href,".html")]');
            foreach ($allLinks as $link) {
                if (!$link instanceof \DOMElement) {
                    continue;
                }
                $href = $link->getAttribute('href');
                if (!preg_match('#/film(\d+)\.html#', $href, $m)) {
                    continue;
                }
                $filmId = $m[1];
                if (isset($seenIds[$filmId])) {
                    continue;
                }
                $seenIds[$filmId] = true;

                $itemTitle = trim(preg_replace('/\s+/', ' ', $link->textContent));
                if (mb_strlen($itemTitle) < 2) {
                    continue;
                }
                $candidates[] = ['id' => $filmId, 'title' => $itemTitle, 'year' => null, 'score' => 0, 'item_text' => '', 'actor_match' => false];
            }
        }

        if (empty($candidates)) {
            return [null, []];
        }

        $normalizedActor = $firstActor ? mb_strtolower($firstActor) : null;
        $normQueryTitle  = mb_strtolower($title);

        $bestId    = $candidates[0]['id'];
        $bestScore = PHP_INT_MIN;

        foreach ($candidates as &$c) {
            // FA often puts the title outside the link — fall back to the full row text.
            $titleForMatch = $c['title'] !== ''
                ? $c['title']
                : mb_substr($c['item_text'], 0, 200);

            similar_text($normQueryTitle, mb_strtolower($titleForMatch), $pct);
            $score = $pct;

            // Gentle year hints — metadata release_year is often wrong; keep below actor bonuses.
            if ($year !== null) {
                if ($c['year'] !== null) {
                    $dy = abs($year - $c['year']);
                    if ($dy === 0) {
                        $score += self::SCORE_YEAR_EXACT;
                    } elseif ($dy === 1) {
                        $score += self::SCORE_YEAR_OFF_1;
                    } elseif ($dy === 2) {
                        $score += self::SCORE_YEAR_OFF_2;
                    } else {
                        $score -= min(55, 5 * $dy);
                    }
                } else {
                    $score -= 18;
                }
            } elseif ($c['year'] !== null) {
                $score += 5;
            }

            // Cast name in the listing row — primary signal when TMDB cast is available.
            if ($normalizedActor && str_contains(mb_strtolower($c['item_text']), $normalizedActor)) {
                $score += self::SCORE_ACTOR_IN_LISTING;
                $c['actor_match'] = true;
            }
            $c['score'] = round($score, 1);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId    = $c['id'];
            }
        }
        unset($c);

        // If any result row matched the known cast name, only choose among those rows.
        if ($normalizedActor) {
            $withActor = array_values(array_filter(
                $candidates,
                static fn (array $row): bool => $row['actor_match'] === true
            ));
            if ($withActor !== []) {
                usort($withActor, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
                $bestId = $withActor[0]['id'];
            }
        }

        // Strip internal helper field before returning.
        foreach ($candidates as &$c) {
            unset($c['item_text']);
        }
        unset($c);

        return [$bestId, $candidates];
    }

    /**
     * Fetches each tied candidate's film page (up to 3) and checks whether
     * $firstActor appears in the HTML. Useful when FA listings don't show cast.
     *
     * Updates $candidates in-place (actor_match flag). Returns the winning film
     * ID, or null if the tie could not be broken.
     *
     * @param array<int,array<string,mixed>> $candidates
     */
    private function resolveTieByActorPage(string $currentBestId, array &$candidates, string $firstActor): ?string
    {
        $topScore = max(array_column($candidates, 'score'));
        $tied     = array_values(array_filter($candidates, fn ($c) => $c['score'] >= $topScore));

        if (count($tied) <= 1) {
            return null;
        }

        // If the fast-path (listing text) already resolved the tie, nothing to do.
        $alreadyMatched = array_filter($tied, fn ($c) => $c['actor_match']);
        if (!empty($alreadyMatched)) {
            return null;
        }

        $normalized = mb_strtolower($firstActor);

        foreach (array_slice($tied, 0, 3) as $c) {
            $url = self::BASE_URL . '/film' . $c['id'] . '.html';
            try {
                $response = $this->http()->get($url);
                if ($response->ok() && str_contains(mb_strtolower($response->body()), $normalized)) {
                    foreach ($candidates as &$cand) {
                        if ($cand['id'] === $c['id']) {
                            $cand['actor_match'] = true;
                            $cand['score']       = round((float) $cand['score'] + self::SCORE_ACTOR_ON_PAGE, 1);
                        }
                    }
                    unset($cand);
                    return $c['id'];
                }
                usleep(self::INNER_DELAY_MS * 1000);
            } catch (\Throwable) {
                // Try the next tied candidate.
            }
        }

        return null;
    }

    private function extractCanonicalUrl(string $html): ?string
    {
        if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']#i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    private function filmIdFromUrl(string $url): ?string
    {
        if (preg_match('#/film(\d+)\.html#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function parseFilmPage(string $html): ?float
    {
        $xpath = $this->xpath($html);

        foreach ([
            '//*[@id="movie-rat-avg"]',
            '//*[contains(@class,"avgrat-box")]',
            '//*[contains(@class,"avg-rating")]',
            '//*[@itemprop="ratingValue"]',
        ] as $xq) {
            $node = $xpath->query($xq)->item(0);
            if ($node) {
                $rating = $this->parseRating($node->textContent);
                if ($rating !== null) {
                    return $rating;
                }
            }
        }

        return null;
    }

    // ── Blocking detection ────────────────────────────────────────────────────

    private function isCaptchaRequired(Response $response): bool
    {
        return $response->status() === 429
            && str_contains($response->body(), 'faCaptcha');
    }

    private function isBlocked(Response $response): bool
    {
        if (in_array($response->status(), [429, 503, 403], true)) {
            return true;
        }

        $body = $response->body();

        if (preg_match('#/film\d+\.html#', $body) || str_contains($body, 'filmaffinity.com')) {
            return false;
        }

        if (
            str_contains($body, 'cf-browser-verification') ||
            str_contains($body, 'challenge-platform') ||
            str_contains($body, '<title>Just a moment') ||
            str_contains($body, 'Checking your browser before accessing')
        ) {
            return true;
        }

        return $response->ok() && strlen(trim($body)) < 10;
    }

    // ── HTTP / session ────────────────────────────────────────────────────────

    private function initSession(): void
    {
        if ($this->jar !== null) {
            return;
        }

        $this->jar = new CookieJar();

        try {
            Http::withOptions(['cookies' => $this->jar])
                ->withHeaders($this->headers())
                ->timeout(15)
                ->get(self::BASE_URL . '/');
        } catch (\Throwable) {
            // Best-effort: continue without cookies if the homepage fails.
        }
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
                'cookies'         => $this->jar,
                'allow_redirects' => ['max' => 5, 'track_redirects' => false],
            ])
            ->withHeaders($this->headers())
            ->timeout(20);
    }

    private function headers(): array
    {
        return [
            'User-Agent'      => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            'Referer'         => self::BASE_URL . '/',
            'DNT'             => '1',
        ];
    }

    // ── Backoff ───────────────────────────────────────────────────────────────

    private function backoff(int $attempt): void
    {
        $seconds = 10 * (3 ** ($attempt - 1));
        Log::info("FilmAffinity: esperando {$seconds}s antes del reintento {$attempt}…");
        sleep($seconds);
    }

    // ── Diagnostics ───────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function diagnose(string $title, ?int $year = null): array
    {
        $this->initSession();

        $cookies = implode('; ', array_map(
            fn ($c) => $c->getName() . '=' . $c->getValue(),
            iterator_to_array($this->jar)
        ));

        $result = [
            'cookies'      => $cookies ?: '(ninguna)',
            'search_query' => $title . ($year ? " ({$year})" : ''),
        ];

        // Probe advanced search (with year filter) if year is known.
        if ($year !== null) {
            $advUrl = self::ADV_SEARCH_URL . '?' . http_build_query([
                'stext'    => $title,
                'stype[]'  => 'title',
                'country'  => '',
                'genre'    => '',
                'fromyear' => $year - 1,
                'toyear'   => $year + 1,
            ]);

            try {
                $r    = Http::withOptions([
                        'cookies'         => $this->jar,
                        'allow_redirects' => ['max' => 10, 'track_redirects' => true],
                    ])
                    ->withHeaders($this->headers())
                    ->timeout(20)
                    ->get($advUrl);
                $body = $r->body();

                $result['probe']['advanced'] = [
                    'url'          => $advUrl,
                    'status'       => $r->status(),
                    'blocked'      => $this->isBlocked($r),
                    'captcha'      => $this->isCaptchaRequired($r),
                    'is_film_page' => $this->isFilmPage($body),
                    'film_ids'     => $this->extractFilmIds($body),
                    'body_sample'  => mb_substr(preg_replace('/\s+/', ' ', $body), 0, 400),
                ];
            } catch (\Throwable $e) {
                $result['probe']['advanced'] = ['error' => $e->getMessage()];
            }
        }

        // Probe basic search.
        $basicUrl = self::SEARCH_URL . '?stype=title&stext=' . urlencode($title);

        try {
            $r    = Http::withOptions([
                    'cookies'         => $this->jar,
                    'allow_redirects' => ['max' => 10, 'track_redirects' => true],
                ])
                ->withHeaders($this->headers())
                ->timeout(20)
                ->get($basicUrl);
            $body = $r->body();

            $result['probe']['basic'] = [
                'url'          => $basicUrl,
                'status'       => $r->status(),
                'blocked'      => $this->isBlocked($r),
                'captcha'      => $this->isCaptchaRequired($r),
                'is_film_page' => $this->isFilmPage($body),
                'film_ids'     => $this->extractFilmIds($body),
                'body_sample'  => mb_substr(preg_replace('/\s+/', ' ', $body), 0, 400),
            ];
        } catch (\Throwable $e) {
            $result['probe']['basic'] = ['error' => $e->getMessage()];
        }

        return $result;
    }

    /**
     * Raw search probe for --show-suggest.
     *
     * @return array<string, mixed>
     */
    public function rawSuggest(string $title, ?int $year = null): array
    {
        $this->initSession();

        try {
            $response = Http::withOptions([
                    'cookies'         => $this->jar,
                    'allow_redirects' => ['max' => 10, 'track_redirects' => true],
                ])
                ->withHeaders($this->headers())
                ->timeout(20)
                ->get(self::SEARCH_URL, [
                    'stype' => 'title',
                    'stext' => $title,
                ]);

            $body = $response->body();

            return [
                'status'       => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'blocked'      => $this->isBlocked($response),
                'is_film_page' => $this->isFilmPage($body),
                'film_ids'     => $this->extractFilmIds($body),
                'body'         => $body,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /** @return string[] */
    private function extractFilmIds(string $html): array
    {
        preg_match_all('#/film(\d+)\.html#', $html, $m);
        return array_values(array_unique($m[1] ?? []));
    }

    private function parseRating(string $text): ?float
    {
        $text = str_replace(',', '.', trim($text));
        if (!is_numeric($text)) {
            return null;
        }
        $val = (float) $text;

        return ($val >= 1.0 && $val <= 10.0) ? $val : null;
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }
}
