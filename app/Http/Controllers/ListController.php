<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Models\Account;
use App\Models\Channel;
use App\Models\DownloadLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $cdnToken = $account->token ?? '';

            $folder = General::codeFromString($account->username, $account);
            if (! Storage::disk('local')->exists($folder) ) {
                Storage::disk('local')->makeDirectory($folder);
            }

            $channels = Channel::with('category')
                ->where('is_active', true)
                ->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
                ->orderBy('channel_categories.order', 'asc')
                ->orderBy('channels.order', 'asc')
                ->select('channels.*')
                ->get();

            $ottLines = ['#EXTM3U url-tvg="https://raw.githubusercontent.com/davidmuma/EPG_dobleM/master/guiatv_sincolor.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_US1.xml.gz"'];

            $ottLines[] = '';
            foreach ($channels as $channel) {
                $extinf = '#EXTINF:-1';

                if (!empty($channel->tvg_id)) {
                    $extinf .= ' tvg-id="' . $channel->tvg_id . '"';
                }
                if (!empty($channel->name)) {
                    $extinf .= ' tvg-name="' . $channel->name . '"';
                }
                if ($channel->category && !empty($channel->category->name)) {
                    $extinf .= ' group-title="' . $channel->category->name . '"';
                }
                if (!empty($channel->logo)) {
                    $extinf .= ' tvg-logo="' . $channel->logo . '"';
                }
                if (!empty($channel->catchup)) {
                    $extinf .= ' catchup="' . $channel->catchup . '"';
                }
                if (!empty($channel->catchup_days)) {
                    $extinf .= ' catchup-days="' . $channel->catchup_days . '"';
                }
                if (!empty($channel->catchup_source)) {
                    $extinf .= ' catchup-source="' . $channel->catchup_source . '?device_profile=DASH_TV_WIDEVINE&start_time={start_iso}&end_time={end_iso}"';
                }

                $extinf .= ',' . $channel->name;

                $ottLines[] = $extinf;

                if (!empty($channel->user_agent)) {
                    $ottLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
                }
                if (!empty($channel->manifest_type)) {
                    $ottLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
                }
                if (!empty($channel->license_type)) {
                    $ottLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
                }
                if (!empty($channel->api_key)) {
                    $ottLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $channel->api_key;
                }
                if ($channel->apply_token) {
                    $ottLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
                }
                if (!empty($channel->url_channel)) {
                    $ottLines[] = $channel->url_channel;
                }
                $ottLines[] = '';
            }

            $content = implode("\n", $ottLines);

            $filename = 'total_ott.m3u';
            Storage::disk('local')->put("{$folder}/{$filename}", $content);

            $tivimateLines = ['#EXTM3U url-tvg="https://raw.githubusercontent.com/davidmuma/EPG_dobleM/master/guiatv_sincolor.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_US1.xml.gz"'];
            
            $tivimateLines[] = '';
            foreach ($channels as $channel) {
                $extinf = '#EXTINF:-1';

                if (!empty($channel->tvg_id)) {
                    $extinf .= ' tvg-id="' . $channel->tvg_id . '"';
                }
                if (!empty($channel->name)) {
                    $extinf .= ' tvg-name="' . $channel->name . '"';
                }
                if ($channel->category && !empty($channel->category->name)) {
                    $extinf .= ' group-title="' . $channel->category->name . '"';
                }
                if (!empty($channel->logo)) {
                    $extinf .= ' tvg-logo="' . $channel->logo . '"';
                }
                if (!empty($channel->catchup)) {
                    $extinf .= ' catchup="' . $channel->catchup . '"';
                }
                if (!empty($channel->catchup_days)) {
                    $extinf .= ' catchup-days="' . $channel->catchup_days . '"';
                }
                if (!empty($channel->catchup_source) && $channel->apply_token) {
                    $extinf .= ' catchup-source="' . $channel->catchup_source . '?device_profile=DASH_TV_WIDEVINE&start_time={utc:Y-m-dTH:M:S}Z&end_time={utcend:Y-m-dTH:M:S}Z|X-TCDN-token=' . $cdnToken . '"';
                } else if (!empty($channel->catchup_source)) {
                    $extinf .= ' catchup-source="' . $channel->catchup_source . '?device_profile=DASH_TV_WIDEVINE&start_time={utc:Y-m-dTH:M:S}Z&end_time={utcend:Y-m-dTH:M:S}Z"';
                }

                $extinf .= ',' . $channel->name;

                $tivimateLines[] = $extinf;

                if (!empty($channel->user_agent)) {
                    $tivimateLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
                }
                if (!empty($channel->manifest_type)) {
                    $tivimateLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
                }
                if (!empty($channel->license_type)) {
                    $tivimateLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
                }
                if (!empty($channel->api_key)) {
                    $tivimateLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $channel->api_key;
                }
                if ($channel->apply_token) {
                    $tivimateLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
                }
                if (!empty($channel->url_channel) && $channel->apply_token) {
                    $tivimateLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
                } else if (!empty($channel->url_channel)) {
                    $tivimateLines[] = $channel->url_channel;
                }
                $tivimateLines[] = '';
            }

            $content = implode("\n", $tivimateLines);

            $filename = 'total.m3u';
            Storage::disk('local')->put("{$folder}/{$filename}", $content);
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
