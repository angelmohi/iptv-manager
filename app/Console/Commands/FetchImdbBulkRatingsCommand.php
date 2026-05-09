<?php

namespace App\Console\Commands;

use App\Models\ChannelMetadata;
use App\Services\Metadata\TitleNormalizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FetchImdbBulkRatingsCommand extends Command
{
    private const RATINGS_URL = 'https://datasets.imdbws.com/title.ratings.tsv.gz';

    private const BASICS_URL = 'https://datasets.imdbws.com/title.basics.tsv.gz';

    private const CACHE_TTL_HOURS = 24;

    protected $signature = 'catalogue:imdb-bulk
        {--type=all   : Filtrar por tipo: movie, series o all}
        {--force      : Actualizar aunque ya tenga rating_imdb}
        {--refresh    : Forzar re-descarga del dataset de IMDb}
        {--limit=0    : Límite de imdb_ids a procesar (0 = sin límite)}
        {--id=        : Procesar un único channel_id}';

    protected $description = 'Notas IMDB solo con datasets oficiales (ratings + basics por título si falta imdb_id)';

    public function handle(): int
    {
        $type    = $this->option('type');
        $force   = (bool) $this->option('force');
        $refresh = (bool) $this->option('refresh');
        $limit   = (int)  $this->option('limit');
        $id      = $this->option('id');

        if (!in_array($type, ['all', 'movie', 'series'], true)) {
            $this->error('Tipo inválido. Usa: movie, series o all.');
            return self::INVALID;
        }

        $gzRatings = storage_path('app/imdb/title.ratings.tsv.gz');
        $gzBasics  = storage_path('app/imdb/title.basics.tsv.gz');

        // ── Fase 1: dataset ratings por imdb_id ───────────────────────────────

        $bulkQuery = ChannelMetadata::query()
            ->where('match_status', 'matched')
            ->whereNotNull('imdb_id');

        $this->applyCatalogueFilters($bulkQuery, $type, $force, $id);

        $imdbIds = $bulkQuery->pluck('imdb_id')->unique()->values()->all();

        if ($limit > 0 && count($imdbIds) > $limit) {
            $imdbIds = array_slice($imdbIds, 0, $limit);
        }

        $titleLookupQuery = ChannelMetadata::query()
            ->where('match_status', 'matched')
            ->whereNull('imdb_id');
        $this->applyCatalogueFilters($titleLookupQuery, $type, $force, $id);
        $needsBasics  = (clone $titleLookupQuery)->exists();
        $needsRatings = $imdbIds !== [] || $needsBasics;

        if ($needsRatings) {
            try {
                $this->ensureGzCached(self::RATINGS_URL, $gzRatings, $refresh);
            } catch (\Throwable $e) {
                $this->error('Error preparando title.ratings: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        if ($imdbIds !== []) {
            $this->info(count($imdbIds) . ' imdb_ids únicos a buscar en title.ratings...');

            $needed  = array_flip($imdbIds);
            $ratings = $this->streamRatings($gzRatings, $needed);

            $found   = count($ratings);
            $missing = count($imdbIds) - $found;
            $this->info("Encontrados {$found} ratings · {$missing} sin nota en el dataset");

            if ($found > 0) {
                $touched = $this->bulkUpdateByImdbId($ratings);
                $this->info("Filas channel_metadata actualizadas (imdb_id): {$touched}");
            }
        } else {
            $this->line('<fg=gray>Fase ratings por imdb_id: nada pendiente con los filtros actuales.</>');
        }

        // ── Fase 2: sin imdb_id → title.basics + title.ratings ────────────────

        if (!$needsBasics) {
            $this->line('<fg=gray>Fase por título: ninguna fila sin imdb_id pendiente.</>');
            return self::SUCCESS;
        }

        try {
            $this->ensureGzCached(self::BASICS_URL, $gzBasics, $refresh);
        } catch (\Throwable $e) {
            $this->error('Error preparando title.basics: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->resolveRatingsByBasicsTitle($gzBasics, $gzRatings, $titleLookupQuery);

        return self::SUCCESS;
    }

    private function applyCatalogueFilters(Builder $query, string $type, bool $force, ?string $id): void
    {
        if ($id) {
            $query->where('channel_id', (int) $id);
        }
        if (!$force) {
            $query->whereNull('rating_imdb');
        }
        if ($type !== 'all') {
            $tvgType = $type === 'series' ? 'series' : 'movie';
            $query->whereHas('channel', fn ($q) => $q->where('tvg_type', $tvgType));
        }
    }

    /**
     * Cruza title.basics con los títulos del catálogo y asigna rating (e imdb_id) desde title.ratings.
     */
    private function resolveRatingsByBasicsTitle(string $gzBasics, string $gzRatings, Builder $titleLookupQuery): void
    {
        $titleLookupQuery->with(['channel.category']);
        $rows = $titleLookupQuery->orderBy('id')->get();

        if ($rows->isEmpty()) {
            $this->line('<fg=gray>Fase por título: ninguna fila sin imdb_id pendiente.</>');
            return;
        }

        $pending = [];
        $index   = [];

        foreach ($rows as $meta) {
            $channel = $meta->channel;
            if (!$channel) {
                continue;
            }

            $kind = $channel->tvg_type === 'series' ? 'series' : 'movie';
            $raw  = $meta->title ?: (($channel->category?->name) ?? $channel->name);

            $parsed = TitleNormalizer::parse($raw);
            $year   = $meta->release_year ?? $parsed['year'];

            $norms = array_values(array_unique(array_filter([
                $this->normalizeTitleKey($parsed['title']),
                $this->normalizeTitleKey($meta->title ?? ''),
                $this->normalizeTitleKey($meta->original_title ?? ''),
            ], fn ($n) => $n !== '')));

            if ($norms === []) {
                continue;
            }

            $idx = count($pending);
            $pending[] = [
                'meta_id'    => $meta->id,
                'year'       => $year,
                'kind'       => $kind,
                'candidates' => [],
            ];

            foreach ($norms as $n) {
                $index[$n][] = $idx;
            }
        }

        if ($pending === []) {
            $this->warn('Fase por título: no hay títulos normalizados válidos en las filas pendientes.');
            return;
        }

        $this->info('Escaneando title.basics.tsv.gz (una pasada, puede tardar varios minutos)...');
        $this->scanBasicsForCandidates($gzBasics, $pending, $index);

        $tconstSet = [];
        foreach ($pending as $slot) {
            foreach (array_keys($slot['candidates']) as $tc) {
                $tconstSet[$tc] = true;
            }
        }

        if ($tconstSet === []) {
            $this->info('Por título: sin coincidencias en title.basics.');
            return;
        }

        $this->info('Leyendo title.ratings para ' . count($tconstSet) . ' tconst candidatos...');
        $ratingsVotes = $this->streamRatingsForTconsts($gzRatings, $tconstSet);

        $updates = [];
        foreach ($pending as $slot) {
            $tconst = $this->pickTconstCandidate($slot['candidates'], $ratingsVotes);
            if ($tconst === null) {
                continue;
            }
            $rv = $ratingsVotes[$tconst] ?? null;
            $updates[$slot['meta_id']] = [
                'imdb_id'            => $tconst,
                'rating_imdb'        => $rv['rating'] ?? null,
                'rating_imdb_count'  => $rv['votes'] ?? null,
            ];
        }

        $touched = $this->bulkUpdateMetadataById($updates);
        $this->info("Filas actualizadas (por título en dataset): {$touched}");
    }

    /**
     * @param list<array{meta_id: int, year: ?int, kind: string, candidates: array<string, true>}> $pending
     * @param array<string, list<int>> $index normalized title => pending indices
     */
    private function scanBasicsForCandidates(string $gzBasics, array &$pending, array $index): void
    {
        $handle = gzopen($gzBasics, 'rb');
        if (!$handle) {
            throw new \RuntimeException("No se pudo abrir {$gzBasics}");
        }

        gzgets($handle); // header

        $lines = 0;
        try {
            while (($line = gzgets($handle)) !== false) {
                $lines++;
                if ($lines % 500000 === 0) {
                    $this->line("  … {$lines} filas basics", 'fg=gray');
                }

                $parts = explode("\t", trim($line, "\r\n"));
                if (count($parts) < 8) {
                    continue;
                }

                [$tconst, $titleType, $primaryTitle, $originalTitle, $isAdult, $startYear] = $parts;

                if ($isAdult === '1') {
                    continue;
                }

                $np = $this->normalizeTitleKey($primaryTitle);
                $no = $this->normalizeTitleKey($originalTitle);

                $hitIdx = [];
                if ($np !== '' && isset($index[$np])) {
                    foreach ($index[$np] as $i) {
                        $hitIdx[$i] = true;
                    }
                }
                if ($no !== '' && $no !== $np && isset($index[$no])) {
                    foreach ($index[$no] as $i) {
                        $hitIdx[$i] = true;
                    }
                }

                if ($hitIdx === []) {
                    continue;
                }

                $startY = $this->parseImdbYearField($startYear);

                foreach (array_keys($hitIdx) as $idx) {
                    if (!$this->basicsTitleTypeMatches($titleType, $pending[$idx]['kind'])) {
                        continue;
                    }

                    $wantYear = $pending[$idx]['year'];
                    if ($wantYear !== null) {
                        if ($startY === null || $startY !== $wantYear) {
                            continue;
                        }
                    }

                    $pending[$idx]['candidates'][$tconst] = true;
                }
            }
        } finally {
            gzclose($handle);
        }
    }

    /**
     * @param array<string, true> $candidates
     * @param array<string, array{rating: float, votes: int}> $ratingsVotes
     */
    private function pickTconstCandidate(array $candidates, array $ratingsVotes): ?string
    {
        if ($candidates === []) {
            return null;
        }

        $keys = array_keys($candidates);
        if (count($keys) === 1) {
            return $keys[0];
        }

        $best       = null;
        $bestVotes  = -1;
        $bestRating = -1.0;

        foreach ($keys as $tc) {
            $rv = $ratingsVotes[$tc] ?? ['votes' => 0, 'rating' => 0.0];
            $v  = $rv['votes'];
            $r  = $rv['rating'];
            if ($v > $bestVotes || ($v === $bestVotes && $r > $bestRating)) {
                $bestVotes  = $v;
                $bestRating = $r;
                $best       = $tc;
            }
        }

        return $best;
    }

    /**
     * @param array<string, bool> $needed tconst => true
     * @return array<string, array{rating: float, votes: int}>
     */
    private function streamRatingsForTconsts(string $gzRatings, array $needed): array
    {
        $found  = [];
        $target = count($needed);

        $handle = gzopen($gzRatings, 'rb');
        if (!$handle) {
            throw new \RuntimeException("No se pudo abrir {$gzRatings}");
        }

        gzgets($handle);

        try {
            while (($line = gzgets($handle)) !== false && count($found) < $target) {
                $parts = explode("\t", trim($line, "\r\n"));
                if (count($parts) < 3) {
                    continue;
                }

                $tconst = $parts[0];
                if (!isset($needed[$tconst])) {
                    continue;
                }

                $votes = (int) preg_replace('/\D/', '', $parts[2]);
                $found[$tconst] = [
                    'rating' => round((float) $parts[1], 1),
                    'votes'  => $votes,
                ];
            }
        } finally {
            gzclose($handle);
        }

        return $found;
    }

    /**
     * @param array<int, array{imdb_id: string, rating_imdb: ?float, rating_imdb_count: ?int}> $updates
     */
    private function bulkUpdateMetadataById(array $updates): int
    {
        $total = 0;
        foreach (array_chunk($updates, 200, true) as $chunk) {
            $imdbCases       = [];
            $ratingCases     = [];
            $countCases      = [];
            $imdbBindings    = [];
            $ratingBindings  = [];
            $countBindings   = [];
            $ids             = [];

            foreach ($chunk as $metaId => $fields) {
                $ids[] = (int) $metaId;

                $imdbCases[] = 'WHEN ? THEN ?';
                $imdbBindings[] = $metaId;
                $imdbBindings[] = $fields['imdb_id'];

                $ratingCases[] = 'WHEN ? THEN ?';
                $ratingBindings[] = $metaId;
                $ratingBindings[] = $fields['rating_imdb'];

                $countCases[] = 'WHEN ? THEN ?';
                $countBindings[] = $metaId;
                $countBindings[] = $fields['rating_imdb_count'];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql = 'UPDATE channel_metadata SET '
                . 'imdb_id = CASE id ' . implode(' ', $imdbCases) . ' END, '
                . 'rating_imdb = CASE id ' . implode(' ', $ratingCases) . ' END, '
                . 'rating_imdb_count = CASE id ' . implode(' ', $countCases) . ' END, '
                . 'updated_at = NOW() '
                . 'WHERE id IN (' . $placeholders . ')';

            $total += DB::update($sql, array_merge(
                $imdbBindings,
                $ratingBindings,
                $countBindings,
                $ids
            ));
        }

        return $total;
    }

    private function normalizeTitleKey(string $title): string
    {
        $t = mb_strtolower(trim($title));
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t);

        return trim($t);
    }

    private function parseImdbYearField(string $field): ?int
    {
        if ($field === '' || $field === '\\N') {
            return null;
        }

        return (int) $field;
    }

    private function basicsTitleTypeMatches(string $titleType, string $kind): bool
    {
        if ($kind === 'series') {
            return in_array($titleType, ['tvSeries', 'tvMiniSeries'], true);
        }

        return in_array($titleType, ['movie', 'tvMovie'], true);
    }

    // ── Dataset cache ─────────────────────────────────────────────────────────

    private function ensureGzCached(string $url, string $gzPath, bool $forceRefresh): void
    {
        $dir = dirname($gzPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear {$dir}");
        }

        $exists = file_exists($gzPath);
        $stale  = $exists && (time() - filemtime($gzPath)) > self::CACHE_TTL_HOURS * 3600;

        if ($exists && !$stale && !$forceRefresh) {
            $ageH   = round((time() - filemtime($gzPath)) / 3600, 1);
            $sizeMB = round(filesize($gzPath) / 1024 / 1024, 1);
            $base   = basename($gzPath);
            $this->line("<fg=gray>{$base} cacheado: {$sizeMB} MB · {$ageH}h (TTL " . self::CACHE_TTL_HOURS . 'h)</>');

            return;
        }

        $this->info('Descargando ' . $url . '...');
        $tmp = $gzPath . '.tmp';

        $response = Http::timeout(600)->withOptions(['sink' => $tmp])->get($url);

        if (!$response->ok()) {
            @unlink($tmp);
            throw new \RuntimeException("HTTP {$response->status()} al descargar {$url}");
        }

        rename($tmp, $gzPath);
        $sizeMB = round(filesize($gzPath) / 1024 / 1024, 1);
        $this->info('Descargado ' . basename($gzPath) . ": {$sizeMB} MB");
    }

    // ── Streaming lookup (imdb_id conocido) ───────────────────────────────────

    /**
     * @param array<string, int> $needed imdb_id => 1
     * @return array<string, array{rating: float, votes: int}>
     */
    private function streamRatings(string $gzPath, array $needed): array
    {
        $found  = [];
        $target = count($needed);

        $handle = gzopen($gzPath, 'rb');
        if (!$handle) {
            throw new \RuntimeException("No se pudo abrir {$gzPath}");
        }

        gzgets($handle);

        $bar = $this->output->createProgressBar($target);
        $bar->start();

        try {
            while (($line = gzgets($handle)) !== false) {
                $parts = explode("\t", trim($line, "\r\n"));
                if (count($parts) < 3) {
                    continue;
                }

                $tconst = $parts[0];
                if (!isset($needed[$tconst])) {
                    continue;
                }

                $votes = (int) preg_replace('/\D/', '', $parts[2]);
                $found[$tconst] = [
                    'rating' => round((float) $parts[1], 1),
                    'votes'  => $votes,
                ];
                $bar->advance();

                if (count($found) >= $target) {
                    break;
                }
            }
        } finally {
            gzclose($handle);
        }

        $bar->finish();
        $this->newLine();

        return $found;
    }

    /**
     * @param array<string, array{rating: float, votes: int}> $ratings
     */
    private function bulkUpdateByImdbId(array $ratings): int
    {
        $totalAffected = 0;
        $chunks        = array_chunk($ratings, 500, true);

        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $ratingCases    = [];
            $countCases     = [];
            $ratingBindings = [];
            $countBindings  = [];
            $ids            = array_keys($chunk);

            foreach ($chunk as $imdbId => $row) {
                $ratingCases[] = 'WHEN ? THEN ?';
                $ratingBindings[] = $imdbId;
                $ratingBindings[] = $row['rating'];

                $countCases[] = 'WHEN ? THEN ?';
                $countBindings[] = $imdbId;
                $countBindings[] = $row['votes'];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql          = 'UPDATE channel_metadata
                    SET rating_imdb = CASE imdb_id ' . implode(' ', $ratingCases) . ' END,
                        rating_imdb_count = CASE imdb_id ' . implode(' ', $countCases) . ' END,
                        updated_at  = NOW()
                    WHERE imdb_id IN (' . $placeholders . ')';

            $totalAffected += DB::update($sql, array_merge($ratingBindings, $countBindings, $ids));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $totalAffected;
    }
}
