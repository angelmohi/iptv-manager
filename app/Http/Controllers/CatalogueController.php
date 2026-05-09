<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\ChannelMetadata;
use App\Services\Metadata\TitleNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    private const PAGE_SIZE = 36;

    /** Per-request memo for the deduped series rep IDs (used by grid, stats, facets). */
    private ?Collection $uniqueSeriesRepIds = null;

    /** Per-request memo for the deduped movie rep IDs (used by grid, stats, facets). */
    private ?Collection $uniqueMovieRepIds = null;

    public function index(): View
    {
        return view('channels.catalogue');
    }

    public function enrich(string $type): JsonResponse
    {
        $exitCode = Artisan::call('catalogue:enrich', ['--type' => $type]);
        $output   = Artisan::output();

        return response()->json([
            'success' => $exitCode === 0,
            'output'  => $output,
        ]);
    }

    /**
     * JSON endpoint feeding the JustWatch-style grid.
     * Series: one card per UNIQUE series (multiple seasons collapse into one card).
     * Movies: one card per channel.
     */
    public function grid(Request $request, string $type): JsonResponse
    {
        if ($type === 'live') {
            return $this->liveData($request);
        }

        if ($type === 'series') {
            return $this->seriesGrid($request);
        }

        return $this->movieGrid($request);
    }

    // ── Movies ────────────────────────────────────────────────────────────────

    private function movieGrid(Request $request): JsonResponse
    {
        $repIds = $this->uniqueMovieRepIds();

        $query = Channel::whereIn('channels.id', $repIds)
            ->with(['category', 'metadata']);

        $this->applyPlatformFilter($query, $request->input('platform'));

        if ($q = trim((string) $request->input('q', ''))) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('channels.name COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereHas('metadata', function ($m) use ($like) {
                      $m->whereRaw('title COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                        ->orWhereRaw('original_title COLLATE utf8mb4_unicode_ci LIKE ?', [$like]);
                  });
            });
        }

        if ($genre = $request->input('genre')) {
            $query->whereHas('metadata', fn ($m) => $m->whereJsonContains('genres', $genre));
        }

        if ($yearFrom = (int) $request->input('year_from')) {
            $query->whereHas('metadata', fn ($m) => $m->where('release_year', '>=', $yearFrom));
        }

        if ($yearTo = (int) $request->input('year_to')) {
            $query->whereHas('metadata', fn ($m) => $m->where('release_year', '<=', $yearTo));
        }

        if (($min = (float) $request->input('min_rating')) > 0) {
            $query->whereHas('metadata', function ($m) use ($min) {
                $m->whereRaw(
                    '(' . self::catalogueRatingSqlUnqualified() . ') >= ?',
                    [$min]
                );
            });
        }

        $query->leftJoin('channel_metadata', 'channel_metadata.channel_id', '=', 'channels.id')
              ->select('channels.*');

        $this->applySort($query, $request->input('sort', 'year_desc'), 'channels.name');

        $page  = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->getQuery()->getCountForPagination();
        $items = $query->forPage($page, self::PAGE_SIZE)->get();

        return response()->json([
            'data'      => $items->map(fn ($ch) => $this->movieCardPayload($ch))->all(),
            'page'      => $page,
            'page_size' => self::PAGE_SIZE,
            'total'     => $total,
            'has_more'  => ($page * self::PAGE_SIZE) < $total,
        ]);
    }

    // ── Series (one card per category) ───────────────────────────────────────

    private function seriesGrid(Request $request): JsonResponse
    {
        // One representative channel per UNIQUE series — multiple seasons of the same show
        // (= multiple categories) collapse into a single card.
        $repIds = $this->uniqueSeriesRepIds();

        $query = Channel::whereIn('channels.id', $repIds)
            ->with(['category', 'metadata'])
            ->join('channel_categories', 'channel_categories.id', '=', 'channels.category_id')
            ->select('channels.*');

        $this->applyPlatformFilter($query, $request->input('platform'));

        if ($q = trim((string) $request->input('q', ''))) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('channel_categories.name COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereHas('metadata', function ($m) use ($like) {
                      $m->whereRaw('title COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                        ->orWhereRaw('original_title COLLATE utf8mb4_unicode_ci LIKE ?', [$like]);
                  });
            });
        }

        if ($genre = $request->input('genre')) {
            $query->whereHas('metadata', fn ($m) => $m->whereJsonContains('genres', $genre));
        }

        if ($yearFrom = (int) $request->input('year_from')) {
            $query->whereHas('metadata', fn ($m) => $m->where('release_year', '>=', $yearFrom));
        }

        if ($yearTo = (int) $request->input('year_to')) {
            $query->whereHas('metadata', fn ($m) => $m->where('release_year', '<=', $yearTo));
        }

        if (($min = (float) $request->input('min_rating')) > 0) {
            $query->whereHas('metadata', function ($m) use ($min) {
                $m->whereRaw(
                    '(' . self::catalogueRatingSqlUnqualified() . ') >= ?',
                    [$min]
                );
            });
        }

        $query->leftJoin('channel_metadata', 'channel_metadata.channel_id', '=', 'channels.id');

        $sortKey = $request->input('sort', 'year_desc');
        $this->applySort($query, $sortKey, 'channel_categories.name');

        $page  = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->getQuery()->getCountForPagination();
        $items = $query->forPage($page, self::PAGE_SIZE)->get();

        return response()->json([
            'data'      => $items->map(fn ($ch) => $this->seriesCardPayload($ch))->all(),
            'page'      => $page,
            'page_size' => self::PAGE_SIZE,
            'total'     => $total,
            'has_more'  => ($page * self::PAGE_SIZE) < $total,
        ]);
    }

    // ── Detail modal ─────────────────────────────────────────────────────────

    public function show(Channel $channel): JsonResponse
    {
        $channel->load(['category', 'metadata']);
        $m = $channel->metadata;

        $payload = [
            'id'        => $channel->id,
            'name'      => $channel->category->name ?? $channel->name,
            'category'  => $channel->category->name ?? null,
            'tvg_type'  => $channel->tvg_type,
            'platform'  => self::getPlatformLabel($channel->url_channel),
            'logo'      => $channel->logo,
            'is_active' => (bool) $channel->is_active,
            'metadata'  => $m ? [
                'title'                => $m->title,
                'original_title'       => $m->original_title,
                'overview'             => $m->overview,
                'release_year'         => $m->release_year,
                'runtime_minutes'      => $m->runtime_minutes,
                'poster_url'           => $m->poster_url,
                'backdrop_url'         => $m->backdrop_url,
                'rating'                => self::catalogueDisplayRating($m),
                'rating_count'          => $m->rating_count,
                'rating_imdb_count'     => $m->rating_imdb_count,
                'rating_display_votes'  => self::catalogueDisplayVotes($m),
                'rating_filmaffinity'   => $m->rating_filmaffinity,
                'genres'               => $m->genres ?? [],
                'cast'                 => $m->cast ?? [],
                'trailer_url'          => $m->trailer_url,
                'imdb_id'              => $m->imdb_id,
                'match_status'         => $m->match_status,
            ] : null,
        ];

        if ($channel->tvg_type === 'series') {
            $catIds = $this->categoryIdsForSeries($channel);
            $payload['episode_count'] = Channel::whereIn('category_id', $catIds)
                ->where('tvg_type', 'series')
                ->where('is_active', true)
                ->count();
            $payload['season_count'] = count($catIds);
        }

        return response()->json($payload);
    }

    // ── Stats & facets ────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        return response()->json([
            'live'   => Channel::where('tvg_type', 'live')->count(),
            'movie'  => $this->uniqueMovieRepIds()->count(),
            'series' => $this->uniqueSeriesRepIds()->count(),
        ]);
    }

    public function facets(string $type): JsonResponse
    {
        if ($type === 'live') {
            $categories = Channel::where('tvg_type', 'live')
                ->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
                ->orderBy('channel_categories.name')
                ->distinct()
                ->pluck('channel_categories.name')
                ->filter(fn ($n) => $n !== null)
                ->values();

            return response()->json(['categories' => $categories]);
        }

        if (!in_array($type, ['movie', 'series'], true)) {
            return response()->json(['genres' => [], 'years' => []]);
        }

        if ($type === 'series') {
            $repIds = $this->uniqueSeriesRepIds();

            $rows = Channel::whereIn('id', $repIds)
                ->whereHas('metadata')
                ->with('metadata:channel_id,genres,release_year')
                ->get();
        } else {
            $movieRepIds = $this->uniqueMovieRepIds();
            $rows = Channel::whereIn('id', $movieRepIds)
                ->whereHas('metadata')
                ->with('metadata:channel_id,genres,release_year')
                ->get();
        }

        $genres = [];
        $years  = [];
        foreach ($rows as $row) {
            foreach ($row->metadata->genres ?? [] as $g) {
                $genres[$g] = true;
            }
            if ($row->metadata->release_year) {
                $years[(int) $row->metadata->release_year] = true;
            }
        }

        $genres = array_keys($genres);
        sort($genres, SORT_STRING | SORT_FLAG_CASE);

        $years = array_keys($years);
        rsort($years);

        return response()->json(['genres' => $genres, 'years' => $years]);
    }

    // ── Live grid ─────────────────────────────────────────────────────────────

    private function liveData(Request $request): JsonResponse
    {
        $query = Channel::with('category')->where('tvg_type', 'live');

        if ($q = trim((string) $request->input('q', ''))) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('channels.name COLLATE utf8mb4_unicode_ci LIKE ?', [$like])
                  ->orWhereHas('category', function ($c) use ($like) {
                      $c->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', [$like]);
                  });
            });
        }

        if ($category = $request->input('category')) {
            $query->whereHas('category', fn ($c) =>
                $c->whereRaw('name COLLATE utf8mb4_unicode_ci = ?', [$category])
            );
        }

        $active = $request->input('active');
        if ($active !== null && $active !== '') {
            $query->where('is_active', (bool) $active);
        }

        $sort = $request->input('sort', 'category_asc');
        if ($sort === 'name_asc') {
            $query->orderBy('channels.name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('channels.name', 'desc');
        } else {
            // category_asc (default): por posición de categoría y luego por posición del canal
            $query->leftJoin('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
                  ->select('channels.*')
                  ->orderBy('channel_categories.order', 'asc')
                  ->orderBy('channels.order', 'asc')
                  ->orderBy('channels.name', 'asc');
        }

        $page  = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->getQuery()->getCountForPagination();
        $items = $query->forPage($page, self::PAGE_SIZE)->get();

        return response()->json([
            'data'      => $items->map(fn ($ch) => [
                'id'        => $ch->id,
                'name'      => $ch->name,
                'category'  => $ch->category->name ?? '—',
                'is_active' => (bool) $ch->is_active,
                'logo'      => $ch->logo,
            ])->all(),
            'page'      => $page,
            'page_size' => self::PAGE_SIZE,
            'total'     => $total,
            'has_more'  => ($page * self::PAGE_SIZE) < $total,
        ]);
    }

    // ── Card payloads ─────────────────────────────────────────────────────────

    private function movieCardPayload(Channel $ch): array
    {
        $m = $ch->metadata;
        return [
            'id'                   => $ch->id,
            'name'                 => $ch->name,
            'category'             => $ch->category->name ?? null,
            'platform'             => self::getPlatformLabel($ch->url_channel),
            'logo'                 => $ch->logo,
            'title'                => $m?->title ?? $ch->name,
            'year'                 => $m?->release_year,
            'rating'               => self::catalogueDisplayRating($m),
            'rating_filmaffinity'  => $m?->rating_filmaffinity,
            'genres'               => $m?->genres ?? [],
            'poster_url'           => $m?->poster_url,
            'has_metadata'         => $m !== null && $m->match_status === 'matched',
        ];
    }

    private function seriesCardPayload(Channel $ch): array
    {
        $m       = $ch->metadata;
        $seriesName = $ch->category->name ?? $ch->name;
        return [
            'id'                   => $ch->id,
            'name'                 => $seriesName,
            'category'             => $seriesName,
            'platform'             => self::getPlatformLabel($ch->url_channel),
            'logo'                 => $ch->logo,
            'title'                => $m?->title ?? $seriesName,
            'year'                 => $m?->release_year,
            'rating'               => self::catalogueDisplayRating($m),
            'rating_filmaffinity'  => $m?->rating_filmaffinity,
            'genres'               => $m?->genres ?? [],
            'poster_url'           => $m?->poster_url,
            'has_metadata'         => $m !== null && $m->match_status === 'matched',
        ];
    }

    /**
     * Effective catalogue score + vote count for UI (detail modal parentheses).
     *
     * @return array{score: float, votes: int}|null
     */
    private static function catalogueRatingBundle(?ChannelMetadata $m): ?array
    {
        if ($m === null) {
            return null;
        }

        if ($m->rating_imdb !== null && (int) ($m->rating_imdb_count ?? 0) > 50) {
            return [
                'score' => (float) $m->rating_imdb,
                'votes' => (int) $m->rating_imdb_count,
            ];
        }

        $count = $m->rating_count;
        if ($count !== null && (int) $count > 50 && $m->rating !== null) {
            return [
                'score' => (float) $m->rating,
                'votes' => (int) $count,
            ];
        }

        return null;
    }

    /** Shown in grid/cards, filters and sort — derived from {@see catalogueRatingBundle()}. */
    private static function catalogueDisplayRating(?ChannelMetadata $m): ?float
    {
        $b = self::catalogueRatingBundle($m);

        return $b['score'] ?? null;
    }

    /** Votes shown next to the star in the detail modal (IMDb votes or TMDB votes). */
    private static function catalogueDisplayVotes(?ChannelMetadata $m): ?int
    {
        $b = self::catalogueRatingBundle($m);

        return $b['votes'] ?? null;
    }

    /** CASE expression scoped to channel_metadata in whereHas() subqueries. */
    private static function catalogueRatingSqlUnqualified(): string
    {
        return 'CASE WHEN rating_imdb IS NOT NULL AND COALESCE(rating_imdb_count, 0) > 50 THEN rating_imdb WHEN rating_count > 50 AND rating IS NOT NULL THEN rating ELSE NULL END';
    }

    /** CASE expression for ORDER BY on an already-joined channel_metadata row. */
    private static function catalogueRatingSqlQualified(): string
    {
        return 'CASE WHEN channel_metadata.rating_imdb IS NOT NULL AND COALESCE(channel_metadata.rating_imdb_count, 0) > 50 THEN channel_metadata.rating_imdb WHEN channel_metadata.rating_count > 50 AND channel_metadata.rating IS NOT NULL THEN channel_metadata.rating ELSE NULL END';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function applySort($query, string $sort, string $titleColumn): void
    {
        switch ($sort) {
            case 'rating_desc':
                $expr = self::catalogueRatingSqlQualified();
                $query->orderByRaw("({$expr}) IS NULL ASC")
                      ->orderByRaw("({$expr}) DESC")
                      ->orderBy($titleColumn);
                break;
            case 'year_desc':
                $query->orderByRaw('channel_metadata.release_year IS NULL, channel_metadata.release_year DESC')
                      ->orderBy($titleColumn);
                break;
            case 'title_asc':
            default:
                $query->orderBy($titleColumn, 'asc');
                break;
        }
    }

    private function applyPlatformFilter($query, $platform): void
    {
        if (!$platform || !array_key_exists($platform, ChannelController::PLATFORM_PATTERNS)) {
            return;
        }

        $patterns = ChannelController::PLATFORM_PATTERNS[$platform];
        if ($patterns === null) {
            $allPatterns = array_merge(
                ...array_filter(array_values(ChannelController::PLATFORM_PATTERNS))
            );
            foreach ($allPatterns as $p) {
                $query->where('channels.url_channel', 'not like', "%{$p}%");
            }
        } else {
            $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('channels.url_channel', 'like', "%{$p}%");
                }
            });
        }
    }

    // ── Diagnóstico temporal ──────────────────────────────────────────────────

    /**
     * Temporal — remove once the series deduplication is verified.
     * Visit /catalogo/debug-series?q=monedas to inspect the dedup result.
     */
    public function debugSeries(Request $request): JsonResponse
    {
        $search = strtolower(trim((string) $request->input('q', '')));

        $rows = DB::table('channels as c')
            ->join('channel_categories as cat', 'cat.id', '=', 'c.category_id')
            ->leftJoin('channel_metadata as m', 'm.channel_id', '=', 'c.id')
            ->where('c.tvg_type', 'series')
            ->where('c.is_active', true)
            ->select([
                'c.category_id',
                'cat.name as cat_name',
                DB::raw('MIN(c.id) as rep_id'),
                DB::raw("MAX(CASE WHEN m.imdb_id IS NOT NULL AND m.imdb_id <> '' THEN m.imdb_id END) as imdb_id"),
                DB::raw("MAX(CASE WHEN m.match_status = 'matched' AND m.external_id IS NOT NULL AND m.external_id <> '' THEN CONCAT(m.provider,':',m.external_id) END) as provider_key"),
                DB::raw("MAX(m.match_status) as match_status"),
            ])
            ->groupBy('c.category_id', 'cat.name')
            ->get();

        $normToCanonical = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeSeriesName($row->cat_name);
            if (!isset($normToCanonical[$norm])) {
                if (!empty($row->imdb_id)) {
                    $normToCanonical[$norm] = 'imdb:' . $row->imdb_id;
                } elseif (!empty($row->provider_key)) {
                    $normToCanonical[$norm] = 'ext:' . $row->provider_key;
                }
            }
        }

        $output = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeSeriesName($row->cat_name);
            if ($search && !str_contains($norm, $search) && !str_contains(strtolower($row->cat_name), $search)) {
                continue;
            }

            if (!empty($row->imdb_id)) {
                $key = 'imdb:' . $row->imdb_id;
            } elseif (!empty($row->provider_key)) {
                $key = 'ext:' . $row->provider_key;
            } elseif (isset($normToCanonical[$norm])) {
                $key = $normToCanonical[$norm] . ' (inherited)';
            } else {
                $key = 'name:' . $norm;
            }

            $output[] = [
                'cat_name'     => $row->cat_name,
                'normalized'   => $norm,
                'rep_id'       => $row->rep_id,
                'imdb_id'      => $row->imdb_id,
                'provider_key' => $row->provider_key,
                'match_status' => $row->match_status,
                'final_key'    => $key,
            ];
        }

        return response()->json($output);
    }

    // ── Movie deduplication ───────────────────────────────────────────────────

    /**
     * Returns one representative channel id per UNIQUE movie.
     *
     * Uses the same two-pass algorithm as uniqueSeriesRepIds():
     *  Pass 1 — map normalized_name → canonical_key (imdb_id or provider:external_id).
     *  Pass 2 — assign every channel its final key and keep MIN(id) per group.
     *
     * Memoized per request — used by movieGrid(), stats() and facets().
     */
    private function uniqueMovieRepIds(): Collection
    {
        if ($this->uniqueMovieRepIds !== null) {
            return $this->uniqueMovieRepIds;
        }

        $rows = DB::table('channels as c')
            ->leftJoin('channel_metadata as m', 'm.channel_id', '=', 'c.id')
            ->where('c.tvg_type', 'movie')
            ->where('c.is_active', true)
            ->select([
                'c.id as channel_id',
                'c.name as channel_name',
                'm.imdb_id',
                DB::raw("CASE WHEN m.match_status = 'matched' AND m.external_id IS NOT NULL AND m.external_id <> '' THEN CONCAT(m.provider,':',m.external_id) END as provider_key"),
            ])
            ->get();

        // Pass 1: normalized_name → first known canonical key.
        $normToCanonical = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeMovieName($row->channel_name);
            if (!isset($normToCanonical[$norm])) {
                if (!empty($row->imdb_id)) {
                    $normToCanonical[$norm] = 'imdb:' . $row->imdb_id;
                } elseif (!empty($row->provider_key)) {
                    $normToCanonical[$norm] = 'ext:' . $row->provider_key;
                }
            }
        }

        // Pass 2: assign final key and keep MIN channel_id per group.
        $groups = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeMovieName($row->channel_name);

            if (!empty($row->imdb_id)) {
                $key = 'imdb:' . $row->imdb_id;
            } elseif (!empty($row->provider_key)) {
                $key = 'ext:' . $row->provider_key;
            } elseif (isset($normToCanonical[$norm])) {
                $key = $normToCanonical[$norm];
            } else {
                $key = 'name:' . $norm;
            }

            if (!isset($groups[$key]) || $row->channel_id < $groups[$key]) {
                $groups[$key] = (int) $row->channel_id;
            }
        }

        return $this->uniqueMovieRepIds = collect(array_values($groups));
    }

    /**
     * Normalizes a movie channel name for deduplication grouping.
     * Strips platform/quality tags in parentheses and extra whitespace.
     */
    private function normalizeMovieName(string $name): string
    {
        // Strip parenthesised / bracketed content (platform, quality, year…)
        $name = preg_replace('/[\(\[\{][^\)\]\}]*[\)\]\}]/u', '', $name);

        // Collapse spaces, lowercase, strip leftover edge punctuation.
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $name = trim($name, " \t-_,;:|");

        return $name;
    }

    // ── Series deduplication ─────────────────────────────────────────────────

    /**
     * Returns one representative channel id per UNIQUE series.
     *
     * The problem with a pure-SQL approach is that a category with an imdb_id
     * and its seasonal twin without one (e.g. "30 Monedas" matched vs
     * "30 Monedas, Season2" not_found) produce different grouping keys and
     * never collapse. We solve it in PHP with a two-pass algorithm:
     *
     * Pass 1 — build a map  normalized_name → canonical_key, where the
     *           canonical key is the first imdb_id (or provider:external_id)
     *           known for that normalized name.
     *
     * Pass 2 — assign every category the canonical key from Pass 1 if one
     *           exists for its normalized name, otherwise use "name:{norm}".
     *           Then group by that final key and pick the MIN rep_id.
     *
     * Memoized per request — used by seriesGrid(), stats() and facets().
     */
    private function uniqueSeriesRepIds(): Collection
    {
        if ($this->uniqueSeriesRepIds !== null) {
            return $this->uniqueSeriesRepIds;
        }

        // Load one row per category: the rep channel id + category name + metadata of rep channel.
        $rows = DB::table('channels as c')
            ->join('channel_categories as cat', 'cat.id', '=', 'c.category_id')
            ->leftJoin('channel_metadata as m', 'm.channel_id', '=', 'c.id')
            ->where('c.tvg_type', 'series')
            ->where('c.is_active', true)
            ->select([
                'c.category_id',
                'cat.name as cat_name',
                DB::raw('MIN(c.id) as rep_id'),
                // Aggregate: if any channel in the category has metadata, use it.
                DB::raw("MAX(CASE WHEN m.imdb_id IS NOT NULL AND m.imdb_id <> '' THEN m.imdb_id END) as imdb_id"),
                DB::raw("MAX(CASE WHEN m.match_status = 'matched' AND m.external_id IS NOT NULL AND m.external_id <> '' THEN CONCAT(m.provider,':',m.external_id) END) as provider_key"),
            ])
            ->groupBy('c.category_id', 'cat.name')
            ->get();

        // Pass 1: for each normalized name, record the first non-null canonical key found.
        // canonical_key is either  "imdb:ttXXX"  or  "ext:provider:extId".
        $normToCanonical = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeSeriesName($row->cat_name);
            if (!isset($normToCanonical[$norm])) {
                if (!empty($row->imdb_id)) {
                    $normToCanonical[$norm] = 'imdb:' . $row->imdb_id;
                } elseif (!empty($row->provider_key)) {
                    $normToCanonical[$norm] = 'ext:' . $row->provider_key;
                }
            }
        }

        // Pass 2: assign the final grouping key to every category, then pick MIN rep_id per group.
        $groups = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeSeriesName($row->cat_name);

            if (!empty($row->imdb_id)) {
                $key = 'imdb:' . $row->imdb_id;
            } elseif (!empty($row->provider_key)) {
                $key = 'ext:' . $row->provider_key;
            } elseif (isset($normToCanonical[$norm])) {
                // This category has no metadata but shares a normalized name with one that does.
                $key = $normToCanonical[$norm];
            } else {
                $key = 'name:' . $norm;
            }

            if (!isset($groups[$key]) || $row->rep_id < $groups[$key]) {
                $groups[$key] = (int) $row->rep_id;
            }
        }

        return $this->uniqueSeriesRepIds = collect(array_values($groups));
    }

    /**
     * Normalizes a category name to a stable key for series grouping.
     *
     * Strips, in order:
     *  1. Anything inside parentheses/brackets — genre tags like "(Terror)",
     *     platform names like "(HBO Max)", quality markers like "(4K)".
     *  2. Season markers in any language with or without surrounding comma/space.
     *  3. Shorthand season codes (T1, S1, …).
     *  4. Leftover punctuation, extra whitespace; lowercased.
     *
     * Must stay aligned with TitleNormalizer::parse() stripping rules.
     */
    private function normalizeSeriesName(string $name): string
    {
        // 1. Strip parenthesised / bracketed content (genre, platform, quality…)
        $name = preg_replace('/[\(\[\{][^\)\]\}]*[\)\]\}]/u', '', $name);

        // 2. Season markers — keyword + number, optional leading comma/space
        $name = preg_replace(
            '/[,\s]?\b(?:Season|Saison|Stagione|Temporada|Sezon|Sezona|Staffel)\s*\d+\b/iu',
            '',
            $name
        );

        // 3. Shorthand: T1, T01, S1, S01 (not SxE episode format)
        $name = preg_replace('/[,\s]?\b(?:T|S)\d{1,2}\b(?!\s*[Ee]\d)/u', '', $name);

        // 4. Collapse spaces, lowercase, then strip any leftover leading/trailing punctuation.
        //    Order matters: trim() first removes spaces, then we strip punctuation that
        //    was left adjacent to the removed season token (e.g. "30 Monedas," → "30 monedas").
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $name = trim($name, " \t-_,;:|");

        return $name;
    }

    /**
     * Resolves all category ids that belong to the same series as the given channel.
     * Uses the same two-pass logic as uniqueSeriesRepIds() so episode/season counts
     * in the detail modal are consistent with what the grid shows.
     *
     * @return array<int>
     */
    private function categoryIdsForSeries(Channel $channel): array
    {
        $m    = $channel->metadata;
        $norm = $this->normalizeSeriesName($channel->category->name ?? $channel->name);

        // Determine the canonical key for this show (same as Pass 1/2 in uniqueSeriesRepIds).
        if ($m && !empty($m->imdb_id)) {
            // Find all categories whose rep channel shares the same imdb_id, OR whose
            // normalized name resolves to this same imdb (pass-2 inheritance).
            $directCatIds = DB::table('channels')
                ->join('channel_metadata', 'channel_metadata.channel_id', '=', 'channels.id')
                ->where('channels.tvg_type', 'series')
                ->where('channels.is_active', true)
                ->where('channel_metadata.imdb_id', $m->imdb_id)
                ->pluck('channels.category_id')
                ->unique();

            // Also include unmatched categories whose normalized name equals ours
            // (the pass-2 inheritance: they would have been folded into this imdb key).
            $nameCatIds = $this->categoryIdsByNormalizedName($norm);

            $catIds = $directCatIds->merge($nameCatIds)->unique()->values()->all();
        } elseif ($m && $m->match_status === 'matched' && !empty($m->external_id)) {
            $directCatIds = DB::table('channels')
                ->join('channel_metadata', 'channel_metadata.channel_id', '=', 'channels.id')
                ->where('channels.tvg_type', 'series')
                ->where('channels.is_active', true)
                ->where('channel_metadata.provider', $m->provider)
                ->where('channel_metadata.external_id', $m->external_id)
                ->pluck('channels.category_id')
                ->unique();

            $nameCatIds = $this->categoryIdsByNormalizedName($norm);
            $catIds     = $directCatIds->merge($nameCatIds)->unique()->values()->all();
        } else {
            $catIds = $this->categoryIdsByNormalizedName($norm)->all();
        }

        return !empty($catIds) ? $catIds : [$channel->category_id];
    }

    /**
     * Returns category ids whose name normalizes to the same key as $norm.
     * Used by categoryIdsForSeries() to include unmatched seasonal twins.
     */
    private function categoryIdsByNormalizedName(string $norm): Collection
    {
        return ChannelCategory::all(['id', 'name'])
            ->filter(fn ($c) => $this->normalizeSeriesName($c->name) === $norm)
            ->pluck('id');
    }

    // ── Platform helpers ─────────────────────────────────────────────────────

    public static function getPlatformLabel(?string $url): string
    {
        if (empty($url)) {
            return 'Movistar Plus+';
        }
        foreach (ChannelController::PLATFORM_PATTERNS as $platform => $patterns) {
            if ($patterns === null) {
                continue;
            }
            foreach ($patterns as $p) {
                if (str_contains($url, $p)) {
                    return $platform;
                }
            }
        }
        return 'Movistar Plus+';
    }
}
