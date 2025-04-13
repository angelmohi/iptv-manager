<?php

namespace App\Console\Commands;

use App\Models\ChannelCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GetCdnToken extends Command
{
    protected $signature = 'movistar:get-cdn-token';
    protected $description = 'Obtiene el CDN Token de Movistar+ usando las credenciales del .env y lo guarda en un archivo.';

    public function handle()
    {
        $username = env('MOVISTAR_USERNAME');
        $password = env('MOVISTAR_PASSWORD');

        if (!$username || !$password) {
            Log::error('Credenciales no encontradas en el archivo .env');
            return 1;
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
            return 1;
        }

        $accessToken = $loginResponse['access_token'] ?? null;

        if (!$accessToken) {
            Log::error('No se obtuvo access_token.');
            return 1;
        }

        Log::info('Access token obtenido correctamente.');

        Log::info('Obteniendo accountNumber...');
        $accountInfo = Http::withToken($accessToken)->get('https://auth.dof6.com/movistarplus/api/devices/amazon.tv/users/authenticate?_=' . now()->timestamp * 1000);

        if (!$accountInfo->successful()) {
            Log::error('Error obteniendo account info: ' . $accountInfo->body());
            return 1;
        }

        $accountNumber = $accountInfo->json()['ofertas'][0]['accountNumber'] ?? null;
        if (!$accountNumber) {
            Log::error('No se encontró el accountNumber.');
            return 1;
        }

        Log::info("AccountNumber obtenido: {$accountNumber}");

        $deviceId = null;
        if (Storage::disk('local')->exists('device_id.txt')) {
            $deviceId = trim(Storage::disk('local')->get('device_id.txt'));
            Log::info("Intentando usar deviceId existente: {$deviceId}");
        }

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
                return 1;
            }

            $deviceId = trim($deviceResponse->body(), '"');

            Log::info("Nuevo DeviceID generado: {$deviceId}");

            Log::info('Registrando nuevo deviceId...');
            $registerDevice = Http::withToken($accessToken)
                ->post("https://auth.dof6.com/movistarplus/amazon.tv/accounts/{$accountNumber}/devices/{$deviceId}?qspVersion=ssp");

            if (!$registerDevice->successful()) {
                Log::error('Error registrando deviceId: ' . $registerDevice->body());
                return 1;
            }

            Log::info('Device registrado correctamente.');

            Storage::disk('local')->put('device_id.txt', $deviceId);
            Log::info("Nuevo deviceId guardado en: storage/app/device_id.txt");

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
                return 1;
            }

            Log::info('Nuevo device activado correctamente.');
        } else {
            Log::info('Device existente activado correctamente.');
        }

        $accessToken = $initData->json()['accessToken'] ?? null;

        if (!$accessToken) {
            Log::error('No se encontró el accessToken en la respuesta.');
            return 1;
        }

        Log::info('Solicitando CDN Token...');
        $cdnTokenResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->post("https://idserver.dof6.com/{$accountNumber}/devices/amazon.tv/cdn/token/refresh");

        if (!$cdnTokenResponse->successful()) {
            Log::error('Error obteniendo cdnToken: ' . $cdnTokenResponse->body());
            return 1;
        }

        $cdnToken = $cdnTokenResponse->json()['access_token'] ?? null;

        if (!$cdnToken) {
            Log::error('No se encontró el cdnToken en la respuesta.');
            return 1;
        }

        Log::info("\nCDN Token obtenido correctamente:");
        Log::info($cdnToken);

        Storage::disk('local')->put('cdn_token.txt', $cdnToken);
        Log::info("\nToken guardado en: storage/app/cdn_token.txt");

        $matches = [];

        // Reemplazar el token en el archivo total.m3u
        $m3uFilePath = 'total.m3u';
        if (Storage::exists($m3uFilePath)) {
            $m3uContent = Storage::get($m3uFilePath);

            preg_match('/X-TCDN-token=([^\s]+)/', $m3uContent, $matches);
            $oldToken = $matches[1] ?? null;

            if ($oldToken) {
                $oldToken = trim($oldToken, '"');
                Log::info("Token antiguo encontrado: " . $oldToken);

                $escapedOldToken = preg_quote($oldToken, '/');
                $newM3uContent = str_replace($oldToken, $cdnToken, $m3uContent);

                Storage::put($m3uFilePath, $newM3uContent);
                Log::info("Token reemplazado en el archivo {$m3uFilePath}");
            } else {
                Log::error("No se encontró el token antiguo en el archivo {$m3uFilePath}.");
            }
        } else {
            Log::error("El archivo {$m3uFilePath} no se encontró.");
        }

        // Reemplazar el token en el archivo total_ott.m3u
        $m3uOttFilePath = 'total_ott.m3u';
        if (Storage::exists($m3uOttFilePath)) {
            $m3uOttContent = Storage::get($m3uOttFilePath);

            preg_match('/X-TCDN-token=([^\s]+)/', $m3uOttContent, $matches);
            $oldToken = $matches[1] ?? null;

            if ($oldToken) {
                $oldToken = trim($oldToken, '"');
                Log::info("Token antiguo encontrado: " . $oldToken);

                $escapedOldToken = preg_quote($oldToken, '/');
                $newM3uOttContent = str_replace($oldToken, $cdnToken, $m3uOttContent);

                Storage::put($m3uOttFilePath, $newM3uOttContent);
                Log::info("Token reemplazado en el archivo {$m3uOttFilePath}");
            } else {
                Log::error("No se encontró el token antiguo en el archivo {$m3uOttFilePath}.");
            }
        } else {
            Log::error("El archivo {$m3uOttFilePath} no se encontró.");
        }

        Log::info('CDN Token obtenido y reemplazado correctamente en los archivos.');

        return 0;
    }
}
