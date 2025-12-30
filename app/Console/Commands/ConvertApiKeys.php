<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;

class ConvertApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channels:convert-apikeys {--dry-run : Do not save changes, only show what would be changed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convierte api_key JSON de keys a formato hex:khex (soporta multikeys) para canales live activos';

    public function handle()
    {
        $this->info('Buscando canales con tvg_type=live, api_key not null, is_active=1 y deleted_at null...');

        $query = Channel::query()
            ->where('tvg_type', 'live')
            ->whereNotNull('api_key')
            ->where('is_active', 1)
            ->whereNull('deleted_at');

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('No se encontraron canales que cumplan los criterios.');
            return 0;
        }

        $convertedPattern = '/^[0-9a-f]{32}:[0-9a-f]{32}(?:,[0-9a-f]{32}:[0-9a-f]{32})*$/i';

        foreach ($channels as $channel) {
            $api = trim((string) $channel->api_key);

            if ($api === '') {
                continue;
            }

            if (preg_match($convertedPattern, $api)) {
                $this->line("Skipping channel {$channel->id} ({$channel->name}): already converted");
                continue;
            }

            $data = json_decode($api, true);
            if (!is_array($data) || !isset($data['keys']) || !is_array($data['keys'])) {
                $this->line("Skipping channel {$channel->id} ({$channel->name}): api_key is not valid JSON multi-key structure");
                continue;
            }

            $pairs = [];

            foreach ($data['keys'] as $key) {
                if (!isset($key['k'])) {
                    $this->line("Skipping channel {$channel->id} ({$channel->name}): key entry missing 'k'");
                    $pairs = [];
                    break;
                }

                $k = $key['k'];
                $kid = isset($key['kid']) ? $key['kid'] : '';

                $hexK = $this->base64ToHex($k);
                $hexKid = $kid !== '' ? $this->base64ToHex($kid) : str_repeat('0', 32);

                if ($hexK === null || $hexKid === null) {
                    $this->line("Skipping channel {$channel->id} ({$channel->name}): base64 decode failed for key/kid");
                    $pairs = [];
                    break;
                }

                $pairs[] = $hexKid . ':' . $hexK;
            }

            if (empty($pairs)) {
                continue;
            }

            $new = implode(',', $pairs);

            if ($this->option('dry-run')) {
                $this->line("Channel {$channel->id} ({$channel->name}) => {$new}");
            } else {
                $old = $channel->api_key;
                $channel->api_key = $new;
                $channel->save();

                Log::info('channels:convert-apikeys', [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'old_api_key' => $old,
                    'new_api_key' => $new,
                ]);

                $this->line("Logged channel {$channel->id} ({$channel->name}) => {$new}");
            }
        }

        $this->info('Procesamiento finalizado.');
        return 0;
    }

    /**
     * Decode a base64 (possibly missing padding) string and return lowercase hex or null on failure.
     *
     * @param string $b64
     * @return string|null
     */
    protected function base64ToHex(string $b64): ?string
    {
        $s = str_replace(' ', '', $b64);
        $s = strtr($s, '-_', '+/');
        $mod = strlen($s) % 4;
        if ($mod > 0) {
            $s .= str_repeat('=', 4 - $mod);
        }

        $bin = base64_decode($s, true);
        if ($bin === false) {
            return null;
        }

        return strtolower(bin2hex($bin));
    }
}
