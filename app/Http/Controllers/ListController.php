<?php

namespace App\Http\Controllers;

use App\Models\DownloadLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['downloadTivimate', 'downloadOtt']);
    }

    /**
     * Show the form for editing the lists.
     */
    public function edit() : View
    {
        $filePath = 'total.m3u';
        $totalContent = '';
        if (Storage::exists($filePath)) {
            $totalContent = Storage::get($filePath);
        }

        $fileOttPath = 'total_ott.m3u';
        $totalOttContent = '';
        if (Storage::exists($fileOttPath)) {
            $totalOttContent = Storage::get($fileOttPath);
        }
        return view('lists.edit', compact('totalContent', 'totalOttContent'));
    }

    /**
     * Update the lists.
     */
    public function update() : JsonResponse
    {
        $filePath = '';

        if (request('tivimate_list')) {
            $filePath = 'total.m3u';
            $file = request()->file('tivimate_list');
        } elseif (request('ott_list')) {
            $filePath = 'total_ott.m3u';
            $file = request()->file('ott_list');
        } else {
            flashDangerMessage('No se ha enviado contenido para actualizar.');
            return jsonIframeRedirection("");
        }

        $file->storeAs('', $filePath);

        flashSuccessMessage('Lista actualizada correctamente.');
        return jsonIframeRedirection(route('lists.edit'));
    }

    /**
     * Download the total.m3u file.
     */
    public function downloadTivimate(): StreamedResponse
    {
        $filePath = 'total.m3u';

        if (!Storage::exists($filePath)) {
            abort(404, 'El archivo total.m3u no se encontró.');
        }

        $ip = request()->query('client_ip', request()->ip());
        $locationData = [
            'city' => null,
            'region' => null,
            'country' => null,
        ];

        try {
            $response = Http::timeout(10)
                ->get("https://ipinfo.io/{$ip}/json", [
                    'token' => env('IPINFO_TOKEN'),
                ]);
    
            if ($response->successful()) {
                $data = $response->json();
                $locationData['city']    = $data['city']    ?? null;
                $locationData['region']  = $data['region']  ?? null;
                $locationData['country'] = $data['country'] ?? null;
            } else {
                Log::error('ipinfo.io returned error: ' . $response->body());
            }
    
        } catch (\Exception $e) {
            Log::error('Error al conectar con ipinfo.io: ' . $e->getMessage());
        }
        
        DownloadLog::create([
            'ip' => $ip,
            'list' => 'Tivimate',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get($filePath);
        $fileName = 'total.m3u';
        $mimeType = 'audio/x-mpegurl';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($fileContent) {
            echo $fileContent;
        }, $fileName, $headers);
    }

    /**
     * Download the total_ott.m3u file.
     */
    public function downloadOtt(): StreamedResponse
    {
        $filePath = 'total_ott.m3u';

        if (!Storage::exists($filePath)) {
            abort(404, 'El archivo total_ott.m3u no se encontró.');
        }

        $ip = request()->query('client_ip', request()->ip());
        $locationData = [
            'city' => null,
            'region' => null,
            'country' => null,
        ];

        try {
            $response = Http::timeout(10)
                ->get("https://ipinfo.io/{$ip}/json", [
                    'token' => env('IPINFO_TOKEN'),
                ]);
    
            if ($response->successful()) {
                $data = $response->json();
                $locationData['city']    = $data['city']    ?? null;
                $locationData['region']  = $data['region']  ?? null;
                $locationData['country'] = $data['country'] ?? null;
            } else {
                Log::error('ipinfo.io returned error: ' . $response->body());
            }
    
        } catch (\Exception $e) {
            Log::error('Error al conectar con ipinfo.io: ' . $e->getMessage());
        }

        DownloadLog::create([
            'ip' => $ip,
            'list' => 'OTT',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get($filePath);
        $fileName = 'total_ott.m3u';
        $mimeType = 'audio/x-mpegurl';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->streamDownload(function () use ($fileContent) {
            echo $fileContent;
        }, $fileName, $headers);
    }
}
