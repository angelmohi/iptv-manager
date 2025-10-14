<?php

namespace App\Console\Commands;

use App\Exceptions\TokenException;
use App\Helpers\Lists;
use App\Helpers\Token;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCdnTokenScheduledAll extends Command
{
    protected $signature = 'get-cdn-token:scheduled-all 
                            {--min-hour=3 : Hora mínima del rango (0-23)}
                            {--max-hour=5 : Hora máxima del rango (0-23)}
                            {--min-delay=60 : Segundos mínimos de delay entre cada cuenta (por defecto 1 min)}
                            {--max-delay=180 : Segundos máximos de delay entre cada cuenta (por defecto 3 min)}
                            {--no-delay : Ejecutar inmediatamente sin delay inicial}';
    
    protected $description = 'Obtiene el CDN Token para todas las cuentas con delays aleatorios.';

    public function handle()
    {
        // Aumentar el tiempo de ejecución máximo
        set_time_limit(3600); // 1 hora
        ini_set('max_execution_time', '3600');
        
        $startTime = now();
        
        $minHour = (int) $this->option('min-hour');
        $maxHour = (int) $this->option('max-hour');
        $noDelay = $this->option('no-delay');
        $minDelay = (int) $this->option('min-delay');
        $maxDelay = (int) $this->option('max-delay');
        
        Log::info("GetCdnTokenScheduledAll: Iniciando script", [
            'start_time' => $startTime,
            'min_hour' => $minHour,
            'max_hour' => $maxHour,
            'min_delay' => $minDelay,
            'max_delay' => $maxDelay
        ]);
        
        // Si no se especifica no-delay, aplicar el delay aleatorio inicial
        if (!$noDelay) {
            $delay = $this->calculateRandomDelay($minHour, $maxHour);
            
            if ($delay > 0) {
                $delayMinutes = round($delay / 60, 2);
                $this->info("Esperando {$delayMinutes} minutos antes de iniciar el procesamiento...");
                Log::info("GetCdnTokenScheduledAll: Esperando {$delayMinutes} minutos (delay aleatorio inicial)");
                sleep($delay);
            }
        }

        // Obtener todas las cuentas
        $accounts = Account::all();
        $totalAccounts = $accounts->count();
        
        if ($totalAccounts === 0) {
            $this->warn("No hay cuentas para procesar.");
            Log::warning("GetCdnTokenScheduledAll: No hay cuentas para procesar");
            return 0;
        }

        $this->info("Iniciando procesamiento de {$totalAccounts} cuentas...");
        Log::info("GetCdnTokenScheduledAll: Iniciando procesamiento de {$totalAccounts} cuentas");

        $successCount = 0;
        $errorCount = 0;

        foreach ($accounts as $index => $account) {
            $accountNumber = $index + 1;
            
            try {
                $this->info("[{$accountNumber}/{$totalAccounts}] Procesando cuenta: {$account->id}");
                
                $result = Token::refreshCdnToken($account);
                Log::info("CDN Token obtenido para cuenta {$account->id}: {$result['cdnToken']}");
                
                Lists::generateTivimateList($account);
                Lists::generateOttList($account);
                Lists::generateCineList($account);
                Lists::generateSeriesList($account);
                Lists::generateCineOttList($account);
                Lists::generateSeriesOttList($account);
                
                $this->info("✓ Cuenta {$account->id} procesada correctamente");
                Log::info("GetCdnTokenScheduledAll: CDN Token obtenido y listas generadas para cuenta {$account->id}");
                $successCount++;
                
                // Delay aleatorio entre cuentas (excepto después de la última)
                if ($accountNumber < $totalAccounts) {
                    $randomDelay = rand($minDelay, $maxDelay);
                    $delayMinutes = round($randomDelay / 60, 2);
                    $this->info("Esperando {$delayMinutes} minutos antes de la siguiente cuenta...");
                    sleep($randomDelay);
                }
                
            } catch (TokenException $e) {
                $this->error("✗ Error en cuenta {$account->id}: " . $e->getMessage());
                Log::error("GetCdnTokenScheduledAll: Error en cuenta {$account->id} - " . $e->getMessage());
                Log::error($e);
                $errorCount++;
                
                // Continuar con la siguiente cuenta en lugar de salir
                continue;
            }
        }

        // Resumen final
        $endTime = now();
        $totalDuration = $startTime->diffInMinutes($endTime);
        
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✓ Proceso completado");
        $this->info("  Total cuentas: {$totalAccounts}");
        $this->info("  Exitosas: {$successCount}");
        if ($errorCount > 0) {
            $this->error("  Con errores: {$errorCount}");
        }
        $this->info("  Duración total: {$totalDuration} minutos");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        Log::info("GetCdnTokenScheduledAll: Proceso completado", [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'duration_minutes' => $totalDuration,
            'end_time' => $endTime
        ]);
        
        // Retornar 0 si al menos una cuenta fue exitosa, 1 si todas fallaron
        return $successCount > 0 ? 0 : 1;
    }

    /**
     * Calcula un delay aleatorio basado en la hora actual y el rango especificado
     * 
     * @param int $minHour Hora mínima (0-23) en la zona horaria de la aplicación
     * @param int $maxHour Hora máxima (0-23) en la zona horaria de la aplicación
     * @return int Segundos a esperar
     */
    private function calculateRandomDelay(int $minHour, int $maxHour): int
    {
        // Usar la zona horaria configurada en la aplicación (Europe/Madrid)
        $timezone = config('app.timezone', 'Europe/Madrid');
        $now = now()->setTimezone($timezone);
        
        $currentHour = $now->hour;
        $currentMinute = $now->minute;
        $currentSecond = $now->second;
        
        // Si ya estamos dentro del rango, ejecutar con un pequeño delay aleatorio
        if ($currentHour >= $minHour && $currentHour < $maxHour) {
            // Delay aleatorio de 0 a 5 minutos
            return rand(0, 300);
        }
        
        // Si estamos antes del rango, calcular delay hasta una hora aleatoria en el rango
        if ($currentHour < $minHour) {
            $targetHour = rand($minHour, $maxHour - 1);
            $targetMinute = rand(0, 59);
            
            $hoursToWait = $targetHour - $currentHour;
            $minutesToWait = $targetMinute - $currentMinute;
            
            $totalSeconds = ($hoursToWait * 3600) + ($minutesToWait * 60) - $currentSecond;
            
            return max(0, $totalSeconds);
        }
        
        // Si ya pasó el rango de hoy, no hacer delay (se ejecutará inmediatamente)
        // El cron lo volverá a ejecutar mañana
        return 0;
    }
}
