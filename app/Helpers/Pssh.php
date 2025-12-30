<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Exception;

class Pssh
{
    /**
     * Obtains the PSSH from an MPD URL using the configured external API.
     *
     * @param string $mpdUrl
     * @param string|null $token
     * @return string|null
     */
    public static function getFromUrl(string $mpdUrl, ?string $token = null, ?bool $isVod = false): ?string
    {
        $apiPsshUrl = config('services.api_pssh_url');

        if (!$apiPsshUrl) {
            return null;
        }

        try {
            $params = [
                'url' => $mpdUrl,
            ];

            if ($token) {
                $params['token'] = $token;
            }

            if ($isVod !== null) {
                $params['vod'] = $isVod ? '1' : '0';
            }

            $response = Http::timeout(30)->get($apiPsshUrl, $params);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();

            return $data['pssh'] ?? null;

        } catch (Exception $e) {
            return null;
        }
    }
}
