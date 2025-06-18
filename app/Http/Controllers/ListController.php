<?php

namespace App\Http\Controllers;

use App\Helpers\Lists;
use App\Models\Account;
use App\Models\DownloadLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * Update the lists.
     */
    public function update() : JsonResponse
    {
        $accounts = Account::all();
        foreach ($accounts as $account) {
            Lists::generate($account);
        }

        flashSuccessMessage('Listas actualizadas correctamente.');
        return jsonIframeRedirection(route('channels.index'));
    }

    /**
     * Download the total.m3u file.
     */
    public function downloadTivimate(string $folder): StreamedResponse
    {
        $filePath = 'total.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
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

        $fileContent = Storage::get("{$folder}/{$filePath}");
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
    public function downloadOtt(string $folder): StreamedResponse
    {
        $filePath = 'total_ott.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
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

        $fileContent = Storage::get("{$folder}/{$filePath}");
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
