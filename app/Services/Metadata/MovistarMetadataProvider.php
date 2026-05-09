<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Movistar+ provider — last-resort fallback after TMDB / OMDb / TVmaze.
 *
 * Movistar's public buscador (/buscador) returns results only for authenticated
 * sessions, so we instead scrape it through its sitemap: the index at
 * /sitemap/sitemap.xml fans out into per-type files that together list every
 * publicly-indexable ficha URL with its slug + numeric id.
 *
 * Strategy:
 *   1. Download the relevant sub-sitemaps once per 24h and cache them as a
 *      slug→ficha-URL map (≈100k entries, ≈10 MB raw XML).
 *   2. For each search, slugify the title and find the best candidate via
 *      exact / season-suffix / prefix / similar_text matching, biased by the
 *      requested type (movie ↔ /cine/, series ↔ /series/, etc.).
 *   3. Fetch the ficha HTML and parse the schema.org JSON-LD block embedded
 *      in <head>, which exposes title, synopsis, year, cast, director and
 *      aggregate rating in a stable shape.
 */
class MovistarMetadataProvider implements MetadataProvider
{
    private const SITEMAP_INDEX = 'https://www.movistarplus.es/sitemap/sitemap.xml';
    private const CACHE_KEY     = 'movistar:sitemap_index_v1';
    private const CACHE_TTL     = 86400;
    private const USER_AGENT    = 'Mozilla/5.0 (compatible; IptvManagerEnrichBot/1.0)';

    /** Sub-sitemaps that contain content fichas. Excludes paginas/cadenasyprogramas. */
    private const SITEMAP_PATTERNS = ['sitemap_series', 'sitemap_temporadas', 'sitemap_ee_az'];

    /** Path prefix preferences per requested content type, highest priority first. */
    private const TYPE_PREFERENCES = [
        'movie'  => ['/cine/', '/documentales/', '/infantil/', '/entretenimiento/', '/series/'],
        'series' => ['/series/', '/infantil/series/', '/infantil/', '/entretenimiento/', '/documentales/', '/cine/'],
    ];

    /** @var array<string, array{url:string,id:string,path:string}>|null */
    private ?array $index = null;

    public function search(string $title, string $type, ?int $year = null): ?MetadataResult
    {
        $needle = $this->slugify($title);
        if ($needle === '') {
            return null;
        }

        $index = $this->loadIndex();
        if (empty($index)) {
            return null;
        }

        $candidate = $this->findCandidate($index, $needle, $type);
        if ($candidate === null) {
            return null;
        }

        return $this->fetchFicha($candidate['url']);
    }

    public function fetchById(string $externalId, string $type): ?MetadataResult
    {
        // Movistar fichas need both the path and the id to render content; the bare
        // ?id=N route does not redirect. Without a slug we cannot synthesise the URL,
        // so fetchById is intentionally a no-op for this provider.
        return null;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    private function loadIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $this->index = $cached;
        }

