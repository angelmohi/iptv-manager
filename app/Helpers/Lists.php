<?php

namespace App\Helpers;

use App\Models\Account;
use App\Models\Channel;
use Illuminate\Support\Facades\Storage;

class Lists
{
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
            ->orderBy('channels.name', 'asc')
            ->select('channels.*')
            ->get();
	
        // Generate list for Cine
        $cineLines = ['#EXTM3U'];
		$cineLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
        $cineLines[] = '';

        foreach ($channels as $channel) {
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
            $cineLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $cineLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $cineLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $cineLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $cineLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $cineLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $cineLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $cineLines[] = $channel->url_channel;
            }
            $cineLines[] = '';
        }

        $content = implode("\n", $cineLines);

        $filename = 'cine.m3u';
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
            ->orderBy('channels.name', 'asc')
            ->select('channels.*')
            ->get();
	
        // Generate list for CineOtt
        $cineOttLines = ['#EXTM3U'];
		$cineOttLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
        $cineOttLines[] = '';

        foreach ($channels as $channel) {
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
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

	
        // Generate list for Series
        $seriesLines = ['#EXTM3U'];
		$seriesLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesLines[] = '';


        foreach ($channels as $channel) {
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
            $seriesLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel) && $channel->apply_token) {
                $seriesLines[] = $channel->url_channel . '|X-TCDN-token=' . $cdnToken;
            } else if (!empty($channel->url_channel)) {
                $seriesLines[] = $channel->url_channel;
            }
            $seriesLines[] = '';
        }

        $content = implode("\n", $seriesLines);

        $filename = 'series.m3u';
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
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

	
        // Generate list for Series Ott
        $seriesOttLines = ['#EXTM3U'];
		$seriesOttLines[] = '#EXT-X-PLAYLIST-TYPE:VOD';
		$seriesOttLines[] = '';


        foreach ($channels as $channel) {
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
            $seriesOttLines[] = $extinf;

            if (!empty($channel->user_agent)) {
                $seriesOttLines[] = '#EXTVLCOPT:http-user-agent=' . $channel->user_agent;
            }
            if (!empty($channel->manifest_type)) {
                $seriesOttLines[] = '#KODIPROP:inputstream.adaptive.manifest_type=' . $channel->manifest_type;
            }
            if (!empty($channel->license_type)) {
                $seriesOttLines[] = '#KODIPROP:inputstream.adaptive.license_type=' . $channel->license_type;
            }
            if (!empty($channel->api_key)) {
                $license = $channel->api_key;
                if (!empty($channel->catchup_api_key)) {
                    $license .= ',' . $channel->catchup_api_key;
                }
                $seriesOttLines[] = '#KODIPROP:inputstream.adaptive.license_key=' . $license;
            }
            if ($channel->apply_token) {
                $seriesOttLines[] = '#KODIPROP:inputstream.adaptive.stream_headers=X-TCDN-token=' . $cdnToken;
            }
            if (!empty($channel->url_channel)) {
                $seriesOttLines[] = $channel->url_channel;
            }
            $seriesOttLines[] = '';
        }

        $content = implode("\n", $seriesOttLines);

        $filename = 'seriesOtt.m3u';
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
