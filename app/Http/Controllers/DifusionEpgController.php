<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DifusionEpgController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('difusion-epg.index');
    }

    public function run(Request $request)
    {
        try {
            $exit = Artisan::call('export:difusion-epg');
            $output = Artisan::output();
            $message = $exit === 0 ? 'Se ha actualizado correctamente.' : 'Actualización finalizada con errores.';
            return response()->json(['custom' => ['success' => $exit === 0, 'message' => $message, 'output' => $output]]);
        } catch (\Exception $e) {
            Log::error('Error running export:difusion-epg: '.$e->getMessage());
            return response()->json(['custom' => ['success' => false, 'message' => $e->getMessage()]], 500);
        }
    }

    public function data(Request $request)
    {
        $path = storage_path('app/cambios.csv');
        if (!file_exists($path)) {
            return response()->json(['data' => []]);
        }

        $difMap = [];
        $difPath = storage_path('app/difusion.csv');
        if (file_exists($difPath) && ($df = fopen($difPath, 'r')) !== false) {
            // assume first row is header
            $difHeader = fgetcsv($df);
            while (($dline = fgetcsv($df)) !== false) {
                if (!isset($dline[0])) continue;
                $cas = trim((string)$dline[0]);
                $name = isset($dline[2]) ? $dline[2] : '';
                if ($cas !== '') $difMap[$cas] = $name;
            }
            fclose($df);
        }

        $rows = [];
        $all = [];
        if (($fp = fopen($path, 'r')) !== false) {
            while (($line = fgetcsv($fp)) !== false) {
                // skip completely empty lines
                $empty = true;
                foreach ($line as $c) { if (strlen(trim((string)$c))>0) { $empty = false; break; } }
                if ($empty) continue;
                $all[] = $line;
            }
            fclose($fp);
        }

        if (empty($all)) {
            return response()->json(['data' => []]);
        }

        // Determine if first non-empty row is a header (contains Fecha or Antes_)
        $first = $all[0];
        $hasHeader = false;
        foreach ($first as $cell) {
            if (is_string($cell) && (stripos($cell, 'Fecha') !== false || stripos($cell, 'Antes_') !== false)) {
                $hasHeader = true;
                break;
            }
        }

        // build expected header if file has no header
        $dataFields = ['CasId', 'CodCadenaTv', 'Nombre', 'Logo', 'PuntoReproduccion', 'FormatoVideo'];
        $expected = ['Fecha', 'Origen', 'Cambio', 'CasId'];
        foreach ($dataFields as $f) {
            $expected[] = 'Antes_' . $f;
            $expected[] = 'Despues_' . $f;
        }

        if ($hasHeader) {
            $header = $first;
            $startIndex = 1;
        } else {
            // if counts match, use expected header trimmed/padded to match count
            $countFirst = count($first);
            if ($countFirst <= count($expected)) {
                $header = array_slice($expected, 0, $countFirst);
            } else {
                // file has more columns than expected: create header by taking expected then generic columns
                $header = $expected;
                for ($i = count($expected); $i < $countFirst; $i++) {
                    $header[] = 'col_' . $i;
                }
            }
            $startIndex = 0;
        }

        for ($i = $startIndex; $i < count($all); $i++) {
            $line = $all[$i];
            $item = [];
            foreach ($header as $j => $col) {
                $item[$col] = isset($line[$j]) ? $line[$j] : '';
            }
            // if Nombre is empty, try to fill it from difusion.csv using CasId
            $casKey = isset($item['CasId']) ? trim((string)$item['CasId']) : '';
            if ($casKey !== '' && isset($difMap[$casKey])) {
                $currentName = trim((string)($item['Nombre'] ?? ''));
                if ($currentName === '') {
                    $item['Nombre'] = $difMap[$casKey];
                }
            }
            $rows[] = $item;
        }

        // Return newest changes first
        $rows = array_reverse($rows);

        return response()->json(['data' => $rows]);
    }
}
