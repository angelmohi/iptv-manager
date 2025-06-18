<?php

namespace App\Console\Commands;

use App\Exceptions\TokenException;
use App\Helpers\Lists;
use App\Helpers\Token;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCdnToken extends Command
{
    protected $signature = 'get-cdn-token';
    protected $description = 'Obtiene el CDN Token.';

    public function handle()
    {
        $accounts = Account::all();

        foreach ($accounts as $account) {
            try {
                $result = Token::refreshCdnToken($account);
                Log::info("CDN Token obtenido: {$result['cdnToken']}");
                Lists::generateTivimateList($account);
                Lists::generateOttList($account);
                Log::info('CDN Token obtenido y reemplazado correctamente en los archivos.');
            } catch (TokenException $e) {
                $this->error($e->getMessage());
                Log::error($e);
                return 1;
            }
        }

        return 0;
    }
}
