<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\DB;

class ImportChannelCategories extends Command
{
    /**
     * El nombre y firma del comando Artisan.
     */
    protected $signature = 'import:channel-categories';

    /**
     * La descripción del comando.
     */
    protected $description = 'Importa los grupos (group-title) desde un archivo M3U a la tabla channel_categories, sin duplicados';

    /**
     * Crea una nueva instancia del comando.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Lógica del comando.
     */
    public function handle()
    {
        $filePath = storage_path('app/total_ott.m3u');

        // Verificamos que el archivo exista y sea legible
        if (! is_readable($filePath)) {
            $this->error("No se puede leer el archivo: {$filePath}");
            return 1; // Código de error
        }

        // Abrimos el archivo en modo lectura
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Error al abrir el archivo: {$filePath}");
            return 1;
        }

        $this->info("Procesando archivo: {$filePath}");

        $categories = []; // Array temporal para ir almacenando nombres únicos

        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                continue;
            }

            // Solo nos interesan las líneas que contienen #EXTINF
            // (aunque podríamos aplicar directamente la regex sin este chequeo, es más eficiente así)
            if (stripos($line, '#EXTINF') === false) {
                continue;
            }

            // Usamos una expresión regular para capturar el valor de group-title="..."
            // La sintaxis busca group-title="(...)"
            if (preg_match('/group-title="([^"]+)"/i', $line, $matches)) {
                $groupTitle = trim($matches[1]);

                // Ignoramos si viene vacío
                if (strlen($groupTitle) === 0) {
                    continue;
                }

                // Si no está ya en nuestro array temporal, lo añadimos.
                // Esto evita procesar duplicados internos en el mismo archivo.
                if (! in_array($groupTitle, $categories, true)) {
                    $categories[] = $groupTitle;
                }
            }
        }

        fclose($handle);

        $this->info('Categorías encontradas en el archivo: ' . count($categories));

        // Ahora, por cada categoría única en el array, la insertamos en la BD
        // solo si no existe ya (para no duplicar).
        // Podemos usar firstOrCreate para simplificar.
        $insertCount = 0;
        DB::beginTransaction();
        try {
            foreach ($categories as $catName) {
                // Puedes ajustar el valor de 'order' según necesites. Aquí lo dejamos en 1 por defecto.
                $categoria = ChannelCategory::firstOrCreate(
                    ['name' => $catName],
                    ['order' => 1]
                );

                // Si fue recién creada (no existía), incrementamos el contador
                if ($categoria->wasRecentlyCreated) {
                    $insertCount++;
                    $this->info("Se creó categoría: {$catName}");
                } else {
                    $this->line("Ya existe categoría: {$catName} (omitido)");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Ocurrió un error al guardar en la BD: " . $e->getMessage());
            return 1;
        }

        $this->info("Proceso finalizado. Categorías nuevas insertadas: {$insertCount}");

        return 0; // Éxito
    }
}
