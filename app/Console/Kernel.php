<?php

namespace App\Console;

use App\Models\Account;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     * 
     * Programa el refresco de CDN Token para cada cuenta a una hora aleatoria
     * determinista entre las 3:00-4:00 y 12h después (15:00-16:00).
     */
    protected function schedule(Schedule $schedule): void
    {
        try {
            $accounts = Account::all();

            foreach ($accounts as $account) {
                // Seed determinista: misma hora durante el día, diferente entre cuentas y días
                $seed = crc32(now()->format('Y-m-d') . '-' . $account->id);
                mt_srand($seed);
                $minuteOffset = mt_rand(0, 59);
                mt_srand(); // Restaurar aleatoriedad normal

                $morningTime = sprintf('03:%02d', $minuteOffset);
                $afternoonTime = sprintf('15:%02d', $minuteOffset);

                $schedule->command('get-cdn-token', [$account->id])
                    ->dailyAt($morningTime)
                    ->timezone('Europe/Madrid')
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/cdn-token.log'));

                $schedule->command('get-cdn-token', [$account->id])
                    ->dailyAt($afternoonTime)
                    ->timezone('Europe/Madrid')
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/cdn-token.log'));
            }
        }
        catch (\Exception $e) {
            Log::error('Error configurando schedule de CDN tokens: ' . $e->getMessage());
        }

        // Ejecutar ExportDifusionEpg cada hora pasados 5 minutos (ej: 00:05, 01:05, 02:05...)
        $schedule->command('export:difusion-epg')
            ->hourlyAt(5)
            ->timezone('Europe/Madrid')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/export-difusion.log'));

        // Ejecutar ExtractPssh una vez al día a las 5:00 de la mañana
        $schedule->command('channels:update-pssh')
            ->dailyAt('05:00')
            ->timezone('Europe/Madrid')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/extract-pssh.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
