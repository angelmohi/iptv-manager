<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Metadata\FilmAffinityService;
use App\Services\Metadata\RateLimitException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchFilmAffinityRatingsCommand extends Command
{
    protected $signature = 'catalogue:filmaffinity
        {--type=all          : Filtrar por tipo: movie, series o all}
        {--force             : Re-obtener incluso si ya tiene nota de FilmAffinity (recomendado para corregir notas mal importadas)}
        {--limit=0           : Límite de registros procesados (0 = sin límite)}
        {--id=               : Procesar un único channel_id}
        {--delay=5000        : Milisegundos de pausa base entre peticiones (±40% de jitter aleatorio)}
        {--on-block=         : Comando a ejecutar cuando FilmAffinity bloquea por IP (ej: "nordvpn -d && nordvpn -c")}
        {--reconnect-wait=15 : Segundos a esperar tras --on-block para que la nueva IP se establezca (mín. 5)}
        {--show-suggest      : Muestra la respuesta cruda de FilmAffinity por cada ítem}
        {--debug             : Muestra diagnóstico crudo de una petición de prueba y sale}
        {--debug-title=      : Título a usar en --debug (ej: "El tesoro 2008"); si no se indica usa el primero de la BD}';

    protected $description = 'Obtiene las notas de FilmAffinity para películas y series del catálogo';

    public function handle(FilmAffinityService $fa): int
    {
        $type          = $this->option('type');
        $force         = (bool)   $this->option('force');
        $limit         = (int)    $this->option('limit');
        $delay         = max(0, (int) $this->option('delay')) * 1000; // μs
        $verbose       = (bool)   $this->option('show-suggest');
        $onBlock       = $this->option('on-block') ?: null;
        $reconnectWait = max(5, (int) $this->option('reconnect-wait'));

        if ($this->option('debug')) {
            return $this->runDebug($fa);
        }

        if ($id = $this->option('id')) {
            return $this->processSingle((int) $id, $fa, $verbose);
        }

        if (!in_array($type, ['all', 'movie', 'series'], true)) {
            $this->error('Tipo inválido. Usa: movie, series o all.');
            return self::INVALID;
        }

        $matched = $notFound = $errors = 0;
        $rotation    = 0;
        $doneIds     = []; // IDs already processed this run — never re-visited after IP rotation.

        // Main loop: retries automatically after rotating IP (--on-block).
        // $doneIds grows across iterations so progress is never lost regardless of --force.
        while (true) {
            try {
                if (in_array($type, ['all', 'movie'], true)) {
                    [$m, $nf, $e] = $this->processMovies($fa, $force, $limit, $delay, $verbose, $doneIds);
                    $matched += $m; $notFound += $nf; $errors += $e;
                }

                if (in_array($type, ['all', 'series'], true)) {
                    $remaining = $limit > 0 ? max(0, $limit - $matched - $notFound - $errors) : 0;
                    [$m, $nf, $e] = $this->processSeries($fa, $force, $remaining, $delay, $verbose, $doneIds);
                    $matched += $m; $notFound += $nf; $errors += $e;
                }

                break; // finished without hitting the rate limit

            } catch (RateLimitException) {
                $this->newLine();
                $this->info("Guardados hasta el bloqueo — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");
                $this->line("  <fg=gray>IDs procesados hasta ahora: " . count($doneIds) . "</>");

                if (!$onBlock) {
                    $this->showCaptchaHelp();
                    return self::FAILURE;
                }

                $rotation++;
                $this->rotateIp($onBlock, $reconnectWait, $rotation);
                $fa->resetSession();
                // Loop continues — $doneIds ensures we skip already-processed items.
            }
        }

        $this->newLine();
        $this->info("Total — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");
        if ($rotation > 0) {
            $this->line("  <fg=gray>Rotaciones de IP realizadas: {$rotation}</>");
        }

        return self::SUCCESS;
    }

    // ── Movies ────────────────────────────────────────────────────────────────

    private function processMovies(FilmAffinityService $fa, bool $force, int $limit, int $delay, bool $verbose, array &$doneIds): array
    {
        $query = Channel::where('tvg_type', 'movie')
            ->whereHas('metadata', fn ($q) => $q->where('match_status', 'matched'));

        if (!$force) {
            $query->whereHas('metadata', fn ($q) => $q->whereNull('rating_filmaffinity'));
        }

        if (!empty($doneIds)) {
            $query->whereNotIn('id', $doneIds);
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

        try {
            $query->with('metadata')->orderBy('id')->chunkById($chunk, function ($channels) use (
                $fa, &$matched, &$notFound, &$errors, &$processed, &$doneIds, $bar, $delay, $limit, $verbose
            ) {
                foreach ($channels as $channel) {
                    if ($limit > 0 && $processed >= $limit) return false;

                    $this->searchAndSave($channel, $fa, $matched, $notFound, $errors, $verbose);
                    $doneIds[] = $channel->id;
                    $processed++;
                    $bar->advance();
                    $this->jitterSleep($delay);
                }
            });
        } catch (RateLimitException $e) {
            $bar->finish();
            $this->newLine();
            $this->info("Movies — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");
            throw $e;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Movies — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Series ────────────────────────────────────────────────────────────────

    private function processSeries(FilmAffinityService $fa, bool $force, int $limit, int $delay, bool $verbose, array &$doneIds): array
    {
        $repIds = Channel::select(DB::raw('MIN(id) as id'))
            ->where('tvg_type', 'series')
            ->groupBy('category_id')
            ->pluck('id');

        $query = Channel::whereIn('id', $repIds)
            ->whereHas('metadata', fn ($q) => $q->where('match_status', 'matched'));

        if (!$force) {
            $query->whereHas('metadata', fn ($q) => $q->whereNull('rating_filmaffinity'));
        }

        if (!empty($doneIds)) {
            $query->whereNotIn('id', $doneIds);
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

        try {
            $query->with(['metadata', 'category'])->orderBy('id')->chunkById($chunk, function ($channels) use (
                $fa, &$matched, &$notFound, &$errors, &$processed, &$doneIds, $bar, $delay, $limit, $verbose
            ) {
                foreach ($channels as $channel) {
                    if ($limit > 0 && $processed >= $limit) return false;

                    $this->searchAndSave($channel, $fa, $matched, $notFound, $errors, $verbose);
                    $doneIds[] = $channel->id;
                    $processed++;
                    $bar->advance();
                    $this->jitterSleep($delay);
                }
            });
        } catch (RateLimitException $e) {
            $bar->finish();
            $this->newLine();
            $this->info("Series — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");
            throw $e;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Series — Con nota: {$matched} · Sin nota: {$notFound} · Errores: {$errors}");

        return [$matched, $notFound, $errors];
    }

    // ── Single ────────────────────────────────────────────────────────────────

    private function processSingle(int $id, FilmAffinityService $fa, bool $verbose): int
    {
        $channel = Channel::with(['metadata', 'category'])->find($id);
        if (!$channel) {
            $this->error("Channel #{$id} no encontrado.");
            return self::FAILURE;
        }

        $matched = $notFound = $errors = 0;
        try {
            $this->searchAndSave($channel, $fa, $matched, $notFound, $errors, $verbose);
        } catch (RateLimitException) {
            $this->showCaptchaHelp();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function searchAndSave(
        Channel $channel,
        FilmAffinityService $fa,
        int &$matched,
        int &$notFound,
        int &$errors,
        bool $verbose = false
    ): void {
        $m = $channel->metadata;
        if (!$m) {
            return;
        }

        $title      = $m->title ?? ($channel->category->name ?? $channel->name);
        $year       = $m->release_year;
        $imdbId     = $m->imdb_id ?: null;
        $firstActor = $this->firstActorName($m->cast ?? null);

        // RateLimitException is NOT caught here — it propagates up to abort the run.
        try {
            $this->line('');
            if ($imdbId) {
                $this->line("  <fg=cyan>IMDB ID:</> {$imdbId} · {$title}");
            } else {
                $this->line("  <fg=gray>Título:</> {$title}" . ($year ? " ({$year})" : ''));
            }

            if ($verbose) {
                $this->dumpSuggest($fa, $title, $year);
            }

            $result = $fa->searchWithDetails($title, $year, $firstActor);
            $this->printSearchResult($result);

            $rating = $result['rating'];

            if ($rating === null && $m->original_title && $m->original_title !== $title) {
                $this->line("  <fg=gray>Reintentando con título original:</> {$m->original_title}");
                if ($verbose) {
                    $this->dumpSuggest($fa, $m->original_title, $year);
                }
                $result = $fa->searchWithDetails($m->original_title, $year, $firstActor);
                $this->printSearchResult($result);
                $rating = $result['rating'];
            }

            if ($rating !== null) {
                $previous = $m->rating_filmaffinity;
                $m->update(['rating_filmaffinity' => $rating]);

                if ($previous !== null && abs((float) $previous - $rating) > 0.05) {
                    $this->line("  <fg=green>✓ Guardado:</> <fg=red>{$previous}</> → <fg=green>{$rating}</>");
                } elseif ($previous !== null) {
                    $this->line("  <fg=green>✓ Guardado:</> {$rating} <fg=gray>(sin cambios)</>");
                } else {
                    $this->line("  <fg=green>✓ Guardado:</> {$rating}");
                }
                $matched++;
            } elseif ($m->rating !== null) {
                $m->update(['rating_filmaffinity' => $m->rating]);
                $this->line("  <fg=yellow>— Sin resultado FA · usando nota TMDB:</> {$m->rating} <fg=gray>[copiado a rating_filmaffinity]</>");
                $notFound++;
            } else {
                $this->line("  <fg=yellow>— Sin resultado en FilmAffinity ni nota TMDB</>");
                $notFound++;
            }
        } catch (RateLimitException $e) {
            throw $e; // propagate — no retries, abort the run
        } catch (\Throwable $e) {
            $this->warn("  Error en channel {$channel->id}: " . $e->getMessage());
            $errors++;
        }
    }

    // ── IP rotation ───────────────────────────────────────────────────────────

    private function rotateIp(string $command, int $waitSeconds, int $rotation): void
    {
        $this->newLine();
        $this->warn("  ↻ Rotando IP (rotación #{$rotation}): {$command}");

        $output   = [];
        $exitCode = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            // Run through cmd so && chaining works; capture stderr too.
            exec("cmd /c \"{$command}\" 2>&1", $output, $exitCode);
        } else {
            exec("{$command} 2>&1", $output, $exitCode);
        }

        foreach ($output as $line) {
            if (trim($line) !== '') {
                $this->line("    <fg=gray>{$line}</>");
            }
        }

        if ($exitCode !== 0) {
            $this->warn("  El comando de rotación terminó con código {$exitCode}.");
        }

        $this->line("  <fg=gray>Esperando {$waitSeconds}s para que la nueva IP se establezca…</>");
        sleep($waitSeconds);
    }

    // ── Captcha helper ────────────────────────────────────────────────────────

    private function showCaptchaHelp(): void
    {
        $this->newLine();
        $this->error(' FilmAffinity ha bloqueado la IP. ');
        $this->newLine();
        $this->line('  <fg=white>Opciones:</>');
        $this->line('');
        $this->line('  <fg=yellow>A) Rotación automática con NordVPN</>');
        $this->line('     Vuelve a lanzar el comando con <fg=cyan>--on-block</>:');
        $this->line('     <fg=gray>php artisan catalogue:filmaffinity --force --on-block="nordvpn -d && nordvpn -c" --reconnect-wait=20</>');
        $this->line('');
        $this->line('  <fg=yellow>B) Espera manual</>');
        $this->line('     1. Abre <fg=cyan>https://www.filmaffinity.com</> en el navegador');
        $this->line('     2. Si aparece captcha, resuélvelo');
        $this->line('     3. Espera <fg=yellow>5-10 minutos</> y vuelve a ejecutar el comando');
        $this->newLine();
        $this->line('  <fg=gray>El comando retoma desde donde se quedó (omite los ya guardados).</>');
        $this->newLine();
    }

    // ── Debug ─────────────────────────────────────────────────────────────────

    private function runDebug(FilmAffinityService $fa): int
    {
        // --debug-title="El tesoro 2008"  →  parse title + optional trailing year
        if ($raw = $this->option('debug-title')) {
            $year  = null;
            $title = trim($raw);
            if (preg_match('/^(.*?)\s+(\d{4})$/', $title, $m)) {
                $title = trim($m[1]);
                $year  = (int) $m[2];
            }
        } else {
            $channel = Channel::where('tvg_type', 'movie')
                ->whereHas('metadata', fn ($q) => $q->where('match_status', 'matched'))
                ->with('metadata')
                ->first();

            if (!$channel) {
                $this->error('No hay ninguna película con IMDB ID en la BD para testear.');
                return self::FAILURE;
            }

            $title = $channel->metadata->title ?? $channel->name;
            $year  = $channel->metadata->release_year;
        }

        $this->line("<fg=cyan>Test con:</> {$title}" . ($year ? " ({$year})" : ''));
        $this->newLine();

        $info = $fa->diagnose($title, $year);

        $this->line("<fg=yellow>Cookies FA:</>  " . ($info['cookies'] ?? '—'));
        $this->line("<fg=yellow>Búsqueda:</>    " . ($info['search_query'] ?? '—'));

        $this->newLine();
        $this->line('<fg=gray>── Sondeo FilmAffinity ─────────────────────────────────────────────────────</>');
        foreach ($info['probe'] ?? [] as $key => $probe) {
            $status   = $probe['status'] ?? '—';
            $blocked  = ($probe['blocked']      ?? false) ? ' <fg=red>[BLOQUEADO]</>'       : '';
            $captcha  = ($probe['captcha']       ?? false) ? ' <fg=red>[CAPTCHA]</>'         : '';
            $filmPage = ($probe['is_film_page']  ?? false) ? ' <fg=green>[FICHA DIRECTA]</>' : '';
            $filmIds  = implode(', ', array_slice($probe['film_ids'] ?? [], 0, 5));
            $idsStr   = $filmIds ? " <fg=green>film IDs: {$filmIds}</>" : ' <fg=red>sin film IDs</>';
            $err      = isset($probe['error']) ? " <fg=red>ERROR: {$probe['error']}</>" : '';
            $this->line("  <fg=cyan>{$key}</>  [{$status}]{$blocked}{$captcha}{$filmPage}{$idsStr}{$err}");
            if (isset($probe['body_sample'])) {
                $this->line("    " . $probe['body_sample']);
            }
            $this->newLine();
        }

        $this->line('<fg=gray>── Búsqueda completa ────────────────────────────────────────────────────────</>');
        try {
            $result = $fa->searchWithDetails($title, $year);
            $this->printSearchResult($result);

            if ($result['rating'] === null && !empty($title)) {
                // Try original_title equivalent if available via the channel
                $this->newLine();
                $this->line('<fg=gray>Resultado final:</> <fg=red>sin nota</>');
            } else {
                $this->newLine();
                $rating = $result['rating'];
                $this->line('<fg=gray>Resultado final:</> ' . ($rating !== null ? "<fg=green>{$rating}</>" : '<fg=red>sin nota</>'));
            }
        } catch (RateLimitException) {
            $this->error('FilmAffinity bloqueó la búsqueda durante el debug.');
        }

        return self::SUCCESS;
    }

    // ── Search result display ─────────────────────────────────────────────────

    /**
     * Returns the name of the first actor from a JSON cast field, or null.
     * Accepts a string (raw JSON), an array already decoded, or null.
     *
     * @param string|array<mixed>|null $cast
     */
    private function firstActorName(string|array|null $cast): ?string
    {
        if ($cast === null || $cast === '' || $cast === []) {
            return null;
        }
        if (is_string($cast)) {
            $cast = json_decode($cast, true);
        }
        if (!is_array($cast) || empty($cast)) {
            return null;
        }
        return $cast[0]['name'] ?? null;
    }

    /**
     * Shows whether FA used advsearch.php (fromyear/toyear) or plain search.php.
     */
    private function lineFaSearchMode(array $result): void
    {
        $stype = $result['search_type'] ?? null;
        $y     = $result['release_year_used'] ?? null;

        if ($stype === 'advanced' && $y !== null) {
            $from = $y - 1;
            $to   = $y + 1;
            $this->line("  <fg=gray>→ FilmAffinity:</> <fg=green>búsqueda avanzada</> <fg=gray>(advsearch · años {$from}–{$to} según release_year={$y})</>");
            return;
        }
        if ($stype === 'advanced+basic' && $y !== null) {
            $this->line("  <fg=gray>→ FilmAffinity:</> <fg=yellow>avanzada sin resultados → búsqueda básica</> <fg=gray>(sin filtro de años en FA; desempate con release_year={$y})</>");
            return;
        }
        if ($stype === 'basic' && $y !== null) {
            $this->line("  <fg=gray>→ FilmAffinity:</> búsqueda básica <fg=gray>(sin fromyear/toyear; desempate con release_year={$y})</>");
            return;
        }
        if ($stype === 'basic') {
            $this->line("  <fg=gray>→ FilmAffinity:</> búsqueda básica <fg=gray>(sin año en metadata → sin filtro FA)</>");
        }
    }

    private function printSearchResult(array $result): void
    {
        $source = $result['source'] ?? 'none';

        if ($source === 'direct') {
            $this->lineFaSearchMode($result);
            $url    = $result['film_url'] ?? '—';
            $rating = $result['rating'];
            $note   = $rating !== null ? "<fg=green>{$rating}</>" : '<fg=red>sin nota</>';
            $this->line("  <fg=gray>→ Ficha directa</> {$note} <fg=gray>{$url}</>");
            return;
        }

        if ($source === 'listing') {
            $this->lineFaSearchMode($result);
            $candidates = $result['candidates'] ?? [];
            $chosenId   = $result['film_id'];
            $rating     = $result['rating'];

            foreach (array_slice($candidates, 0, 8) as $c) {
                $chosen     = $c['id'] === $chosenId ? ' <fg=cyan>[elegido]</>' : '';
                $yearStr    = $c['year'] ? " ({$c['year']})" : '';
                $score      = isset($c['score']) ? " <fg=gray>score={$c['score']}</>" : '';
                $actorMatch = !empty($c['actor_match']) ? ' <fg=magenta>[actor✓]</>' : '';
                $this->line("  <fg=gray>  #{$c['id']}</> {$c['title']}{$yearStr}{$score}{$actorMatch}{$chosen}");
            }

            if (count($candidates) > 8) {
                $this->line("  <fg=gray>  … y " . (count($candidates) - 8) . " más</>");
            }

            $note = $rating !== null ? "<fg=green>{$rating}</>" : '<fg=red>sin nota</>';
            $url  = $result['film_url'] ?? '—';
            $this->line("  <fg=gray>→ Ficha elegida</> {$note} <fg=gray>{$url}</>");
            return;
        }

        // source === 'none'
        $this->line("  <fg=gray>→ Sin resultados en FilmAffinity</>");
    }

    // ── Verbose ───────────────────────────────────────────────────────────────

    private function dumpSuggest(FilmAffinityService $fa, string $title, ?int $year = null): void
    {
        $raw      = $fa->rawSuggest($title, $year);
        $status   = $raw['status']  ?? '—';
        $blocked  = ($raw['blocked']     ?? false) ? ' <fg=red>[BLOQUEADO]</>'       : '';
        $filmPage = ($raw['is_film_page'] ?? false) ? ' <fg=green>[FICHA DIRECTA]</>' : '';
        $ids      = implode(', ', array_slice($raw['film_ids'] ?? [], 0, 5));
        $body     = $raw['body'] ?? ($raw['error'] ?? '(vacío)');

        $this->line("  <fg=magenta>[filmaffinity]</> status={$status}{$blocked}{$filmPage}");
        if ($ids) {
            $this->line("  <fg=gray>film IDs encontrados:</> {$ids}");
        }
        $this->line("  <fg=gray>" . mb_substr(preg_replace('/\s+/', ' ', $body), 0, 300) . "</>");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function jitterSleep(int $baseUs): void
    {
        if ($baseUs <= 0) {
            return;
        }
        $variance = (int) ($baseUs * 0.4);
        usleep($baseUs + rand(-$variance, $variance));
    }
}
