<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExportDifusionEpg extends Command
{
    protected $signature = 'export:difusion-epg';
    protected $description = 'Descarga la URL DIFUSION y exporta difusion.csv y cambios.csv (solo campos relevantes).';

    private $difusionUrl;
    private $difusionCsv;
    private $cambiosCsv;

    private $dataFields = [
        'CasId', 'CodCadenaTv', 'Nombre', 'Logo', 'PuntoReproduccion', 'FormatoVideo'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->difusionCsv = storage_path('app/difusion.csv');
        $this->cambiosCsv = storage_path('app/cambios.csv');
        $this->difusionUrl = config('services.difusion_url');
    }

    public function handle()
    {
        $this->info('--- Inicio del proceso: ' . now()->toDateTimeString() . ' ---');

        try {
            $response = Http::timeout(30)->get($this->difusionUrl);
        } catch (\Exception $e) {
            Log::error("ERROR al descargar DIFUSION desde {$this->difusionUrl}: " . $e->getMessage());
            return 1;
        }

        if ($response->failed()) {
            Log::error("ERROR HTTP al descargar DIFUSION: " . $response->status());
            return 1;
        }

        $movistarEPG = $response->json();
        if (!is_array($movistarEPG)) {
            Log::error('ERROR: La estructura del JSON de DIFUSION no es una lista como se esperaba.');
            return 1;
        }

        $csvData = [];

        foreach ($movistarEPG as $grupo) {
            if (!is_array($grupo)) {
                Log::warning('Advertencia: Elemento no es un diccionario en DIFUSION, saltando.');
                continue;
            }

            $logoUrl = '';
            if (!empty($grupo['Logos']) && is_array($grupo['Logos'])) {
                foreach ($grupo['Logos'] as $logoInfo) {
                    if (is_array($logoInfo) && isset($logoInfo['id']) && $logoInfo['id'] === 'nobox_dark') {
                        $logoUrl = isset($logoInfo['uri']) ? $logoInfo['uri'] : '';
                        break;
                    }
                }
            }

            $row = [];
            foreach ($this->dataFields as $field) {
                $row[$field] = isset($grupo[$field]) ? $grupo[$field] : '';
            }
            $row['Logo'] = $logoUrl;
            $row['Nombre'] = is_scalar($row['Nombre']) ? trim(str_replace(["\t", "\n"], [' ', ' '], $row['Nombre'])) : '';

            if (empty($row['CasId'])) {
                Log::warning('Advertencia: Registro sin CasId encontrado en DIFUSION, saltando.');
                continue;
            }

            $csvData[] = $row;
        }

        $existing = $this->loadExistingData($this->difusionCsv);
        $changes = $this->detectChanges($existing, $csvData, 'DIFUSION');
        if (!empty($changes)) {
            $this->writeChanges($changes);
        }

        try {
            $fp = fopen($this->difusionCsv, 'w');
            if ($fp === false) {
                throw new \RuntimeException('No se pudo abrir archivo para escritura: ' . $this->difusionCsv);
            }
            
            fputcsv($fp, $this->dataFields, ',', '"');
            foreach ($csvData as $row) {
                $line = [];
                foreach ($this->dataFields as $f) {
                    $line[] = isset($row[$f]) ? $row[$f] : '';
                }
                fputcsv($fp, $line, ',', '"');
            }
            fclose($fp);
        } catch (\Exception $e) {
            Log::error('Error al escribir difusion.csv: ' . $e->getMessage());
            return 1;
        }

        $this->info('DIFUSION exportado a ' . $this->difusionCsv . '. Cambios detectados: ' . count($changes));
        $this->info('--- Fin del proceso: ' . now()->toDateTimeString() . ' ---');

        return 0;
    }

    private function loadExistingData(string $filepath): array
    {
        if (!file_exists($filepath)) {
            return [];
        }

        $data = [];
        if (($fp = fopen($filepath, 'r')) === false) {
            return [];
        }
        $header = fgetcsv($fp);
        if ($header === false) {
            fclose($fp);
            return [];
        }

        while (($row = fgetcsv($fp)) !== false) {
            $assoc = [];
            foreach ($header as $i => $col) {
                $assoc[$col] = isset($row[$i]) ? $row[$i] : '';
            }
            // Normalize to only dataFields
            $filtered = [];
            foreach ($this->dataFields as $f) {
                $filtered[$f] = isset($assoc[$f]) ? $assoc[$f] : '';
            }
            $data[] = $filtered;
        }

        fclose($fp);
        return $data;
    }

    private function detectChanges(array $oldData, array $newData, string $origen): array
    {
        $changes = [];
        $dateNow = now()->format('d/m/Y');

        $oldDict = [];
        foreach ($oldData as $r) {
            if (!empty($r['CasId'])) $oldDict[$r['CasId']] = $r;
        }
        $newDict = [];
        foreach ($newData as $r) {
            if (!empty($r['CasId'])) $newDict[$r['CasId']] = $r;
        }

        $allKeys = array_unique(array_merge(array_keys($oldDict), array_keys($newDict)));
        sort($allKeys, SORT_STRING);

        foreach ($allKeys as $casid) {
            $oldRow = $oldDict[$casid] ?? null;
            $newRow = $newDict[$casid] ?? null;

            if (!$oldRow && $newRow) {
                $tipo = 'Añadido';
            } elseif ($oldRow && !$newRow) {
                $tipo = 'Eliminado';
            } elseif ($oldRow != $newRow) {
                $tipo = 'Modificado';
            } else {
                continue;
            }

            $record = [
                'Fecha' => $dateNow,
                'Origen' => $origen,
                'Cambio' => $tipo,
                'CasId' => $casid,
            ];

            foreach ($this->dataFields as $field) {
                $oldVal = $oldRow[$field] ?? '';
                $newVal = $newRow[$field] ?? '';
                $antes = 'Antes_' . $field;
                $despues = 'Despues_' . $field;

                if ($tipo === 'Modificado') {
                    if ($oldVal !== $newVal) {
                        $record[$antes] = $oldVal;
                        $record[$despues] = $newVal;
                    } else {
                        $record[$antes] = '';
                        $record[$despues] = '';
                    }
                } elseif ($tipo === 'Añadido') {
                    $record[$antes] = '';
                    $record[$despues] = $newVal;
                } else {
                    $record[$antes] = $oldVal;
                    $record[$despues] = '';
                }
            }

            $changes[] = $record;
        }

        return $changes;
    }

    private function writeChanges(array $changes): void
    {
        if (empty($changes)) return;

        $interleaved = [];
        foreach ($this->dataFields as $f) {
            $interleaved[] = 'Antes_' . $f;
            $interleaved[] = 'Despues_' . $f;
        }
        $fieldnames = array_merge(['Fecha', 'Origen', 'Cambio', 'CasId'], $interleaved);

        $fp = fopen($this->cambiosCsv, 'a');
        if ($fp === false) {
            Log::error('No se pudo abrir cambios.csv para escritura.');
            return;
        }

        // Append rows WITHOUT header, matching existing manual CSV format.
        foreach ($changes as $c) {
            $values = [];
            foreach ($fieldnames as $col) {
                $val = $c[$col] ?? '';
                // Normalize newlines and convert to string
                $val = is_null($val) ? '' : (string)$val;
                // Escape double quotes by doubling them
                $val = str_replace('"', '""', $val);
                $values[] = $val;
            }
            // Build a line with all values quoted exactly like the manual CSV
            $quoted = '"' . implode('","', $values) . '"';
            // Replace escaped sequences to actual quotes for file
            $line = str_replace('"', '"', $quoted);
            // However above produced same; simpler: construct with real double quotes
            $parts = array_map(function($v){ return '"' . str_replace('"', '""', $v) . '"'; }, $values);
            $line = implode(',', $parts) . PHP_EOL;
            // Now replace sequences of backslash-escaped quotes if any
            fwrite($fp, $line);
        }

        fclose($fp);
    }
}
