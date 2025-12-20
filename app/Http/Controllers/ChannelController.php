<?php

namespace App\Http\Controllers;

use App\Models\ChannelCategory;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class ChannelController extends Controller
{
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
    public function index(Request $request) : View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->getChannelsData($request);
        }
        
        return view('channels.index');
    }

    /**
     * Get channels data for DataTables AJAX request.
     */
    public function getChannelsData(Request $request) : JsonResponse
    {
        $query = Channel::with('category');
        
        // Handle search
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                  ->orWhere('tvg_id', 'like', "%{$searchValue}%")
                  ->orWhere('tvg_type', 'like', "%{$searchValue}%")
                  ->orWhereHas('category', function($q) use ($searchValue) {
                      $q->where('name', 'like', "%{$searchValue}%");
                  });
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
                case 'tvg_type':
                    $query->orderBy('tvg_type', $orderDirection);
                    break;
                default:
                    $query->orderBy('id', 'asc');
            }
        } else {
            $query->orderBy('id', 'asc');
        }

        $totalRecords = Channel::count();
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
                'apply_token' => $channel->apply_token ? 'Yes' : 'No',
                'is_active' => $channel->is_active ? 'Yes' : 'No',
                'tvg_type' => $channel->tvg_type ?? 'Undefined',
                'edit_url' => route('channels.edit', $channel->id),
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
    public function create() : View
    {
        $categories = ChannelCategory::orderBy('order')->get();
        return view('channels.create', compact('categories'));
    }

    /**
     * Show the form for editing the channel.
     */
    public function edit(Channel $channel) : View
    {
        $categories = ChannelCategory::orderBy('order')->get();
        return view('channels.edit', compact('channel', 'categories'));
    }

    /**
     * Store a new channel.
     */
    public function store(Request $request) : JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
			'tvg_type' => 'required',
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
            'is_active' => 'required|boolean',
            'apply_token' => 'required|boolean',
            'parental_control' => 'required|boolean',
        ]);

        $data['order'] = Channel::max('order') + 1;

        $channel = Channel::create($data);

        flashSuccessMessage('Channel created successfully.');
        return jsonIframeRedirection(route('channels.edit', $channel->id));
    }

    /**
     * Update the specified channel.
     */
    public function update(Request $request, Channel $channel) : JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
			'tvg_type' => 'required',
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
            'is_active' => 'required|boolean',
            'apply_token' => 'required|boolean',
            'parental_control' => 'required|boolean',
        ]);

        $channel->update($data);

        flashSuccessMessage('Channel updated successfully.');
        return jsonIframeRedirection(route('channels.edit', $channel->id));
    }

    /**
     * Reorder channels.
     */
    public function reorder(Request $request) : JsonResponse
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
    public function duplicate(Channel $channel) : JsonResponse
    {
        $newChannel = $channel->replicate();
        $newChannel->name .= ' (Copia)';
        $newChannel->save();

        flashSuccessMessage('Channel duplicated successfully.');
        return jsonIframeRedirection(route('channels.edit', $newChannel->id));
    }

    /**
     * Remove the specified channel.
     */
    public function destroy(Channel $channel) : JsonResponse
    {
        $channel->delete();

        flashSuccessMessage('Channel deleted successfully.');
        return jsonIframeRedirection(route('channels.index'));
    }
	
	    /**
     * Upload updated m3u
     */
	public function uploadM3U(Request $request)
	{
		$request->validate(['archivo' => 'required|file']);

		$file = $request->file('archivo');
		// Guarda o sobreescribe el archivo con nombre fijo
		Storage::putFileAs('', $file, 'total_ott.m3u');

		return response()->json(['success' => true]);
	}

	public function importCategories()
	{
		try {
			$output = Artisan::call('import:channel-categories');
			$log = Artisan::output();
			return response()->json(['success' => 'Categories imported successfully', 'log' => $log]);
		} catch (\Exception $e) {
			return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
		}
	}

	public function importChannels()
	{
		try {
			$output = Artisan::call('import:channels');
			$log = Artisan::output();
			return response()->json(['success' => 'Channels imported successfully', 'log' => $log]);
		} catch (\Exception $e) {
			return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
		}
	}


}
