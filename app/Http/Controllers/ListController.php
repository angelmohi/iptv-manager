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
        $this->middleware('auth')->except(['downloadTivimate', 'downloadOtt', 'downloadCine', 'downloadSeries', 'downloadCineOtt', 'downloadSeriesOtt', 'downloadKodi']);
    }

    /**
     * Update the lists.
     */
    public function update() : JsonResponse
    {
        $accounts = Account::all();
        foreach ($accounts as $account) {
            Lists::generateTivimateList($account);
            Lists::generateOttList($account);
			Lists::generateCineList($account);
			Lists::generateSeriesList($account);
			Lists::generateCineOttList($account);
			Lists::generateSeriesOttList($account);
            Lists::generateKodiList($account);
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

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
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

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
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
	
    /**
     * Download the cine.m3u file.
     */
    public function downloadCine(string $folder): StreamedResponse
    {
        $filePath = 'cine.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
            abort(404, 'El archivo cine.m3u no se encontró.');
        }

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
            'list' => 'OTT',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get("{$folder}/{$filePath}");
        $fileName = 'cine.m3u';
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
     * Download the cineOtt.m3u file.
     */
    public function downloadCineOtt(string $folder): StreamedResponse
    {
        $filePath = 'cineOtt.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
            abort(404, 'El archivo cineOtt.m3u no se encontró.');
        }

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
            'list' => 'OTT',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get("{$folder}/{$filePath}");
        $fileName = 'cineOtt.m3u';
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
     * Download the series.m3u file.
     */
    public function downloadSeries(string $folder): StreamedResponse
    {
        $filePath = 'series.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
            abort(404, 'El archivo series.m3u no se encontró.');
        }

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
            'list' => 'OTT',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get("{$folder}/{$filePath}");
        $fileName = 'series.m3u';
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
     * Download the series.m3u file.
     */
    public function downloadSeriesOtt(string $folder): StreamedResponse
    {
        $filePath = 'seriesOtt.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
            abort(404, 'El archivo seriesOtt.m3u no se encontró.');
        }

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
            'list' => 'OTT',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get("{$folder}/{$filePath}");
        $fileName = 'seriesOtt.m3u';
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
     * Download the kodi.m3u file.
     */
    public function downloadKodi(string $folder): StreamedResponse
    {
        $filePath = 'kodi.m3u';

        if (!Storage::exists("{$folder}/{$filePath}")) {
            abort(404, 'El archivo kodi.m3u no se encontró.');
        }

        $account = Account::where('folder', $folder)->first();

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
            'account_id' => $account->id ?? null,
            'list' => 'Kodi',
            'city' => $locationData['city'],
            'region' => $locationData['region'],
            'country' => $locationData['country'],
            'user_agent' => request()->userAgent(),
        ]);

        $fileContent = Storage::get("{$folder}/{$filePath}");
        $fileName = 'kodi.m3u';
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
