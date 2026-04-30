<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function index(): View
    {
        return view('channels.catalogue');
    }

	public function data(Request $request, string $type): JsonResponse
	{
		// Query base
		$query = Channel::with('category')->where('tvg_type', $type);

		if ($type !== 'live') {
			$query->where('is_active', true);
		}

		// Filtro plataforma
		if ($type !== 'live' && $request->filled('platform')) {
			$platform = $request->platform;
			if (array_key_exists($platform, ChannelController::PLATFORM_PATTERNS)) {
				$patterns = ChannelController::PLATFORM_PATTERNS[$platform];
				if ($patterns === null) {
					$allPatterns = array_merge(...array_filter(array_values(ChannelController::PLATFORM_PATTERNS)));
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
		}

		// Búsqueda
		if (!empty($request->search['value'])) {
			$search = '%' . $request->search['value'] . '%';
			$query->where(function ($q) use ($search) {
				$q->whereRaw('channels.name COLLATE utf8mb4_unicode_ci LIKE ?', [$search])
				  ->orWhereHas('category', function ($q2) use ($search) {
					  $q2->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', [$search]);
				  });
			});
		}

		// Total sin ordenar (antes del join)
		$totalQuery = Channel::where('tvg_type', $type);
		if ($type !== 'live') $totalQuery->where('is_active', true);
		$total    = $totalQuery->count();
		$filtered = $query->count(); // antes del join de ordenación
		
		// Contador especial para el header
		$seriesCount = ($type === 'series')
		? Channel::where('tvg_type', 'series')->where('is_active', true)->distinct('category_id')->count('category_id')
		: null;

		// Ordenación (el join solo afecta al SELECT final, no al count)
		if ($request->has('order')) {
			$col = $request->columns[$request->order[0]['column']]['data'] ?? 'name';
			$dir = $request->order[0]['dir'] ?? 'asc';
			if ($col === 'category') {
				$query->join('channel_categories', 'channels.category_id', '=', 'channel_categories.id')
					  ->orderBy('channel_categories.name', $dir)
					  ->select('channels.*');
			} elseif ($col === 'is_active') {
				$query->orderBy('is_active', $dir);
			} else {
				$query->orderBy('channels.name', $dir);
			}
		} else {
			$query->orderBy('channels.name', 'asc');
		}

		$channels = $query->skip($request->start ?? 0)->take($request->length ?? 25)->get();

		$data = $channels->map(function ($ch) use ($type) {
			$row = [
				'name'     => $ch->name,
				'category' => $ch->category->name ?? '—',
			];
			if ($type === 'live') {
				$row['is_active'] = $ch->is_active;
			} else {
				$row['platform'] = self::getPlatformLabel($ch->url_channel);
			}
			return $row;
		});

		return response()->json([
			'draw'            => intval($request->draw),
			'recordsTotal'    => $total,
			'recordsFiltered' => $filtered,
			'data'            => $data,
			'seriesCount'     => $seriesCount,
		]);
	}

    private static function getPlatformLabel(?string $url): string
    {
        if (empty($url)) return 'Movistar Plus+';
        foreach (ChannelController::PLATFORM_PATTERNS as $platform => $patterns) {
            if ($patterns === null) continue;
            foreach ($patterns as $p) {
                if (str_contains($url, $p)) return $platform;
            }
        }
        return 'Movistar Plus+';
    }
}