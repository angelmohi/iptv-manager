<?php

namespace App\Helpers;

use App\Models\Account;
use App\Models\Channel;
use Illuminate\Support\Facades\Storage;

class Lists
{
    /**
     * Generate lists.
     *
     * @param  Account  $account
     * @return void
     */
    public static function generate(Account $account): void
    {
        $cdnToken = $account->token ?? '';

        // Create the folder if it doesn't exist
        $folder = General::codeFromString($account->username, $account);
        if (!Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $channels = Channel::with('category')
            ->where('is_active', true)
            ->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
            ->orderBy('channel_categories.order', 'asc')
            ->orderBy('channels.order', 'asc')
            ->select('channels.*')
            ->get();

        // Generate list for OTT
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

        // Generate list for Tivimate
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
}