        $built = $this->buildIndex();
        if (!empty($built)) {
            Cache::put(self::CACHE_KEY, $built, self::CACHE_TTL);
        }
        return $this->index = $built;
    }

    private function buildIndex(): array
    {
        try {
            $resp = Http::timeout(30)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::SITEMAP_INDEX);
            if (!$resp->ok()) {
                Log::warning('Movistar sitemap index unreachable', ['status' => $resp->status()]);
                return [];
            }
        } catch (\Throwable $e) {
            Log::warning('Movistar sitemap index error', ['error' => $e->getMessage()]);
            return [];
        }

        preg_match_all('#<loc>([^<]+)</loc>#', $resp->body(), $m);
        $sitemaps = array_filter($m[1] ?? [], function (string $url) {
            foreach (self::SITEMAP_PATTERNS as $needle) {
                if (str_contains($url, $needle)) return true;
            }
            return false;
        });

        $index = [];
        foreach ($sitemaps as $sitemapUrl) {
            try {
                $body = Http::timeout(60)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->get($sitemapUrl)
                    ->body();
            } catch (\Throwable $e) {
                Log::warning('Movistar sub-sitemap failed', ['url' => $sitemapUrl, 'error' => $e->getMessage()]);
                continue;
            }

            preg_match_all(
                '#<loc>(https://www\.movistarplus\.es/[^<]+/ficha\?tipo=E&amp;id=\d+)</loc>#',
                $body,
                $entries
            );
            foreach ($entries[1] ?? [] as $rawUrl) {
                $url = html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts = parse_url($url);
                $path  = $parts['path'] ?? '';
                parse_str($parts['query'] ?? '', $q);
                $id = isset($q['id']) ? (string) $q['id'] : '';

                // Path looks like "/<cat>[/sub]/<slug>/ficha". Take the last
                // non-empty segment before "/ficha" as the slug.
                if (!preg_match('#/([^/]+)/ficha$#', $path, $sm)) continue;
                $slug = $sm[1];
                if ($slug === '' || $id === '') continue;

                // Multiple URLs may share the same slug in theory (rare). Prefer the
                // one whose path most closely matches a content category — first wins
                // is fine for our purposes since we re-rank later by type.
                if (!isset($index[$slug])) {
                    $index[$slug] = ['url' => $url, 'id' => $id, 'path' => $path];
                }
            }
        }

        return $index;
    }

    // ── Matching ──────────────────────────────────────────────────────────────

    /** @param array<string, array{url:string,id:string,path:string}> $index */
    private function findCandidate(array $index, string $needle, string $type): ?array
    {
        $preferences = self::TYPE_PREFERENCES[$type] ?? self::TYPE_PREFERENCES['movie'];

        // 1) Exact slug match.
        if (isset($index[$needle])) {
            return $index[$needle];
        }

        // 2) Series often live under "<slug>-t1". For a series query, prefer the
        //    season-1 variant; for a movie query that happens to share a slug
        //    base, this still gives a usable ficha.
        foreach ([1, 2, 3] as $season) {
            $key = $needle . '-t' . $season;
            if (isset($index[$key])) {
                return $index[$key];
            }
        }

        // 3) Slug-prefix / fuzzy scan. Score every entry whose slug shares a
        //    meaningful prefix with the needle, then re-rank by type preference.
        $best = null;
        $bestScore = 0.0;
        foreach ($index as $slug => $entry) {
            $score = $this->slugScore($needle, $slug);
            if ($score < 80.0) continue;

            // Boost by type preference: each preferred prefix adds a small bonus
            // so the same-similarity candidate of the right category wins.
            $boost = 0.0;
            foreach ($preferences as $i => $prefix) {
                if (str_starts_with($entry['path'], $prefix)) {
                    $boost = (count($preferences) - $i) * 0.5;
                    break;
                }
            }
            $effective = $score + $boost;

            if ($effective > $bestScore) {
                $bestScore = $effective;
                $best = $entry;
            }
        }

        return $best;
    }

    private function slugScore(string $needle, string $slug): float
    {
        // Strip trailing season markers like "-t1", "-t12" so "krypto-salva-el-dia"
        // matches "krypto-salva-el-dia-t1" with full similarity.
        $slugBase = preg_replace('/-t\d{1,2}$/', '', $slug);

        if ($slugBase === $needle) return 100.0;
        if (str_starts_with($slugBase, $needle . '-')) return 95.0;
        if (str_starts_with($needle, $slugBase . '-')) return 90.0;

        similar_text($needle, $slugBase, $pct);
        return (float) $pct;
    }

    // ── Ficha parsing ─────────────────────────────────────────────────────────

    private function fetchFicha(string $url): ?MetadataResult
    {
        try {
            $resp = Http::timeout(20)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Movistar ficha fetch error', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
        if (!$resp->ok()) {
            return null;
        }

        $body = $resp->body();

        // Find the content JSON-LD block (Movie / TVSeries / TVEpisode), skipping
        // BreadcrumbList and other unrelated schema.org entries on the page.
        $content = null;
        if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $body, $blocks)) {
            foreach ($blocks[1] as $json) {
                $data = json_decode(trim($json), true);
                if (!is_array($data)) continue;
                $atype = $data['@type'] ?? '';
                if (in_array($atype, ['Movie', 'TVSeries', 'TVEpisode', 'Series', 'CreativeWork'], true)) {
                    $content = $data;
                    break;
                }
            }
        }
        if ($content === null) {
            return null;
        }

        $title    = $content['name'] ?? '';
        $overview = $content['description'] ?? null;

        $year = null;
        if (!empty($content['datePublished'])) {
            $year = (int) substr((string) $content['datePublished'], 0, 4) ?: null;
        }

        $cast = [];
        foreach ($content['actor'] ?? [] as $actor) {
            $name = is_array($actor) ? ($actor['name'] ?? '') : (string) $actor;
            if ($name === '') continue;
            $cast[] = ['name' => $name, 'character' => '', 'profile_url' => null];
            if (count($cast) >= 10) break;
        }

        // Movistar publishes ratings on a 0–5 scale; normalise to the 0–10 scale
        // used by TMDB so the catalogue UI can render them consistently.
        $rating = null;
        $ratingCount = null;
        if (!empty($content['aggregateRating'])) {
            $agg = $content['aggregateRating'];
            if (isset($agg['ratingValue'])) {
                $rating = round((float) $agg['ratingValue'] * 2, 1);
            }
            if (isset($agg['ratingCount'])) {
                $ratingCount = (int) $agg['ratingCount'];
            }
        }

        // Image: prefer the JSON-LD value, fall back to og:image, and strip the
        // tracking query string (?od[]=...) so the cached URL stays clean.
        $poster = $content['image'] ?? null;
        if ($poster === null && preg_match('#<meta property="og:image" content="([^"]+)"#', $body, $og)) {
            $poster = $og[1];
        }
        if (is_string($poster) && ($pos = strpos($poster, '?')) !== false) {
            $poster = substr($poster, 0, $pos);
        }

        $externalId = '';
        if (preg_match('#[?&]id=(\d+)#', $url, $idm)) {
            $externalId = $idm[1];
        }

        if ($title === '' && $overview === null && $poster === null) {
            return null;
        }

        return new MetadataResult(
            provider:        'movistar',
            externalId:      $externalId,
            imdbId:          null,
            title:           (string) $title,
            originalTitle:   null,
            overview:        $overview ? (string) $overview : null,
            releaseYear:     $year,
            runtimeMinutes:  null,
            posterUrl:       is_string($poster) ? $poster : null,
            backdropUrl:     null,
            rating:          $rating,
            ratingCount:     $ratingCount,
            genres:          [],
            cast:            $cast,
            trailerUrl:      null,
        );
    }

    // ── Slug helper ───────────────────────────────────────────────────────────

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        // Replace accented characters with their ASCII counterparts.
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        return trim((string) $value, '-');
    }
}
