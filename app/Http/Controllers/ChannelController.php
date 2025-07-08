<?php

namespace App\Http\Controllers;

use App\Models\ChannelCategory;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
    public function index() : View
    {
        $channels = Channel::all();
        return view('channels.index', compact('channels'));
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
            'category_id' => 'required|exists:channel_categories,id',
            'tvg_id' => 'nullable|string|max:255',
            'logo' => 'required',
            'url_channel' => 'required',
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

        flashSuccessMessage('Canal creado correctamente.');
        return jsonIframeRedirection(route('channels.edit', $channel->id));
    }

    /**
     * Update the specified channel.
     */
    public function update(Request $request, Channel $channel) : JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:channel_categories,id',
            'tvg_id' => 'nullable|string|max:255',
            'logo' => 'required',
            'url_channel' => 'required',
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

        flashSuccessMessage('Canal actualizado correctamente.');
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

        flashSuccessMessage('Canal duplicado correctamente.');
        return jsonIframeRedirection(route('channels.edit', $newChannel->id));
    }

    /**
     * Remove the specified channel.
     */
    public function destroy(Channel $channel) : JsonResponse
    {
        $channel->delete();

        flashSuccessMessage('Canal eliminado correctamente.');
        return jsonIframeRedirection(route('channels.index'));
    }
}
