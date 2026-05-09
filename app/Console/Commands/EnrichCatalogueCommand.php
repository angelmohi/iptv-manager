<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ChannelMetadata;
use App\Services\Metadata\CompositeMetadataProvider;
use App\Services\Metadata\MetadataProvider;
use App\Services\Metadata\MetadataResult;
use App\Services\Metadata\MovistarMetadataProvider;
use App\Services\Metadata\OmdbMetadataProvider;
use App\Services\Metadata\TitleNormalizer;
use App\Services\Metadata\TmdbMetadataProvider;
use App\Services\Metadata\TvmazeMetadataProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class EnrichCatalogueCommand extends Command
{
    /**
     * In-memory cache of provider lookups for the lifetime of this command run.
     * IPTV catalogues frequently repeat titles across quality variants ("Avatar 4K",
     * "Avatar HD", "Avatar"), so deduplicating saves thousands of HTTP calls.
     *
     * Key:   "{type}|{lowercased title}|{year-or-empty}"
     * Value: ?MetadataResult
     */
    private array $searchCache = [];

    /** @var array<string, int> Per-provider match counter for the final breakdown. */
    private array $providerStats = [];

    /**
     * Cache of IMDb rating + vote lookups keyed by imdb_id (or "title|year") so duplicate
     * channels (Avatar 4K, Avatar HD…) don't burn the OMDb daily quota twice.
     *
     * @var array<string, array{rating: float, votes: ?int}|null>
     */
    private array $imdbRatingCache = [];

    private ?string $omdbKey  = null;
    private string  $omdbBase = '';
    private bool    $skipImdb = false;

    protected $signature = 'catalogue:enrich
        {--type=all : Filter by type: movie, series or all}
        {--force : Re-enrich items that already have metadata}
        {--retry-not-found : Re-enrich only items previously marked as not_found (uses fallback providers)}
        {--skip-tmdb : Skip TMDB in the search chain. Useful with --retry-not-found}
        {--skip-omdb : Skip OMDb in the search chain. Combinable with --skip-tmdb}
        {--skip-tvmaze : Skip TVmaze in the search chain}
        {--skip-movistar : Skip Movistar+ in the search chain}
        {--skip-imdb : Skip the post-enrichment IMDb rating fetch via OMDb}
        {--limit=0 : Limit the number of items processed (0 = no limit)}
        {--id= : Enrich a single channel by id}
        {--tmdb-id= : Skip search and fetch directly by TMDB id (requires --id)}';

    protected $description = 'Fetch metadata (poster, rating, synopsis, cast) via TMDB → OMDb → TVmaze → Movistar+; después guarda rating_imdb + rating_imdb_count vía OMDb cuando hay API key';

    public function handle(MetadataProvider $provider): int
    {
        $type           = $this->option('type');
        $force          = (bool) $this->option('force');
        $retryNotFound  = (bool) $this->option('retry-not-found');
        $limit          = (int) $this->option('limit');
        $id             = $this->option('id');

        if ($force && $retryNotFound) {
            $this->error('--force and --retry-not-found are mutually exclusive.');
            return self::INVALID;
        }

        $this->skipImdb = (bool) $this->option('skip-imdb');
        $this->omdbBase = rtrim(config('services.omdb.base_url', 'https://www.omdbapi.com/'), '/') . '/';
        $this->omdbKey  = config('services.omdb.key') ?: null;

        if (!$this->skipImdb && !$this->omdbKey) {
            $this->line('<fg=yellow>⚠ OMDB_API_KEY no configurada — rating_imdb se omitirá</>');
        }

        // Each --skip-* flag removes its provider from the search chain so we
        // don't waste lookups on ones we know will miss. --tmdb-id keeps working
        // under --skip-tmdb because that branch resolves TmdbMetadataProvider
        // directly, bypassing the chain.
        $registry = [
            'tmdb'     => ['label' => 'TMDB',      'class' => TmdbMetadataProvider::class],
            'omdb'     => ['label' => 'OMDb',      'class' => OmdbMetadataProvider::class],
            'tvmaze'   => ['label' => 'TVmaze',    'class' => TvmazeMetadataProvider::class],
            'movistar' => ['label' => 'Movistar+', 'class' => MovistarMetadataProvider::class],
        ];
        $skipped = array_filter(array_keys($registry), fn ($k) => (bool) $this->option('skip-' . $k));

        if (!empty($skipped)) {
            if (count($skipped) === count($registry)) {
                $this->error('No quedan proveedores activos: revisa los flags --skip-*.');
                return self::INVALID;
            }
            $chain  = [];
            $labels = [];
            foreach ($registry as $key => $meta) {
                if (in_array($key, $skipped, true)) continue;
                $chain[]  = app($meta['class']);
                $labels[] = $meta['label'];
            }
            $provider = new CompositeMetadataProvider($chain);
            $skippedLabels = array_map(fn ($k) => $registry[$k]['label'], $skipped);
            $this->line('<fg=yellow>⚠ ' . implode(' y ', $skippedLabels) . ' desactivado — usando ' . implode(', ', $labels) . '</>');
        }

        if ($id) {
            return $this->enrichSingleChannel((int) $id, $provider);
        }

        $matched  = 0;
        $notFound = 0;
        $errors   = 0;

        if (in_array($type, ['all', 'movie'], true)) {
            [$m, $nf, $e] = $this->enrichMovies($provider, $force, $retryNotFound, $limit);
            $matched  += $m;
            $notFound += $nf;
            $errors   += $e;
        }

        if (in_array($type, ['all', 'series'], true)) {
            // For --limit with all, pass remaining budget
            $remaining = $limit > 0 ? max(0, $limit - $matched - $notFound - $errors) : 0;
            [$m, $nf, $e] = $this->enrichSeries($provider, $force, $retryNotFound, $remaining);
            $matched  += $m;
            $notFound += $nf;
            $errors   += $e;
        }

        if (!in_array($type, ['all', 'movie', 'series'], true)) {
            $this->error('Invalid --type. Use: movie, series or all.');
            return self::INVALID;
        }

        $this->newLine();
        $this->info("Total — Matched: {$matched} · Not found: {$notFound} · Errors: {$errors}");

        if (!empty($this->providerStats)) {
            $parts = [];
            foreach ($this->providerStats as $name => $count) {
                $parts[] = "{$name}: {$count}";
            }
            $this->line('<fg=gray>Desglose por proveedor — ' . implode(' · ', $parts) . '</>');
        }

        return self::SUCCESS;
    }

    // ── Movies ────────────────────────────────────────────────────────────────

    private function enrichMovies(MetadataProvider $provider, bool $force, bool $retryNotFound, int $limit): array
    {
        $query = Channel::where('tvg_type', 'movie');
        if ($retryNotFound) {
            // Re-process only the items that previously couldn't be matched against any
            // provider, so the fallback chain (OMDb / TVmaze) gets a chance on them.
            $query->whereHas('metadata', fn ($q) => $q->where('match_status', 'not_found'));
        } elseif (!$force) {
            $query->where(fn ($q) => $q
                ->whereDoesntHave('metadata')
                //->orWhereHas('metadata', fn ($q) => $q->whereNull('imdb_id'))
            );
        }

        $total = (clone $query)->count();
        if ($limit > 0) $total = min($total, $limit);

        if ($total === 0) {
            $this->info('Movies: nothing to enrich.');
            return [0, 0, 0];
        }

        $label = $retryNotFound ? "retrying {$total} unmatched movie(s)" : "Enriching {$total} movie(s)";
        $this->info($label . '...');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched = $notFound = $errors = $processed = 0;
        $delayUs = $this->delayUs();
        $chunkSize = $limit > 0 ? min(100, $limit) : 100;

        $query->orderBy('id')->chunkById($chunkSize, function ($channels) use (
            $provider, &$matched, &$notFound, &$errors, &$processed, $bar, $delayUs, $limit
        ) {
            foreach ($channels as $channel) {
                if ($limit > 0 && $processed >= $limit) return false;

                $this->line('');
                $this->line("  <fg=gray>Original:</> {$channel->name}");

                $parsed = TitleNormalizer::parse($channel->name);
                $this->line("  <fg=cyan>Normalizado:</> {$parsed['title']}" . ($parsed['year'] ? " ({$parsed['year']})" : ''));

                $this->processChannel($channel, $parsed, 'movie', $provider, $matched, $notFound, $errors);
                $processed++;
                $bar->advance();
                if ($delayUs > 0) usleep($delayUs);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Movies — Matched: {$matched} · Not found: {$notFound} · Errors: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Series (one representative channel per category) ──────────────────────

    private function enrichSeries(MetadataProvider $provider, bool $force, bool $retryNotFound, int $limit): array
    {
        // Find all series categories, pick representative channel (MIN id per category)
        $repIds = Channel::select(\Illuminate\Support\Facades\DB::raw('MIN(id) as id'))
            ->where('tvg_type', 'series')
            ->groupBy('category_id')
            ->pluck('id');

        $query = Channel::whereIn('id', $repIds);
        if ($retryNotFound) {
            $query->whereHas('metadata', fn ($q) => $q->where('match_status', 'not_found'));
        } elseif (!$force) {
            $query->where(fn ($q) => $q
                ->whereDoesntHave('metadata')
                //->orWhereHas('metadata', fn ($q) => $q->whereNull('imdb_id'))
            );
        }

        $total = (clone $query)->count();
        if ($limit > 0) $total = min($total, $limit);

        if ($total === 0) {
            $this->info('Series: nothing to enrich.');
            return [0, 0, 0];
        }

        $label = $retryNotFound ? "Retrying {$total} unmatched series" : "Enriching {$total} series";
        $this->info($label . '...');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched = $notFound = $errors = $processed = 0;
        $delayUs = $this->delayUs();
        $chunkSize = $limit > 0 ? min(100, $limit) : 100;

        $query->with('category')->orderBy('id')->chunkById($chunkSize, function ($channels) use (
            $provider, &$matched, &$notFound, &$errors, &$processed, $bar, $delayUs, $limit
        ) {
            foreach ($channels as $channel) {
                if ($limit > 0 && $processed >= $limit) return false;

                // Use the category name (= series title) for TMDB search
                $seriesName = $channel->category->name ?? $channel->name;
                $this->line('');
                $this->line("  <fg=gray>Serie:</> {$seriesName}");

                $parsed = TitleNormalizer::parse($seriesName);
                $this->line("  <fg=cyan>Normalizado:</> {$parsed['title']}" . ($parsed['year'] ? " ({$parsed['year']})" : ''));

                $this->processChannel($channel, $parsed, 'series', $provider, $matched, $notFound, $errors);
                $processed++;
                $bar->advance();
                if ($delayUs > 0) usleep($delayUs);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Series — Matched: {$matched} · Not found: {$notFound} · Errors: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Single channel by --id ─────────────────────────────────────────────────

    private function enrichSingleChannel(int $id, MetadataProvider $provider): int
    {
        $channel = Channel::with('category')->find($id);
        if (!$channel) {
            $this->error("Channel #{$id} not found.");
            return self::FAILURE;
        }

        $isSeries = $channel->tvg_type === 'series';
        $rawName  = $isSeries ? ($channel->category->name ?? $channel->name) : $channel->name;
        $type     = $isSeries ? 'series' : 'movie';

        $this->line("<fg=gray>Name:</> {$rawName}");

        // Manual TMDB id override — useful when the auto-search cannot find the movie
        // because the IPTV catalogue stores a dubbed title different from TMDB's index.
        // Usage: php artisan catalogue:enrich --id=X --tmdb-id=Y
        if ($tmdbId = $this->option('tmdb-id')) {
            $this->line("<fg=cyan>TMDB id manual:</> {$tmdbId}");
            $matched = $notFound = $errors = 0;
            try {
                // Resolve TMDB explicitly: a TMDB id is meaningless against OMDb/TVmaze,
                // and bypassing the chain ensures --tmdb-id keeps working under --skip-tmdb.
                $tmdbProvider = app(TmdbMetadataProvider::class);
                $result = $tmdbProvider->fetchById((string) $tmdbId, $type);
                $parsed = TitleNormalizer::parse($rawName);
                if ($result === null) {
                    $this->line("  <fg=red>✗ TMDB id {$tmdbId} no encontrado</>");
                    $notFound++;
                } else {
                    $this->line("  <fg=green>✓ Encontrado:</> {$result->title} ({$result->releaseYear}) — rating: {$result->rating}");
                    $metaAttrs = array_merge($this->baseMetadataAttributes($result), $this->imdbRatingPayload($result, $type));
                    ChannelMetadata::updateOrCreate(
                        ['channel_id' => $channel->id],
                        $metaAttrs
                    );
                }
            } catch (\Throwable $e) {
                $this->warn("Error: " . $e->getMessage());
            }
            return self::SUCCESS;
        }

        $parsed = TitleNormalizer::parse($rawName);
        $this->line("<fg=cyan>Normalizado:</> {$parsed['title']}" . ($parsed['year'] ? " ({$parsed['year']})" : ''));

        $matched = $notFound = $errors = 0;
        $this->processChannel($channel, $parsed, $type, $provider, $matched, $notFound, $errors);

        return self::SUCCESS;
    }

    // ── Shared processing ─────────────────────────────────────────────────────

    private function processChannel(
        Channel $channel,
        array $parsed,
        string $type,
        MetadataProvider $provider,
        int &$matched,
        int &$notFound,
        int &$errors
    ): void {
        try {
            $result = null;
            if (!empty($parsed['title'])) {
                $cacheKey = $type . '|' . mb_strtolower($parsed['title']) . '|' . ($parsed['year'] ?? '');
                if (array_key_exists($cacheKey, $this->searchCache)) {
                    $result = $this->searchCache[$cacheKey];
                    if ($result !== null) {
                        $this->line("  <fg=gray>↻ Cacheado en esta ejecución</>");
                    }
                } else {
                    $result = $provider->search($parsed['title'], $type, $parsed['year']);
                    $this->searchCache[$cacheKey] = $result;
                }
            }

            if ($result === null) {
                $this->line("  <fg=red>✗ No encontrado</>");
                ChannelMetadata::updateOrCreate(
                    ['channel_id' => $channel->id],
                    [
                        'provider'     => 'none',
                        'external_id'  => '',
                        'title'        => $parsed['title'] ?: $channel->name,
                        'match_status' => 'not_found',
                        'enriched_at'  => now(),
                    ]
                );
                $this->providerStats['none'] = ($this->providerStats['none'] ?? 0) + 1;
                $notFound++;
            } else {
                $this->providerStats[$result->provider] = ($this->providerStats[$result->provider] ?? 0) + 1;
                $this->line("  <fg=green>✓ Encontrado:</> {$result->title} ({$result->releaseYear}) — rating: {$result->rating} <fg=gray>[{$result->provider}]</>");
                $metaAttrs = array_merge($this->baseMetadataAttributes($result), $this->imdbRatingPayload($result, $type));
                ChannelMetadata::updateOrCreate(
                    ['channel_id' => $channel->id],
                    $metaAttrs
                );
                $matched++;
            }
        } catch (Throwable $e) {
            $errors++;
            $this->newLine();
            $this->warn("Error on channel {$channel->id}: " . $e->getMessage());
        }
    }

    private function delayUs(): int
    {
        return max(0, (int) config('services.tmdb.delay_ms', 250)) * 1000;
    }

    // ── Metadata rows ─────────────────────────────────────────────────────────

    /**
     * Campos comunes del proveedor (TMDB/OMDb/…) antes de fusionar notas IMDb.
     *
     * @return array<string, mixed>
     */
    private function baseMetadataAttributes(MetadataResult $result): array
    {
        return [
            'provider'        => $result->provider,
            'external_id'     => $result->externalId,
            'imdb_id'         => $result->imdbId,
            'title'           => $result->title,
            'original_title'  => $result->originalTitle,
            'overview'        => $result->overview,
            'release_year'    => $result->releaseYear,
            'runtime_minutes' => $result->runtimeMinutes,
            'poster_url'      => $result->posterUrl,
            'backdrop_url'    => $result->backdropUrl,
            'rating'          => $result->rating,
            'rating_count'    => $result->ratingCount,
            'genres'          => $result->genres,
            'cast'            => $result->cast,
            'trailer_url'     => $result->trailerUrl,
            'match_status'    => 'matched',
            'enriched_at'     => now(),
        ];
    }

    // ── IMDb rating (misma idea que catalogue:imdb, fusionado al alta de metadata)

    /**
     * Nota y votos IMDb para persistir junto al resto de metadatos (sin segundo UPDATE).
     * Omisión si --skip-imdb o falta OMDB_API_KEY (salvo match directo por proveedor omdb).
     *
     * @return array{rating_imdb?: float, rating_imdb_count?: ?int}
     */
    private function imdbRatingPayload(MetadataResult $result, string $type): array
    {
        if ($this->skipImdb) {
            return [];
        }

        if ($result->provider === 'omdb' && $result->rating !== null) {
            $this->line('  <fg=green>✓ IMDB:</> ' . $result->rating . ($result->ratingCount ? " <fg=gray>({$result->ratingCount} votos)</>" : '') . ' <fg=gray>(reusado del match OMDb)</>');

            return [
                'rating_imdb'       => $result->rating,
                'rating_imdb_count' => $result->ratingCount,
            ];
        }

        if (!$this->omdbKey) {
            return [];
        }

        try {
            $pair = $this->fetchImdbRating($result->imdbId, $result->title, $result->originalTitle, $type, $result->releaseYear);
            if ($pair !== null) {
                $this->line('  <fg=green>✓ IMDB:</> ' . $pair['rating'] . ($pair['votes'] ? " <fg=gray>({$pair['votes']} votos)</>" : ''));

                return [
                    'rating_imdb'       => $pair['rating'],
                    'rating_imdb_count' => $pair['votes'],
                ];
            }
            $this->line('  <fg=gray>— Sin nota IMDB en OMDb</>');
        } catch (Throwable $e) {
            $this->warn('  Error IMDB (OMDb): ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function fetchImdbRating(
        ?string $imdbId,
        string $title,
        ?string $originalTitle,
        string $type,
        ?int $year
    ): ?array {
        $cacheKey = $imdbId ?: ('t|' . mb_strtolower($title) . '|' . ($year ?? ''));
        if (array_key_exists($cacheKey, $this->imdbRatingCache)) {
            return $this->imdbRatingCache[$cacheKey];
        }

        if ($imdbId) {
            $pair = $this->extractImdbRatingAndVotes($this->callOmdb(['i' => $imdbId, 'r' => 'json']));
        } else {
            $pair = $this->omdbRatingAndVotesByTitle($title, $type, $year);
            if (
                $pair === null
                && $originalTitle !== null
                && $originalTitle !== ''
                && mb_strtolower($originalTitle) !== mb_strtolower($title)
            ) {
                $this->line("  <fg=gray>IMDb OMDb:</> reintentando con título original «{$originalTitle}»");
                $pair = $this->omdbRatingAndVotesByTitle($originalTitle, $type, $year);
            }
        }

        return $this->imdbRatingCache[$cacheKey] = $pair;
    }

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function omdbRatingAndVotesByTitle(string $title, string $type, ?int $year): ?array
    {
        $omdbType = $type === 'series' ? 'series' : 'movie';
        $attempts = [];
        if ($year) {
            $attempts[] = ['t' => $title, 'type' => $omdbType, 'y' => $year];
        }
        $attempts[] = ['t' => $title, 'type' => $omdbType];

        foreach ($attempts as $params) {
            $pair = $this->extractImdbRatingAndVotes($this->callOmdb($params + ['r' => 'json']));
            if ($pair !== null) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function extractImdbRatingAndVotes(?array $data): ?array
    {
        if (!$data || ($data['Response'] ?? 'False') !== 'True') {
            return null;
        }
        $value = $data['imdbRating'] ?? null;
        if ($value === null || $value === 'N/A' || $value === '') {
            return null;
        }
        $votes = null;
        $rawVotes = $data['imdbVotes'] ?? null;
        if ($rawVotes !== null && $rawVotes !== 'N/A' && $rawVotes !== '') {
            $votes = (int) str_replace(',', '', (string) $rawVotes);
        }

        return ['rating' => round((float) $value, 1), 'votes' => $votes];
    }

    private function callOmdb(array $params): ?array
    {
        $params['apikey'] = $this->omdbKey;
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        $response = Http::timeout(15)->get($this->omdbBase, $params);
        return $response->ok() ? $response->json() : null;
    }
}
