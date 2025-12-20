<?php

namespace App\Http\Controllers;

use App\Models\ChannelHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request) : View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->getLogsData($request);
        }
        
        return view('logs.index');
    }

    private function getLogsData(Request $request) : JsonResponse
    {
        $query = ChannelHistory::with(['channel', 'user']);
        
        // Handle search
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function($q) use ($searchValue) {
                $q->whereHas('channel', function($q) use ($searchValue) {
                    $q->where('name', 'like', "%{$searchValue}%");
                })
                ->orWhere('pssh', 'like', "%{$searchValue}%")
                ->orWhere('api_key', 'like', "%{$searchValue}%");
            });
        }

        // Handle ordering
        if ($request->has('order')) {
            $orderColumn = $request->columns[$request->order[0]['column']]['data'];
            $orderDirection = $request->order[0]['dir'];
            
            switch ($orderColumn) {
                case 'channel':
                    $query->join('channels', 'channel_history.channel_id', '=', 'channels.id')
                          ->orderBy('channels.name', $orderDirection)
                          ->select('channel_history.*');
                    break;
                case 'created_at':
                    $query->orderBy('created_at', $orderDirection);
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $totalRecords = ChannelHistory::count();
        $totalFiltered = $query->count();

        // Handle pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;
        $logs = $query->skip($start)->take($length)->get();

        $data = [];
        foreach ($logs as $log) {
            $data[] = [
                'channel' => $log->channel->name ?? 'Deleted Channel',
                'pssh' => $log->pssh,
                'api_key' => $log->api_key,
                'created_by' => $log->user->name ?? 'System',
                'created_at' => $log->created_at->format('d/m/Y H:i:s'),
            ];
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
    }
}
