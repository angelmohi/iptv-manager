<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Channel;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\DB;

class ImportChannels extends Command
{
    /**
     * El nombre y firma del comando.
     * Tomará siempre storage/app/total_ott.m3u sin parámetros.
     */
    protected $signature = 'import:channels';

    /**
     * La descripción del comando.
     */
    protected $description = 'Importa todos los canales desde storage/app/total_ott.m3u, saltando los comentados (##...) y líneas no válidas.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 1. Ruta fija al fichero dentro de storage/app
        $filePath = storage_path('app/total_ott.m3u');

        // 2. Verificamos que exista y sea legible
        if (!is_readable($filePath)) {
            $this->error("No se puede leer el archivo: {$filePath}");
            return 1;
        }

        $this->info("Iniciando importación de canales desde: {$filePath}");

        // 3. Abrimos el fichero para recorrerlo línea a línea
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Error al abrir el archivo: {$filePath}");
            return 1;
        }

        // 4. Variables temporales para construir cada canal
        $current = [
            'tvg_id'        => null,
            'name'          => null,
            'group_title'   => null,
            'logo'          => null,
            'catchup'       => null,
            'catchup_days'  => null,
            'catchup_source'=> null,
            'user_agent'    => null,
            'manifest_type' => null,
            'license_type'  => null,
            'api_key'       => null,
            'url_channel'   => null,
        ];

        // 5. Contador de canales procesados exitosamente
        $contadorCanales = 0;

        // 6. Para asignar un orden incremental si quisieras
        $orderCounter = 1;

        // 7. Recorremos cada línea del fichero
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                continue;
            }

            // Quitamos espacios a los lados
            $trimmed = trim($line);

            // 7.1. Si la línea está vacía, saltamos
            if ($trimmed === '') {
                continue;
            }

            // 7.2. Si la línea empieza por "##", está comentada => la ignoramos
            if (substr($trimmed, 0, 2) === '##') {
                continue;
            }

            // 7.3. Si la línea empieza por "<", por ejemplo "<###### Movistar Futbol######>", la ignoramos
            if (substr($trimmed, 0, 1) === '<') {
                continue;
            }

            // 7.4. Si la línea es un bloque "#EXTINF:" (solo un # delante), procesamos el inicio de un canal
            if (stripos($trimmed, '#EXTINF:') === 0) {
                // 7.4.1. Al iniciar un nuevo EXTINF, reiniciamos $current
                $current = [
                    'tvg_id'        => null,
                    'name'          => null,
                    'group_title'   => null,
                    'logo'          => null,
                    'catchup'       => null,
                    'catchup_days'  => null,
                    'catchup_source'=> null,
                    'user_agent'    => null,
                    'manifest_type' => null,
                    'license_type'  => null,
                    'api_key'       => null,
                    'url_channel'   => null,
                ];

                // 7.4.2. Extraemos todos los atributos clave="valor" de la misma línea EXTINF
                //     Usamos ([\w-]+) para que acepte guiones en la clave.
                //     Ejemplo de línea EXTINF:
                //     #EXTINF:-1 tvg-id="TV3" tvg-name="TV3" group-title="Movistar Autonomicas"
                //               catchup="default" catchup-days="30" catchup-source="https://...mpd" tvg-logo="...png", TV3
                preg_match_all('/([\w-]+)="([^"]*)"/i', $trimmed, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    // $m[1] contiene la clave (por ejemplo "tvg-id", "group-title", "catchup-days", etc.)
                    // $m[2] contiene el valor (por ejemplo "TV3", "Movistar Autonomicas", "30", etc.)
                    $key = strtolower($m[1]);
                    $val = trim($m[2]);

                    switch ($key) {
                        case 'tvg-id':
                            $current['tvg_id'] = $val;
                            break;
                        case 'tvg-name':
                            $current['name'] = $val;
                            break;
                        case 'group-title':
                            $current['group_title'] = $val;
                            break;
                        case 'tvg-logo':
                            $current['logo'] = $val;
                            break;
                        case 'catchup':
                            $current['catchup'] = $val;
                            break;
                        case 'catchup-days':
                            $current['catchup_days'] = $val;
                            break;
                        case 'catchup-source':
                            $current['catchup_source'] = $val;
                            break;
                        // Si hubiera más campos que extraer de EXTINF, añadir aquí
                    }
                }

                // 7.4.3. Obtener el "nombre humano" después de la coma, si no vino tvg-name
                if (stripos($trimmed, ',') !== false) {
                    $afterComma = substr($trimmed, stripos($trimmed, ',') + 1);
                    if (empty($current['name'])) {
                        $current['name'] = trim($afterComma);
                    }
                }

                // Pasamos a la siguiente línea; el canal completo se cerrará cuando encontremos la URL
                continue;
            }

            // 7.5. Si la línea empieza con "#EXTVLCOPT:", buscamos user_agent
            if (stripos($trimmed, '#EXTVLCOPT:') === 0) {
                // Ejemplo: #EXTVLCOPT:http-user-agent=Mozilla/5.0 (...)
                if (preg_match('/http-user-agent=(.*)/i', $trimmed, $m2)) {
                    $ua = trim($m2[1]);
                    $current['user_agent'] = trim($ua, "\"");
                }
                continue;
            }

            // 7.6. Si la línea empieza con "#KODIPROP:", buscamos manifest_type, license_type, license_key
            if (stripos($trimmed, '#KODIPROP:') === 0) {
                $kodiprop = substr($trimmed, strlen('#KODIPROP:'));

                // 7.6.1. Capturar manifest_type
                if (preg_match('/inputstream\.adaptive\.manifest_type=([^\s]+)/i', $kodiprop, $m3)) {
                    $current['manifest_type'] = trim($m3[1]);
                }

                // 7.6.2. Capturar license_type
                if (preg_match('/inputstream\.adaptive\.license_type=([^\s]+)/i', $kodiprop, $m4)) {
                    $current['license_type'] = trim($m4[1]);
                }

                // 7.6.3. Capturar license_key:
                //   - Caso JSON con llaves: { … }
                //   - Caso token tipo "hex:hex" (sin espacios)
                if (preg_match('/inputstream\.adaptive\.license_key=({.*})/i', $kodiprop, $m5_json)) {
                    // Si es JSON (encerrado en llaves), lo tomamos completo (incluyendo espacios internos)
                    $current['api_key'] = trim($m5_json[1]);
                }
                elseif (preg_match('/inputstream\.adaptive\.license_key=([^\s]+)/i', $kodiprop, $m5_plain)) {
                    // Si no es JSON, es un token corto (hex:hex), lo tomamos tal cual
                    $current['api_key'] = trim($m5_plain[1]);
                }

                // 7.6.4. Stream headers (X-TCDN-token=…) lo ignoramos deliberadamente
                continue;
            }

            // 7.7. Si la línea NO empieza con "#" ni con "<" ni está vacía => es la URL final del canal
            if (substr($trimmed, 0, 1) !== '#' && substr($trimmed, 0, 1) !== '<') {
                // 7.7.1. Asignamos la URL
                $current['url_channel'] = $trimmed;

                // 7.7.2. Ya tenemos todo el bloque del canal, procedemos a guardarlo

                // 7.7.2.1. Buscar la categoría en base a group_title
                $catId = null;
                if (!empty($current['group_title'])) {
                    $categoria = ChannelCategory::where('name', $current['group_title'])->first();
                    if ($categoria) {
                        $catId = $categoria->id;
                    } else {
                        // Si la categoría no existe, puedes dejar null
                        // O bien comentarlo para crearla al vuelo:
                        // $categoria = ChannelCategory::create(['name' => $current['group_title'], 'order' => 1]);
                        // $catId = $categoria->id;
                        $catId = null;
                    }
                }

                // 7.7.2.2. Limpiar catchup_source de posibles tokens (si fuera necesario)
                if (!empty($current['catchup_source'])) {
                    // Por si la URL contuviera "?X-TCDN-token=...&..." o "&X-TCDN-token=..."
                    $current['catchup_source'] = preg_replace(
                        '/([?&])X-TCDN-token=[^&]+(&?)/i',
                        '$1',
                        $current['catchup_source']
                    );
                    // Quitar un posible "&" sobrante al final
                    $current['catchup_source'] = rtrim($current['catchup_source'], '&');
                }

                // 7.7.2.3. Preparamos array con todos los datos a insertar
                $datosCanal = [
                    'category_id'   => $catId,
                    'name'          => $current['name']           ?? null,
                    'tvg_id'        => $current['tvg_id']         ?? null,
                    'logo'          => $current['logo']           ?? null,
                    'user_agent'    => $current['user_agent']     ?? null,
                    'manifest_type' => $current['manifest_type']  ?? null,
                    'license_type'  => $current['license_type']   ?? null,
                    'api_key'       => $current['api_key']        ?? null,
                    'url_channel'   => $current['url_channel']    ?? null,
                    'catchup'       => $current['catchup']        ?? null,
                    'catchup_days'  => $current['catchup_days']   ?? null,
                    'catchup_source'=> $current['catchup_source'] ?? null,
                    'order'         => $orderCounter,  // Orden global incremental
                ];

                // 7.7.2.4. Insertar en la tabla channels
                try {
                    Channel::create($datosCanal);
                    $contadorCanales++;
                    $this->info("Canal importado: {$current['name']} (tvg-id: {$current['tvg_id']})");
                } catch (\Exception $e) {
                    $this->error("Error al insertar canal {$current['name']}: " . $e->getMessage());
                }

                // 7.7.2.5. Preparar para el siguiente canal: incrementar orden y resetear $current
                $orderCounter++;
                $current = [
                    'tvg_id'        => null,
                    'name'          => null,
                    'group_title'   => null,
                    'logo'          => null,
                    'catchup'       => null,
                    'catchup_days'  => null,
                    'catchup_source'=> null,
                    'user_agent'    => null,
                    'manifest_type' => null,
                    'license_type'  => null,
                    'api_key'       => null,
                    'url_channel'   => null,
                ];
            }

            // Si la línea no cumple ninguno de los casos anteriores, se descarta
        } // fin del while

        fclose($handle);

        $this->info("Importación finalizada. Total de canales insertados: {$contadorCanales}");

        return 0;
    }
}
