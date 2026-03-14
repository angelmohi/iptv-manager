<?php

namespace App\Helpers;

use App\Models\Account;
use App\Models\Channel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Lists
{
    private static function isPremiumUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $patterns = [
            'prod/dash/skymd',
            'prod/dash/applmd',
            'prod/dash/hbomd',
        ];

        return Str::contains($url, $patterns);
    }

    /**
     * Generate tivimate list.
     *
     * @param  Account  $account
     * @return void
     */
	 
    public static function generateTivimateList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'live'],
			]);


        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

        // Generate list for Tivimate
        $tivimateLines = ['#EXTM3U url-tvg="https://raw.githubusercontent.com/davidmuma/EPG_dobleM/master/guiatv_sincolor.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz, https://github.com/HelmerLuzo/PlutoTV_HL/raw/refs/heads/main/epg/es.xml.gz, https://github.com/HelmerLuzo/RakutenTV_HL/raw/refs/heads/main/epg/RakutenTV.xml.gz, https://raw.github.com/matthuisman/i.mjh.nz/master/SamsungTVPlus/es.xml.gz"'];
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
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $tivimateLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
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

    /**
     * Generate OTT list.
     *
     * @param  Account  $account
     * @return void
     */
    public static function generateOttList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'live'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

        // Generate list for OTT
        $ottLines = ['#EXTM3U url-tvg="https://raw.githubusercontent.com/davidmuma/EPG_dobleM/master/guiatv_sincolor.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz, https://github.com/HelmerLuzo/PlutoTV_HL/raw/refs/heads/main/epg/es.xml.gz, https://github.com/HelmerLuzo/RakutenTV_HL/raw/refs/heads/main/epg/RakutenTV.xml.gz, https://raw.github.com/matthuisman/i.mjh.nz/master/SamsungTVPlus/es.xml.gz"'];

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
            if (!empty($channel->api_key) || !empty($channel->catchup_api_key)) {
                $licenseJson = null;

                // If api_key already looks like JSON, use it as-is
                if (!empty($channel->api_key) && is_string($channel->api_key) && trim($channel->api_key)[0] === '{') {
                    $licenseJson = $channel->api_key;
                } else {
                    $merged = [];
                    if (!empty($channel->api_key)) {
                        $parsed = self::parseColonPairs($channel->api_key);
                        if (!empty($parsed)) {
                            $merged = array_merge($merged, $parsed);
                        }
                    }
                    if (!empty($channel->catchup_api_key)) {
                        $parsed = self::parseColonPairs($channel->catchup_api_key);
                        if (!empty($parsed)) {
                            $merged = array_merge($merged, $parsed);
                        }
                    }

                    if (!empty($merged)) {
                        $licenseJson = json_encode($merged, JSON_UNESCAPED_SLASHES);
                    }
                }

                if (!empty($licenseJson)) {
                    $ottLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $licenseJson;
                }
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
    }
	
    public static function generateCineList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'movie'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channels.name', 'asc')   // sigue existiendo, no se toca
			->select('channels.*')
			->get();

		// Reordenar en PHP ignorando ! ? ¡ ¿ en el nombre del canal
		$channels = $channels->sortBy(function ($channel) {
			$name  = $channel->name ?? '';
			// 1) Quitar signos de puntuación que no quieres tener en cuenta
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// 2) Convertir a ASCII para eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// 3) Normalizar a minúsculas
			return mb_strtolower(trim($clean), 'UTF-8');
		})->values();

		// Generate list for Cine
		$cineLines = ['#EXTM3U'];
		$cineLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$cineLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="movie"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $cinePremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $cinePremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $cinePremiumLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $cinePremiumLines[] = $channel->url_channel;
            }
            $cinePremiumLines[] = '';
        }

        $content = implode("\n", $cinePremiumLines);

        $filename = 'cine.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
    public static function generateCinePremiumList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'movie'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channels.name', 'asc')   // sigue existiendo, no se toca
			->select('channels.*')
			->get();

		// Reordenar en PHP ignorando ! ? ¡ ¿ en el nombre del canal
		$channels = $channels->sortBy(function ($channel) {
			$name  = $channel->name ?? '';
			// 1) Quitar signos de puntuación que no quieres tener en cuenta
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// 2) Convertir a ASCII para eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// 3) Normalizar a minúsculas
			return mb_strtolower(trim($clean), 'UTF-8');
		})->values();

		// Generate list for Cine
		$cinePremiumLines = ['#EXTM3U'];
		$cinePremiumLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$cinePremiumLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (!self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="movie"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $cinePremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $cinePremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $cinePremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $cinePremiumLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $cinePremiumLines[] = $channel->url_channel;
            }
            $cinePremiumLines[] = '';
        }

        $content = implode("\n", $cinePremiumLines);

        $filename = 'cinePremium.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
	    public static function generateCineOttList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'movie'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channels.name', 'asc')   // sigue existiendo, no se toca
			->select('channels.*')
			->get();

		// Reordenar en PHP ignorando ! ? ¡ ¿ en el nombre del canal
		$channels = $channels->sortBy(function ($channel) {
			$name  = $channel->name ?? '';
			// 1) Quitar signos de puntuación que no quieres tener en cuenta
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// 2) Convertir a ASCII para eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// 3) Normalizar a minúsculas
			return mb_strtolower(trim($clean), 'UTF-8');
		})->values();

		// Generate list for Cine
		$cineOttLines = ['#EXTM3U'];
		$cineOttLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$cineOttLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="movie"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $cineOttLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $cineOttLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $cineOttLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $cineOttLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $cineOttLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $cineOttLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel)) {
                $cineOttLines[] = $channel->url_channel;
            }
            $cineOttLines[] = '';
        }

        $content = implode("\n", $cineOttLines);

        $filename = 'cineOtt.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
	    public static function generateCineOttPremiumList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'movie'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channels.name', 'asc')   // sigue existiendo, no se toca
			->select('channels.*')
			->get();

		// Reordenar en PHP ignorando ! ? ¡ ¿ en el nombre del canal
		$channels = $channels->sortBy(function ($channel) {
			$name  = $channel->name ?? '';
			// 1) Quitar signos de puntuación que no quieres tener en cuenta
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// 2) Convertir a ASCII para eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// 3) Normalizar a minúsculas
			return mb_strtolower(trim($clean), 'UTF-8');
		})->values();

		// Generate list for Cine
		$cineOttPremiumLines = ['#EXTM3U'];
		$cineOttPremiumLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$cineOttPremiumLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (!self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="movie"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $cineOttPremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $cineOttPremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $cineOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $cineOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $cineOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $cineOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel)) {
                $cineOttPremiumLines[] = $channel->url_channel;
            }
            $cineOttPremiumLines[] = '';
        }

        $content = implode("\n", $cineOttPremiumLines);

        $filename = 'cineOttPremium.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }	
	
    public static function generateSeriesList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'series'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

		$channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channel_categories.order', 'asc') // orden cronológico de categorías
			->orderBy('channels.order', 'asc')           // orden cronológico de capítulos
			->select('channels.*')
			->get();

		// Reordenar SOLO para el export:
		// 1) Agrupar por nombre de categoría (título de serie)
		// 2) Ordenar las categorías por nombre, ignorando ! ? ¡ ¿ y tildes
		// 3) Mantener el orden cronológico interno de los capítulos dentro de cada categoría
		$grouped = $channels->groupBy(function ($channel) {
			return $channel->category->name ?? '';
		});

		$sortedCategoryNames = $grouped->keys()->sortBy(function ($name) {
			// Quitar signos de puntuación que quieras ignorar
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// Eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// Normalizar a minúsculas para comparación
			return mb_strtolower(trim($clean), 'UTF-8');
		});

		$sortedChannels = collect();
		foreach ($sortedCategoryNames as $catName) {
			$sortedChannels = $sortedChannels->merge($grouped[$catName]);
		}

		// Esta es la colección final, ya ordenada como quieres para el export
		$channels = $sortedChannels->values();

		// Generate list for Series
		$seriesLines = ['#EXTM3U'];
		$seriesLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="series"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $seriesPremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesPremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $seriesPremiumLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $seriesPremiumLines[] = $channel->url_channel;
            }
            $seriesPremiumLines[] = '';
        }

        $content = implode("\n", $seriesPremiumLines);

        $filename = 'series.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
    public static function generateSeriesPremiumList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'series'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

		$channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channel_categories.order', 'asc') // orden cronológico de categorías
			->orderBy('channels.order', 'asc')           // orden cronológico de capítulos
			->select('channels.*')
			->get();

		// Reordenar SOLO para el export:
		// 1) Agrupar por nombre de categoría (título de serie)
		// 2) Ordenar las categorías por nombre, ignorando ! ? ¡ ¿ y tildes
		// 3) Mantener el orden cronológico interno de los capítulos dentro de cada categoría
		$grouped = $channels->groupBy(function ($channel) {
			return $channel->category->name ?? '';
		});

		$sortedCategoryNames = $grouped->keys()->sortBy(function ($name) {
			// Quitar signos de puntuación que quieras ignorar
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// Eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// Normalizar a minúsculas para comparación
			return mb_strtolower(trim($clean), 'UTF-8');
		});

		$sortedChannels = collect();
		foreach ($sortedCategoryNames as $catName) {
			$sortedChannels = $sortedChannels->merge($grouped[$catName]);
		}

		// Esta es la colección final, ya ordenada como quieres para el export
		$channels = $sortedChannels->values();

		// Generate list for Series
		$seriesPremiumLines = ['#EXTM3U'];
		$seriesPremiumLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesPremiumLines[] = '';

		foreach ($channels as $channel) {
			// Incluir los premium de la lista normal de cine
			if (!self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="series"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $seriesPremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesPremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesPremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $seriesPremiumLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $seriesPremiumLines[] = $channel->url_channel;
            }
            $seriesPremiumLines[] = '';
        }

        $content = implode("\n", $seriesPremiumLines);

        $filename = 'seriesPremium.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
	    public static function generateSeriesOttList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'series'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

		$channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channel_categories.order', 'asc') // orden cronológico de categorías
			->orderBy('channels.order', 'asc')           // orden cronológico de capítulos
			->select('channels.*')
			->get();

		// Reordenar SOLO para el export:
		// 1) Agrupar por nombre de categoría (título de serie)
		// 2) Ordenar las categorías por nombre, ignorando ! ? ¡ ¿ y tildes
		// 3) Mantener el orden cronológico interno de los capítulos dentro de cada categoría
		$grouped = $channels->groupBy(function ($channel) {
			return $channel->category->name ?? '';
		});

		$sortedCategoryNames = $grouped->keys()->sortBy(function ($name) {
			// Quitar signos de puntuación que quieras ignorar
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// Eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// Normalizar a minúsculas para comparación
			return mb_strtolower(trim($clean), 'UTF-8');
		});

		$sortedChannels = collect();
		foreach ($sortedCategoryNames as $catName) {
			$sortedChannels = $sortedChannels->merge($grouped[$catName]);
		}

		// Esta es la colección final, ya ordenada como quieres para el export
		$channels = $sortedChannels->values();

		// Generate list for Series
		$seriesOttLines = ['#EXTM3U'];
		$seriesOttLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesOttLines[] = '';

		foreach ($channels as $channel) {
			// Excluir los premium de la lista normal de cine
			if (self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="series"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $seriesOttPremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesOttPremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel)) {
                $seriesOttPremiumLines[] = $channel->url_channel;
            }
            $seriesOttPremiumLines[] = '';
        }

        $content = implode("\n", $seriesOttPremiumLines);

        $filename = 'seriesOtt.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }
	
	    public static function generateSeriesOttPremiumList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
			->where([
				['is_active', true],
				['tvg_type', 'series'],
			]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

		$channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
			->orderBy('channel_categories.order', 'asc') // orden cronológico de categorías
			->orderBy('channels.order', 'asc')           // orden cronológico de capítulos
			->select('channels.*')
			->get();

		// Reordenar SOLO para el export:
		// 1) Agrupar por nombre de categoría (título de serie)
		// 2) Ordenar las categorías por nombre, ignorando ! ? ¡ ¿ y tildes
		// 3) Mantener el orden cronológico interno de los capítulos dentro de cada categoría
		$grouped = $channels->groupBy(function ($channel) {
			return $channel->category->name ?? '';
		});

		$sortedCategoryNames = $grouped->keys()->sortBy(function ($name) {
			// Quitar signos de puntuación que quieras ignorar
			$clean = preg_replace('/[!?¡¿]/u', '', $name);

			// Eliminar tildes/acentos: Á → A, É → E, etc.
			$clean = Str::ascii($clean);

			// Normalizar a minúsculas para comparación
			return mb_strtolower(trim($clean), 'UTF-8');
		});

		$sortedChannels = collect();
		foreach ($sortedCategoryNames as $catName) {
			$sortedChannels = $sortedChannels->merge($grouped[$catName]);
		}

		// Esta es la colección final, ya ordenada como quieres para el export
		$channels = $sortedChannels->values();

		// Generate list for Series
		$seriesOttPremiumLines = ['#EXTM3U'];
		$seriesOttPremiumLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesOttPremiumLines[] = '';

		foreach ($channels as $channel) {
			// Incluir los premium de la lista normal de cine
			if (!self::isPremiumUrl($channel->url_channel)) {
				continue;
			}

			$extinf = '#EXTINF:-1';

			if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
			$extinf .= ' tvg-type="series"';
			if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            $extinf .= ',' . $channel->name;
            $seriesOttPremiumLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesOttPremiumLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesOttPremiumLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel)) {
                $seriesOttPremiumLines[] = $channel->url_channel;
            }
            $seriesOttPremiumLines[] = '';
        }

        $content = implode("\n", $seriesOttPremiumLines);

        $filename = 'seriesPremiumOtt.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }

    /**
     * Generate Kodi list.
     *
     * @param  Account  $account
     * @return void
     */
    public static function generateKodiList(Account $account): void
    {
        $cdnToken = $account->token ?? '';
        $folder = $account->folder ?? General::codeFromString($account->username, $account);

        // Create the folder if it doesn't exist
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $queryChannels = Channel::with('category')
            ->where([
                ['is_active', true],
                ['tvg_type', 'live'],
            ]);

        if ($account->parental_control) {
            $queryChannels->where('parental_control', false);
        }

        $channels = $queryChannels->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

        // Generate list for Kodi
        $kodiLines = ['#EXTM3U url-tvg="https://raw.githubusercontent.com/davidmuma/EPG_dobleM/master/guiatv_sincolor.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz, https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz, https://github.com/HelmerLuzo/PlutoTV_HL/raw/refs/heads/main/epg/es.xml.gz, https://github.com/HelmerLuzo/RakutenTV_HL/raw/refs/heads/main/epg/RakutenTV.xml.gz, https://raw.github.com/matthuisman/i.mjh.nz/master/SamsungTVPlus/es.xml.gz"'];
        $kodiLines[] = '';

        foreach ($channels as $channel) {
            $extinf = '#EXTINF: -1';

            if (!empty($channel->tvg_id)) {
                $extinf .= ' tvg-id="' . $channel->tvg_id . '"';
            }
            if (!empty($channel->name)) {
                $extinf .= ' tvg-name="' . $channel->name . '"';
            }
            if ($channel->category && !empty($channel->category->name)) {
                $extinf .= ' group-title="' . $channel->category->name . '"';
            }
            if (!empty($channel->catchup)) {
                $extinf .= ' catchup-type="' . $channel->catchup . '"';
            }
            if (!empty($channel->catchup_source) && $channel->apply_token) {
                $extinf .= ' catchup-source="' . $channel->catchup_source . '?device_profile=DASH_TV_WIDEVINE&start_time={utc:Y-m-dTH:M:S}Z&end_time=${end:Y-m-dTH:M:S}Z"';
            } else if (!empty($channel->catchup_source)) {
                $extinf .= ' catchup-source="' . $channel->catchup_source . '?device_profile=DASH_TV_WIDEVINE&start_time={utc:Y-m-dTH:M:S}Z&end_time=${end:Y-m-dTH:M:S}Z"';
            }
            if (!empty($channel->catchup_correction)) {
                $extinf .= ' catchup-correction="' . $channel->catchup_correction . '"';
            }
            if (!empty($channel->catchup_days)) {
                $extinf .= ' catchup-days="' . $channel->catchup_days . '"';
            }
            if (!empty($channel->logo)) {
                $extinf .= ' tvg-logo="' . $channel->logo . '"';
            }

            $extinf .= ',' . $channel->name;

            $kodiLines[] = $extinf;

            // KODIPROP properties
            $kodiLines[] = '#KODIPROP:contentlookup=False';
            
            if (!empty($channel->manifest_type)) {
                if ($channel->manifest_type === 'mpd') {
                    $kodiLines[] = '#KODIPROP:mimetype=application/dash+xml';
                } else if ($channel->manifest_type === 'hls') {
                    $kodiLines[] = '#KODIPROP:mimetype=application/x-mpegURL';
                }
            }
            
            $kodiLines[] = '#KODIPROP:inputstream=inputstream.adaptive';
            
            if (!empty($channel->manifest_type)) {
                $kodiLines[] = '#KODIPROP.inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            
            if ($channel->apply_token) {
                $kodiLines[] = '#KODIPROP:inputstream.adaptive.manifest_headers=User-Agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0&X-TCDN-token=' . $cdnToken;
            } else {
                $kodiLines[] = '#KODIPROP:inputstream.adaptive.manifest_headers=User-Agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0';
            }
            
            if (!empty($channel->api_key)) {
                $drm = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $drm .= ',' . $channel->catchup_api_key;
                }
                $kodiLines[] = '#KODIPROP:inputstream.adaptive.drm_legacy=org.w3.clearkey|' . $drm;
            }

            if (!empty($channel->url_channel)) {
                $kodiLines[] = $channel->url_channel;
            }
            
            $kodiLines[] = '';
        }

        $content = implode("\n", $kodiLines);

        $filename = 'kodi.m3u';
        Storage::disk('local')->put("{$folder}/{$filename}", $content);
    }

    /**
     * Convert JSON DRM keys to Kodi format (key_id:key)
     *
     * @param  string  $jsonKeys
     * @return string|null
     */
    private static function convertJsonKeysToKodi(string $jsonKeys): ?string
    {
        try {
            $data = json_decode($jsonKeys, true);
            
            if (!isset($data['keys']) || !is_array($data['keys']) || empty($data['keys'])) {
                return null;
            }
            
            $convertedKeys = [];
            
            // Process all keys in the array
            foreach ($data['keys'] as $keyData) {
                if (!isset($keyData['kid']) || !isset($keyData['k'])) {
                    continue;
                }
                
                // Decode base64url to hex
                $kid = self::base64UrlToHex($keyData['kid']);
                $k = self::base64UrlToHex($keyData['k']);
                
                if (!$kid || !$k) {
                    continue;
                }
                
                // Format: kid:k
                $convertedKeys[] = $kid . ':' . $k;
            }
            
            if (empty($convertedKeys)) {
                return null;
            }
            
            // Join multiple keys with comma
            return implode(',', $convertedKeys);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Convert base64url to hexadecimal
     *
     * @param  string  $base64url
     * @return string|null
     */
    private static function base64UrlToHex(string $base64url): ?string
    {
        try {
            // Convert base64url to base64
            $base64 = strtr($base64url, '-_', '+/');
            
            // Add padding if needed
            $padding = strlen($base64) % 4;
            if ($padding > 0) {
                $base64 .= str_repeat('=', 4 - $padding);
            }
            
            // Decode base64 to binary
            $binary = base64_decode($base64, true);
            
            if ($binary === false) {
                return null;
            }
            
            // Convert binary to hex
            return bin2hex($binary);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse a string with format "kid:key,kid2:key2" into assoc array
     *
     * @param string $str
     * @return array|null
     */
    private static function parseColonPairs(string $str): ?array
    {
        $result = [];

        $parts = array_filter(array_map('trim', explode(',', $str)), function ($v) {
            return $v !== '';
        });

        foreach ($parts as $part) {
            $pair = explode(':', $part, 2);
            if (count($pair) === 2) {
                $k = trim($pair[0]);
                $v = trim($pair[1]);
                if ($k !== '' && $v !== '') {
                    $result[$k] = $v;
                }
            }
        }

        return empty($result) ? null : $result;
    }
}
