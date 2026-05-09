<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TmdbSearchCommand extends Command
{
    protected $signature = 'tmdb:search
        {title : Title to search for}
        {--year= : Optional year filter}
        {--type=movie : movie or series}';

    protected $description = 'Debug TMDB search for a given title — shows all fallback attempts and raw results';

    public function handle(): int
    {
        $apiKey   = config('services.tmdb.key');
        $baseUrl  = rtrim(config('services.tmdb.base_url', 'https://api.themoviedb.org/3'), '/');
        $language = config('services.tmdb.language', 'es-ES');

        if (!$apiKey) {
            $this->error('TMDB_API_KEY is not configured.');
            return self::FAILURE;
        }

        $title    = $this->argument('title');
        $year     = $this->option('year') ? (int) $this->option('year') : null;
        $type     = $this->option('type');
        $endpoint = $type === 'series' ? '/search/tv' : '/search/movie';
        $yearKey  = $type === 'series' ? 'first_air_date_year' : 'year';

        $stripped        = ltrim($title, '¡¿');
        $bareTitle       = trim(preg_replace('/\s{2,}/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title)));
        $articlePattern  = '/^(?:el|la|los|las|un|una|unos|unas|the|a|an|le|les|lo|gli|der|die|das|ein|eine|o|os|as|um|uma)\s+/iu';
        $articleStripped = preg_replace($articlePattern, '', $bareTitle);

        $attempts = [
            ['label' => 'Con año',               'query' => $title,           'year' => $year, 'lang' => $language],
            ['label' => 'Sin año',               'query' => $title,           'year' => null,  'lang' => $language],
            ['label' => 'Sin ¡/¿',               'query' => $stripped,        'year' => null,  'lang' => $language],
            ['label' => 'Sin puntuación',         'query' => $bareTitle,       'year' => null,  'lang' => $language],
            ['label' => 'Sin puntuación (en-US)', 'query' => $bareTitle,       'year' => null,  'lang' => 'en-US'],
            ['label' => 'Sin artículo',           'query' => $articleStripped, 'year' => null,  'lang' => $language],
        ];

        // Deduplicate (e.g. if title has no ¡ or no year was given)
        $seen = [];
        $attempts = array_filter($attempts, function ($a) use (&$seen) {
            $key = $a['query'] . '|' . ($a['year'] ?? '') . '|' . $a['lang'];
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        });

        foreach ($attempts as $attempt) {
            $params = [
                'api_key'       => $apiKey,
                'language'      => $attempt['lang'],
                'query'         => $attempt['query'],
                'include_adult' => 'false',
            ];
            if ($attempt['year']) {
                $params[$yearKey] = $attempt['year'];
            }

            $label = "<fg=cyan>{$attempt['label']}</>";
            $q     = "<fg=yellow>{$attempt['query']}</>";
            $y     = $attempt['year'] ? " <fg=gray>({$attempt['year']})</>" : '';
            $this->line("─── {$label}: {$q}{$y}");

            $response = Http::timeout(15)->get($baseUrl . $endpoint, $params);

            if (!$response->ok()) {
                $this->line("    <fg=red>HTTP {$response->status()}</>");
                continue;
            }

            $results = $response->json('results') ?? [];

            if (empty($results)) {
                $this->line('    <fg=red>Sin resultados</>');
                continue;
            }

            $this->line("    <fg=green>" . count($results) . " resultado(s)</>");
            foreach (array_slice($results, 0, 5) as $i => $r) {
                $t   = $type === 'series' ? ($r['name'] ?? '?') : ($r['title'] ?? '?');
                $ot  = $type === 'series' ? ($r['original_name'] ?? '') : ($r['original_title'] ?? '');
                $yr  = substr($type === 'series' ? ($r['first_air_date'] ?? '') : ($r['release_date'] ?? ''), 0, 4);
                $id  = $r['id'] ?? '?';
                $pop = number_format((float) ($r['popularity'] ?? 0), 1);
                $this->line("    [{$id}] <fg=white>{$t}</> / {$ot} ({$yr}) pop:{$pop}");
            }

            // Stop at the first attempt that returns results
            break;
        }

        // ── Fuzzy fallback (mirrors TmdbMetadataProvider behaviour) ──────────
        $this->line('─── <fg=cyan>Fuzzy (sin artículo, primeras 2 palabras + similar_text ≥ 85 %)</>');
        $words = array_values(array_filter(explode(' ', $articleStripped)));
        if (count($words) >= 2) {
            $shortQuery = implode(' ', array_slice($words, 0, 2));
            $params = [
                'api_key'       => $apiKey,
                'language'      => $language,
                'query'         => $shortQuery,
                'include_adult' => 'false',
            ];
            $response   = Http::timeout(15)->get($baseUrl . $endpoint, $params);
            $candidates = $response->ok() ? ($response->json('results') ?? []) : [];
            $titleField = $type === 'series' ? 'name' : 'title';
            $searchNorm = mb_strtolower($articleStripped);
            $bestResult = null;
            $bestScore  = 0.0;
            foreach ($candidates as $candidate) {
                $cb = trim(preg_replace('/\s{2,}/u', ' ',
                    preg_replace('/[^\p{L}\p{N}\s]/u', ' ',
                        mb_strtolower($candidate[$titleField] ?? ''))));
                similar_text($searchNorm, $cb, $pct);
                if ($pct > $bestScore) { $bestScore = $pct; $bestResult = $candidate; }
            }
            if ($bestResult && $bestScore >= 85.0) {
                $t   = $bestResult[$titleField] ?? '?';
                $ot  = $type === 'series' ? ($bestResult['original_name'] ?? '') : ($bestResult['original_title'] ?? '');
                $yr  = substr($type === 'series' ? ($bestResult['first_air_date'] ?? '') : ($bestResult['release_date'] ?? ''), 0, 4);
                $id  = $bestResult['id'] ?? '?';
                $pctFmt = number_format($bestScore, 1);
                $this->line("    <fg=green>Match {$pctFmt} %</> [{$id}] <fg=white>{$t}</> / {$ot} ({$yr})");
            } else {
                $pctFmt = number_format($bestScore, 1);
                $this->line("    <fg=red>Sin match</> (mejor: {$pctFmt} %" . ($bestResult ? " — " . ($bestResult[$titleField] ?? '') : '') . ')');
            }
        } else {
            $this->line('    <fg=gray>Título muy corto para fuzzy</>');
        }

        return self::SUCCESS;
    }
}
