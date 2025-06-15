<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Models\Account;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AccountController extends Controller
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

    /**
     * Display a listing of the channels.
     */
    public function index() : View
    {
        $accounts = Account::all();
        return view('accounts.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new account.
     */
    public function create() : View
    {
        return view('accounts.create');
    }

    /**
     * Show the form for editing the account.
     */
    public function edit(Account $account) : View
    {
        $folder = General::codeFromString($account->username, $account);
        return view('accounts.edit', compact('account', 'folder'));
    }

    /**
     * Store a new account.
     */
    public function store(Request $request) : JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:255|unique:accounts',
            'password' => 'required',
        ]);

        $data['password'] = Crypt::encryptString($data['password']);

        $account = Account::create($data);

        flashSuccessMessage('Cuenta creada correctamente.');
        return jsonIframeRedirection(route('accounts.edit', $account->id));
    }

    /**
     * Generate a new token for the account.
     */
    public function generateToken(Account $account) : JsonResponse
    {
        $username = $account->username;
        $password = Crypt::decryptString($account->password);

        if (!$username || !$password) {
            Log::error('Credenciales no encontradas');
            flashDangerMessage('Credenciales no encontradas.');
            return jsonIframeRedirection("");
        }

        Log::info('Obteniendo access_token...');
        $loginResponse = Http::asForm()->post('https://auth.dof6.com/auth/oauth2/token?deviceClass=amazon.tv', [
            'grant_type' => 'password',
            'deviceClass' => 'amazon.tv',
            'username' => $username,
            'password' => $password,
        ]);

        if (!$loginResponse->successful()) {
            Log::error('Error al hacer login: ' . $loginResponse->body());
            flashDangerMessage('Error al hacer login.');
            return jsonIframeRedirection("");
        }

        $accessToken = $loginResponse['access_token'] ?? null;

        if (!$accessToken) {
            Log::error('No se obtuvo access_token.');
            flashDangerMessage('No se obtuvo access_token.');
            return jsonIframeRedirection("");
        }

        Log::info('Access token obtenido correctamente.');

        Log::info('Obteniendo accountNumber...');
        $accountInfo = Http::withToken($accessToken)->get('https://auth.dof6.com/movistarplus/api/devices/amazon.tv/users/authenticate?_=' . now()->timestamp * 1000);

        if (!$accountInfo->successful()) {
            Log::error('Error obteniendo account info: ' . $accountInfo->body());
            flashDangerMessage('Error obteniendo account info.');
            return jsonIframeRedirection("");
        }

        $accountNumber = $accountInfo->json()['ofertas'][0]['accountNumber'] ?? null;
        if (!$accountNumber) {
            Log::error('No se encontró el accountNumber.');
            flashDangerMessage('No se encontró el accountNumber.');
            return jsonIframeRedirection("");
        }

        Log::info("AccountNumber obtenido: {$accountNumber}");

        $deviceId = $account->device_id ?? null;
        $needsNewDevice = false;

        if ($deviceId) {
            Log::info('Activando device existente (initData)...');
            $initData = Http::withToken($accessToken)
                ->post("https://clientservices.dof6.com/movistarplus/amazon.tv/sdp/mediaPlayers/{$deviceId}/initData?qspVersion=ssp&version=8&status=default", [
                    "accountNumber" => $accountNumber,
                    "userProfile" => "0",
                    "streamMiscellanea" => "HTTPS",
                    "deviceType" => "SMARTTV_OTT",
                    "deviceManufacturerProduct" => "LG",
                    "streamDRM" => "Widevine",
                    "streamFormat" => "DASH",
                ]);

            if (!$initData->successful()) {
                Log::error('DeviceId inválido o expirado, creando uno nuevo...');
                $needsNewDevice = true;
            }
        } else {
            Log::info('No se encontró deviceId guardado. Creando uno nuevo...');
            $needsNewDevice = true;
        }

        if ($needsNewDevice) {
            $deviceResponse = Http::withToken($accessToken)
                ->post("https://auth.dof6.com/movistarplus/amazon.tv/accounts/{$accountNumber}/devices/?qspVersion=ssp");

            if (!$deviceResponse->successful()) {
                Log::error('Error creando deviceId: ' . $deviceResponse->body());
                flashDangerMessage('Error creando deviceId.');
                return jsonIframeRedirection("");
            }

            $deviceId = trim($deviceResponse->body(), '"');

            Log::info("Nuevo DeviceID generado: {$deviceId}");

            Log::info('Registrando nuevo deviceId...');
            $registerDevice = Http::withToken($accessToken)
                ->post("https://auth.dof6.com/movistarplus/amazon.tv/accounts/{$accountNumber}/devices/{$deviceId}?qspVersion=ssp");

            if (!$registerDevice->successful()) {
                Log::error('Error registrando deviceId: ' . $registerDevice->body());
                flashDangerMessage('Error registrando deviceId.');
                return jsonIframeRedirection("");
            }

            Log::info('Device registrado correctamente.');

            // Guardar el nuevo deviceId en el modelo Account
            $account->device_id = $deviceId;
            $account->save();
            Log::info("Nuevo deviceId guardado en la base de datos.");

            $initData = Http::withToken($accessToken)
                ->post("https://clientservices.dof6.com/movistarplus/amazon.tv/sdp/mediaPlayers/{$deviceId}/initData?qspVersion=ssp&version=8&status=default", [
                    "accountNumber" => $accountNumber,
                    "userProfile" => "0",
                    "streamMiscellanea" => "HTTPS",
                    "deviceType" => "SMARTTV_OTT",
                    "deviceManufacturerProduct" => "LG",
                    "streamDRM" => "Widevine",
                    "streamFormat" => "DASH",
                ]);

            if (!$initData->successful()) {
                Log::error('Error activando device: ' . $initData->body());
                flashDangerMessage('Error activando device.');
                return jsonIframeRedirection("");
            }

            Log::info('Nuevo device activado correctamente.');
        } else {
            Log::info('Device existente activado correctamente.');
        }

        $accessToken = $initData->json()['accessToken'] ?? null;

        if (!$accessToken) {
            Log::error('No se encontró el accessToken en la respuesta.');
            flashDangerMessage('No se encontró el accessToken en la respuesta.');
            return jsonIframeRedirection("");
        }

        Log::info('Solicitando CDN Token...');
        $cdnTokenResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->post("https://idserver.dof6.com/{$accountNumber}/devices/amazon.tv/cdn/token/refresh");

        if (!$cdnTokenResponse->successful()) {
            Log::error('Error obteniendo cdnToken: ' . $cdnTokenResponse->body());
            flashDangerMessage('Error obteniendo cdnToken.');
            return jsonIframeRedirection("");
        }

        $cdnToken = $cdnTokenResponse->json()['access_token'] ?? null;

        if (!$cdnToken) {
            Log::error('No se encontró el cdnToken en la respuesta.');
            flashDangerMessage('No se encontró el cdnToken en la respuesta.');
            return jsonIframeRedirection("");
        }

        Log::info("\nCDN Token obtenido correctamente:");
        Log::info($cdnToken);

        $account->token = $cdnToken;
        $account->token_expires_at = now()->addDay();
        $account->save();
        Log::info("CDN Token guardado en la base de datos.");

        // Crear carpeta para el usuario si no existe
        $folder = General::codeFromString($account->username, $account);
        if (! Storage::disk('local')->exists($folder) ) {
            Storage::disk('local')->makeDirectory($folder);
        }
        
        // Generar archivos M3U con los canales
        $channels = Channel::with('category')
            ->where('is_active', true)
            ->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

        // Generar lista para OTT
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

        // Generar lista para Tivimate
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

        flashSuccessMessage('CDN Token obtenido y reemplazado correctamente en los archivos.');
        Log::info('CDN Token obtenido y reemplazado correctamente en los archivos.');
        return jsonIframeRedirection("");
    }
}
