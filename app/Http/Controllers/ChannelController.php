<?php

namespace App\Http\Controllers;

use App\Helpers\Lists;
use App\Helpers\Pssh;
use App\Helpers\SearchKeys;
use App\Models\Account;
use App\Models\ChannelCategory;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class ChannelController extends Controller
{
    // null patterns = default (everything that doesn't match the others)
    private const PLATFORM_PATTERNS = [
        'Apple TV'       => ['dash/applmd'],
        'HBO Max'        => ['dash/hbomd'],
        'SkyShowtime'    => ['dash/skymd'],
        'FlixOlé'        => ['dash/flixmd'],
        'Movistar Plus+' => null,
    ];

    public const TYPE_CONFIG = [
        'live'   => ['label' => 'Live',      'icon' => 'fas fa-broadcast-tower'],
        'movie'  => ['label' => 'Películas', 'icon' => 'fas fa-film'],
        'series' => ['label' => 'Series',    'icon' => 'fas fa-tv'],
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the channels.
     */
    public function index(Request $request, string $type): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->getChannelsData($request, $type);
        }

        $config = self::TYPE_CONFIG[$type];
        $categories = ChannelCategory::where('type', $type)->orderBy('order')->get();
        return view('channels.index', compact('type', 'config', 'categories'));
    }

    /**
     * Get channels data for DataTables AJAX request.
     */
    public function getChannelsData(Request $request, string $type): JsonResponse
    {
        $query = Channel::with('category')->where('tvg_type', $type);

        // Handle platform filter
        if ($request->filled('platform') && array_key_exists($request->platform, self::PLATFORM_PATTERNS)) {
            $patterns = self::PLATFORM_PATTERNS[$request->platform];
            if ($patterns === null) {
                // Movistar Plus+: URLs that don't match any specific platform
                $allPatterns = array_merge(...array_filter(array_values(self::PLATFORM_PATTERNS)));
                foreach ($allPatterns as $p) {
                    $query->where('url_channel', 'not like', "%{$p}%");
                }
            } else {
                $query->where(function ($q) use ($patterns) {
                    foreach ($patterns as $p) {
                        $q->orWhere('url_channel', 'like', "%{$p}%");
                    }
                });
            }
        }

        // Handle search
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $search = "%{$searchValue}%";
                $q->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', [$search])
                    ->orWhereRaw('tvg_id COLLATE utf8mb4_unicode_ci LIKE ?', [$search])
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', [$search]);
                    });

                // Platform search: match search term against platform names
                foreach (self::PLATFORM_PATTERNS as $platform => $patterns) {
                    if (!str_contains(strtolower($platform), strtolower($searchValue))) continue;

                    if ($patterns === null) {
                        // Movistar Plus+: URLs that don't match any specific pattern
                        $allPatterns = array_merge(...array_filter(array_values(self::PLATFORM_PATTERNS)));
                        $q->orWhere(function ($q2) use ($allPatterns) {
                            foreach ($allPatterns as $p) {
                                $q2->where('url_channel', 'not like', "%{$p}%");
                            }
                        });
                    } else {
                        foreach ($patterns as $p) {
                            $q->orWhere('url_channel', 'like', "%{$p}%");
                        }
                    }
                }
            });
        }

        // Handle ordering
        if ($request->has('order')) {
            $orderColumn = $request->columns[$request->order[0]['column']]['data'];
            $orderDirection = $request->order[0]['dir'];

            switch ($orderColumn) {
                case 'name':
                    $query->orderBy('name', $orderDirection);
                    break;
                case 'category':
                    $query->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
                        ->orderBy('channel_categories.name', $orderDirection)
                        ->select('channels.*');
                    break;
                case 'is_active':
                    $query->orderBy('is_active', $orderDirection);
                    break;
                case 'apply_token':
                    $query->orderBy('apply_token', $orderDirection);
                    break;
                default:
                    $query->orderBy('id', 'asc');
            }
        } else {
            $query->orderBy('id', 'asc');
        }

        $totalRecords = Channel::where('tvg_type', $type)->count();
        $totalFiltered = $query->count();

        // Handle pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;
        $channels = $query->skip($start)->take($length)->get();

        $data = [];
        foreach ($channels as $channel) {
            $data[] = [
                'name' => $channel->name,
                'category' => $channel->category->name ?? 'Uncategorized',
                'platform' => self::getPlatformFromUrl($channel->url_channel),
                'apply_token' => $channel->apply_token ? 'Yes' : 'No',
                'is_active' => $channel->is_active ? 'Yes' : 'No',
                'edit_url' => route('channels.edit', ['type' => $type, 'channel' => $channel->id]),
            ];
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
    }

    /**
     * Show the form for creating a new channel.
     */
    public function create(string $type): View
    {
        $categories = ChannelCategory::where('type', $type)->orderBy('order')->get();
        $config = self::TYPE_CONFIG[$type];
        return view('channels.create', compact('categories', 'type', 'config'));
    }

    /**
     * Show the form for editing the channel.
     */
    public function edit(string $type, Channel $channel): View
    {
        $categories = ChannelCategory::where('type', $type)->orderBy('order')->get();
        $config = self::TYPE_CONFIG[$type];
        return view('channels.edit', compact('channel', 'categories', 'type', 'config'));
    }

    /**
     * Store a new channel.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:channel_categories,id',
            'tvg_id' => 'nullable|string|max:255',
            'logo' => 'required',
            'url_channel' => 'required',
            'pssh' => 'nullable',
            'api_key' => 'nullable',
            'user_agent' => 'nullable',
            'manifest_type' => 'nullable',
            'license_type' => 'nullable',
            'catchup' => 'nullable',
            'catchup_days' => 'nullable',
            'catchup_source' => 'nullable',
            'catchup_pssh' => 'nullable',
            'catchup_api_key' => 'nullable',

            'is_active' => 'required|boolean',
            'apply_token' => 'required|boolean',
            'parental_control' => 'required|boolean',
        ]);

        $data['tvg_type'] = $type;
        $data['order'] = Channel::max('order') + 1;

        $channel = Channel::create($data);

        flashSuccessMessage('Canal creado correctamente.');
        return jsonIframeRedirection(route('channels.edit', ['type' => $type, 'channel' => $channel->id]));
    }

    /**
     * Update the specified channel.
     */
    public function update(Request $request, string $type, Channel $channel): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:channel_categories,id',
            'tvg_id' => 'nullable|string|max:255',
            'logo' => 'required',
            'url_channel' => 'required',
            'pssh' => 'nullable',
            'api_key' => 'nullable',
            'user_agent' => 'nullable',
            'manifest_type' => 'nullable',
            'license_type' => 'nullable',
            'catchup' => 'nullable',
            'catchup_days' => 'nullable',
            'catchup_source' => 'nullable',
            'catchup_pssh' => 'nullable',
            'catchup_api_key' => 'nullable',

            'is_active' => 'required|boolean',
            'apply_token' => 'required|boolean',
            'parental_control' => 'required|boolean',
        ]);

        $data['tvg_type'] = $type;
        $channel->update($data);

        flashSuccessMessage('Canal actualizado correctamente.');
        return jsonIframeRedirection(route('channels.edit', ['type' => $type, 'channel' => $channel->id]));
    }

    /**
     * Reorder channels.
     */
    public function reorder(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'order'         => 'required|array',
            'order.*.id'    => 'required|integer|exists:channels,id',
            'order.*.order' => 'required|integer|min:1',
        ]);

        foreach ($data['order'] as $item) {
            Channel::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Duplicate the specified channel.
     */
    public function duplicate(string $type, Channel $channel): JsonResponse
    {
        $newChannel = $channel->replicate();
        $newChannel->name .= ' (Copia)';
        $newChannel->save();

        flashSuccessMessage('Canal duplicado correctamente.');
        return jsonIframeRedirection(route('channels.edit', ['type' => $type, 'channel' => $newChannel->id]));
    }

    /**
     * Remove the specified channel.
     */
    public function destroy(string $type, Channel $channel): JsonResponse
    {
        $channel->delete();

        flashSuccessMessage('Canal eliminado correctamente.');
        return jsonIframeRedirection(route('channels.index', $type));
    }

    /**
     * Upload updated m3u
     */
    public function uploadM3U(Request $request)
    {
        $request->validate(['archivo' => 'required|file']);

        $file = $request->file('archivo');
        Storage::putFileAs('', $file, 'total_ott.m3u');

        return response()->json(['success' => true]);
    }

    public function importCategories()
    {
        try {
            $output = Artisan::call('import:channel-categories');
            $log = Artisan::output();
            return response()->json(['success' => 'Categorías importadas correctamente', 'log' => $log]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function importChannels()
    {
        try {
            $output = Artisan::call('import:channels');
            $log = Artisan::output();
            return response()->json(['success' => 'Canales importados correctamente', 'log' => $log]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function checkKeys(string $type, Channel $channel): JsonResponse
    {
        $username = config('services.iptv.token_account');
        $account = Account::where('username', $username)->first();
        $token = $account ? $account->token : null;

        $url = $channel->url_channel;
        $psshMsg = "No se ha actualizado el PSSH.";
        $keysMsg = "No se han actualizado las Keys.";
        $psshUpdated = false;
        $keysUpdated = false;
        $psshVal = $channel->pssh;
        $keysVal = $channel->api_key;

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => "El canal no tiene una URL de MPD definida."
            ]);
        }

        try {
            $pssh = Pssh::getFromUrl($url, (bool) $channel->apply_token ? $token : null);

            if ($pssh) {
                if ($pssh != $channel->pssh) {
                    $psshVal = $pssh;
                    $psshUpdated = true;
                    $psshMsg = "PSSH actualizado correctamente.";
                }

                $keys = SearchKeys::getKeys($pssh);
                if ($keys && $keys != $channel->api_key) {
                    $keysVal = $keys;
                    $keysUpdated = true;
                    $keysMsg = "Keys actualizadas correctamente.";
                } elseif (!$keys) {
                    $keysMsg = "No se han encontrado Keys para este PSSH.";
                }
            } else {
                $psshMsg = "No se ha podido extraer el PSSH del MPD.";
            }

            if ($psshUpdated || $keysUpdated) {
                if ($psshUpdated) $channel->pssh = $psshVal;
                if ($keysUpdated) $channel->api_key = $keysVal;
                $channel->save();

                $accounts = Account::all();
                foreach ($accounts as $acc) {
                    self::generateListsByType($channel->tvg_type, $acc);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ], 500);
        }

        $status = "info";
        if ($psshUpdated || $keysUpdated) {
            $status = "success";
        }

        return response()->json([
            'success' => true,
            'pssh_updated' => $psshUpdated,
            'keys_updated' => $keysUpdated,
            'pssh' => $psshVal,
            'api_key' => $keysVal,
            'status' => $status,
            'message' => $psshMsg . " " . $keysMsg
        ]);
    }

    /**
     * Check and update only catchup PSSH and keys for a channel.
     */
    public function checkCatchupKeys(string $type, Channel $channel): JsonResponse
    {
        $username = config('services.iptv.token_account');
        $account = Account::where('username', $username)->first();
        $token = $account ? $account->token : null;

        $catchupUrl = $channel->catchup_source;
        $catchupPsshMsg = "No se ha actualizado el catchup PSSH.";
        $catchupKeysMsg = "No se han actualizado las catchup Keys.";
        $catchupPsshUpdated = false;
        $catchupKeysUpdated = false;
        $catchupPsshVal = $channel->catchup_pssh;
        $catchupKeysVal = $channel->catchup_api_key;

        if (!$catchupUrl) {
            return response()->json([
                'success' => false,
                'message' => "El canal no tiene una URL de catchup definida."
            ]);
        }

        try {
            $catchupPssh = Pssh::getFromUrl($catchupUrl, (bool) $channel->apply_token ? $token : null, true);

            if ($catchupPssh) {
                if ($catchupPssh != $channel->catchup_pssh) {
                    $catchupPsshVal = $catchupPssh;
                    $catchupPsshUpdated = true;
                    $catchupPsshMsg = "Catchup PSSH actualizado correctamente.";
                }

                $catchupKeys = SearchKeys::getKeys($catchupPssh);
                if ($catchupKeys && $catchupKeys != $channel->catchup_api_key) {
                    $catchupKeysVal = $catchupKeys;
                    $catchupKeysUpdated = true;
                    $catchupKeysMsg = "Catchup Keys actualizadas correctamente.";
                } elseif (!$catchupKeys) {
                    $catchupKeysMsg = "No se han encontrado Keys para este catchup PSSH.";
                }
            } else {
                $catchupPsshMsg = "No se ha podido extraer el catchup PSSH del MPD.";
            }

            if ($catchupPsshUpdated || $catchupKeysUpdated) {
                if ($catchupPsshUpdated) $channel->catchup_pssh = $catchupPsshVal;
                if ($catchupKeysUpdated) $channel->catchup_api_key = $catchupKeysVal;
                $channel->save();

                $accounts = Account::all();
                foreach ($accounts as $acc) {
                    self::generateListsByType($channel->tvg_type, $acc);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ], 500);
        }

        $status = "info";
        if ($catchupPsshUpdated || $catchupKeysUpdated) {
            $status = "success";
        }

        return response()->json([
            'success' => true,
            'catchup_pssh_updated' => $catchupPsshUpdated,
            'catchup_keys_updated' => $catchupKeysUpdated,
            'catchup_pssh' => $catchupPsshVal,
            'catchup_api_key' => $catchupKeysVal,
            'status' => $status,
            'message' => $catchupPsshMsg . " " . $catchupKeysMsg
        ]);
    }

    /**
     * Detect the streaming platform from the channel URL.
     */
    private static function getPlatformFromUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        foreach (self::PLATFORM_PATTERNS as $platform => $patterns) {
            if ($patterns === null) continue;
            foreach ($patterns as $pattern) {
                if (str_contains($url, $pattern)) return $platform;
            }
        }

        return 'Movistar Plus+';
    }

    /**
     * Generate lists scoped by channel type.
     */
    public static function generateListsByType(string $type, Account $account): void
    {
        match ($type) {
            'live' => (function () use ($account) {
                Lists::generateTivimateList($account);
                Lists::generateOttList($account);
                Lists::generateKodiList($account);
            })(),
            'movie' => (function () use ($account) {
                Lists::generateCineList($account);
                Lists::generateCinePremiumList($account);
                Lists::generateCineOttList($account);
                Lists::generateCineOttpremiumList($account);
            })(),
            'series' => (function () use ($account) {
                Lists::generateSeriesList($account);
                Lists::generateSeriesPremiumList($account);
                Lists::generateSeriesOttList($account);
                Lists::generateSeriesOttpremiumList($account);
            })(),
        };
    }
}
