<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FetchImdbRatingsCommand extends Command
{
    protected $signature = 'catalogue:imdb
        {--type=all   : Filtrar por tipo: movie, series o all}
        {--force      : Re-obtener incluso si ya tiene nota IMDB guardada}
        {--limit=0    : Límite de registros procesados (0 = sin límite)}
        {--id=        : Procesar un único channel_id}
        {--delay=250  : Milisegundos de pausa entre peticiones a OMDb}';

    protected $description = 'Obtiene la nota y votos IMDB (vía OMDb) para películas y series del catálogo y los guarda en rating_imdb / rating_imdb_count';

    private string $omdbBase;
    private ?string $omdbKey;

    public function handle(): int
    {
        $this->omdbBase = rtrim(config('services.omdb.base_url', 'https://www.omdbapi.com/'), '/') . '/';
        $this->omdbKey  = config('services.omdb.key') ?: null;

        if (!$this->omdbKey) {
            $this->error('Falta OMDB_API_KEY en .env (consigue una gratis en https://www.omdbapi.com/apikey.aspx).');
            return self::INVALID;
        }

        $type    = $this->option('type');
        $force   = (bool) $this->option('force');
        $limit   = (int)  $this->option('limit');
        $delayUs = max(0, (int) $this->option('delay')) * 1000;

        if ($id = $this->option('id')) {
            return $this->processSingle((int) $id, $delayUs);
        }

        if (!in_array($type, ['all', 'movie', 'series'], true)) {
            $this->error('Tipo inválido. Usa: movie, series o all.');
            return self::INVALID;
        }

        $matched = $notFound = $errors = 0;

        if (in_array($type, ['all', 'movie'], true)) {
            [$m, $nf, $e] = $this->processMovies($force, $limit, $delayUs);
            $matched += $m; $notFound += $nf; $errors += $e;
        }

        if (in_array($type, ['all', 'series'], true)) {
            $remaining = $limit > 0 ? max(0, $limit - $matched - $notFound - $errors) : 0;
            [$m, $nf, $e] = $this->processSeries($force, $remaining, $delayUs);
            $matched += $m; $notFound += $nf; $errors += $e;
        }

        $this->newLine();
        $this->info("Total — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");

        return self::SUCCESS;
    }

    // ── Movies ────────────────────────────────────────────────────────────────

    private function processMovies(bool $force, int $limit, int $delayUs): array
    {
        $query = Channel::where('tvg_type', 'movie')
            ->whereHas('metadata', fn ($q) => $q->where('match_status', 'matched'));

        if (!$force) {
            $query->whereHas('metadata', fn ($q) => $q->whereNull('rating_imdb'));
        }

        $count = (clone $query)->count();
        $total = $limit > 0 ? min($count, $limit) : $count;

        if ($total === 0) {
            $this->info('Movies: nada que procesar.');
            return [0, 0, 0];
        }

        $this->info("Procesando {$total} película(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched = $notFound = $errors = $processed = 0;
        $chunk = $limit > 0 ? min(100, $limit) : 100;

        $query->with('metadata')->orderBy('id')->chunkById($chunk, function ($channels) use (
            &$matched, &$notFound, &$errors, &$processed, $bar, $delayUs, $limit
        ) {
            foreach ($channels as $channel) {
                if ($limit > 0 && $processed >= $limit) return false;

                $this->fetchAndSave($channel, $matched, $notFound, $errors);
                $processed++;
                $bar->advance();
                if ($delayUs > 0) usleep($delayUs);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Movies — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Series ────────────────────────────────────────────────────────────────

    private function processSeries(bool $force, int $limit, int $delayUs): array
    {
        $repIds = Channel::select(DB::raw('MIN(id) as id'))
            ->where('tvg_type', 'series')
            ->groupBy('category_id')
            ->pluck('id');

        $query = Channel::whereIn('id', $repIds)
            ->whereHas('metadata', fn ($q) => $q->where('match_status', 'matched'));

        if (!$force) {
            $query->whereHas('metadata', fn ($q) => $q->whereNull('rating_imdb'));
        }

        $count = (clone $query)->count();
        $total = $limit > 0 ? min($count, $limit) : $count;

        if ($total === 0) {
            $this->info('Series: nada que procesar.');
            return [0, 0, 0];
        }

        $this->info("Procesando {$total} serie(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $matched = $notFound = $errors = $processed = 0;
        $chunk = $limit > 0 ? min(100, $limit) : 100;

        $query->with(['metadata', 'category'])->orderBy('id')->chunkById($chunk, function ($channels) use (
            &$matched, &$notFound, &$errors, &$processed, $bar, $delayUs, $limit
        ) {
            foreach ($channels as $channel) {
                if ($limit > 0 && $processed >= $limit) return false;

                $this->fetchAndSave($channel, $matched, $notFound, $errors);
                $processed++;
                $bar->advance();
                if ($delayUs > 0) usleep($delayUs);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Series — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Single ────────────────────────────────────────────────────────────────

    private function processSingle(int $id, int $delayUs): int
    {
        $channel = Channel::with(['metadata', 'category'])->find($id);
        if (!$channel) {
            $this->error("Channel #{$id} no encontrado.");
            return self::FAILURE;
        }

        $matched = $notFound = $errors = 0;
        $this->fetchAndSave($channel, $matched, $notFound, $errors);

        if ($delayUs > 0) usleep($delayUs);
        return self::SUCCESS;
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function fetchAndSave(Channel $channel, int &$matched, int &$notFound, int &$errors): void
    {
        $m = $channel->metadata;
        if (!$m) return;

        $title  = $m->title ?? ($channel->category->name ?? $channel->name);
        $year   = $m->release_year;
        $imdbId = $m->imdb_id ?: null;
        $type   = $channel->tvg_type === 'series' ? 'series' : 'movie';

        $this->line('');
        if ($imdbId) {
            $this->line("  <fg=cyan>IMDB ID:</> {$imdbId} · {$title}");
        } else {
            $this->line("  <fg=gray>Título:</> {$title}" . ($year ? " ({$year})" : ''));
        }

        try {
            // Prefer the exact IMDB id lookup — guarantees we hit the right title.
            $pair = $imdbId
                ? $this->ratingByImdbId($imdbId)
                : $this->ratingByTitle($title, $type, $year);

            if ($pair === null && !$imdbId && $m->original_title && $m->original_title !== $title) {
                $this->line("  <fg=gray>Reintentando con título original:</> {$m->original_title}");
                $pair = $this->ratingByTitle($m->original_title, $type, $year);
            }

            if ($pair !== null) {
                $previous = $m->rating_imdb;
                $m->update([
                    'rating_imdb'       => $pair['rating'],
                    'rating_imdb_count' => $pair['votes'],
                ]);

                if ($previous !== null && abs((float) $previous - $pair['rating']) > 0.05) {
                    $this->line("  <fg=green>✓ Guardado:</> <fg=red>{$previous}</> → <fg=green>{$pair['rating']}</>");
                } elseif ($previous !== null) {
                    $this->line("  <fg=green>✓ Guardado:</> {$pair['rating']} <fg=gray>(sin cambios)</>");
                } else {
                    $this->line("  <fg=green>✓ Guardado:</> {$pair['rating']}");
                }
                $matched++;
            } else {
                $this->line("  <fg=yellow>— Sin nota IMDB en OMDb</>");
                $notFound++;
            }
        } catch (\Throwable $e) {
            $this->warn("  Error en channel {$channel->id}: " . $e->getMessage());
            $errors++;
        }
    }

    // ── OMDb calls ────────────────────────────────────────────────────────────

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function ratingByImdbId(string $imdbId): ?array
    {
        $data = $this->callOmdb(['i' => $imdbId, 'r' => 'json']);

        return $this->extractRatingAndVotes($data);
    }

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function ratingByTitle(string $title, string $type, ?int $year): ?array
    {
        $omdbType = $type === 'series' ? 'series' : 'movie';

        $attempts = [];
        if ($year) {
            $attempts[] = ['t' => $title, 'type' => $omdbType, 'y' => $year];
        }
        $attempts[] = ['t' => $title, 'type' => $omdbType];

        foreach ($attempts as $params) {
            $data = $this->callOmdb($params + ['r' => 'json']);
            $pair = $this->extractRatingAndVotes($data);
            if ($pair !== null) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * @return array{rating: float, votes: ?int}|null
     */
    private function extractRatingAndVotes(?array $data): ?array
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
        if (!$response->ok()) {
            $this->warn("  OMDb HTTP {$response->status()}");
            return null;
        }
        return $response->json();
    }
}
