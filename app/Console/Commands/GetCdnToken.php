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
    protected $signature = 'get-cdn-token {account? : ID de la cuenta (opcional)}';
    protected $description = 'Obtiene el CDN Token.';

    public function handle()
    {
        $accountId = $this->argument('account');

        if ($accountId) {
            $accounts = Account::where('id', $accountId)->get();

            if ($accounts->isEmpty()) {
                $this->error("No se encontró la cuenta con ID: {$accountId}");
                return 1;
            }
        }
        else {
            $accounts = Account::all();
        }

        foreach ($accounts as $account) {
            try {
                $result = Token::refreshCdnToken($account);
                Log::info("CDN Token obtenido: {$result['cdnToken']}");
                Lists::generateTivimateList($account);
                Lists::generateOttList($account);
                Lists::generateCineList($account);
                Lists::generateSeriesList($account);
                Lists::generateCineOttList($account);
                Lists::generateSeriesOttList($account);
                Lists::generateKodiList($account);
                Lists::generateCineOttpremiumList($account);
                Lists::generateSeriesOttpremiumList($account);
                Lists::generateCinePremiumList($account);
                Lists::generateSeriesPremiumList($account);
                Log::info('CDN Token obtenido y reemplazado correctamente en los archivos.');
            }
            catch (TokenException $e) {
                $this->error($e->getMessage());
                Log::error($e);
                return 1;
            }
        }

        return 0;
    }
}
