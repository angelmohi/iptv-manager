<?php

namespace App\Http\Controllers;

use App\Models\ChannelCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelCategoryController extends Controller
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
     * Show the form for creating a new category.
     */
    public function create(string $type): View
    {
        $config = ChannelController::TYPE_CONFIG[$type];
        return view('channel-categories.create', compact('type', 'config'));
    }

    /**
     * Show the form for editing the category.
     */
    public function edit(string $type, ChannelCategory $category): View
    {
        $config = ChannelController::TYPE_CONFIG[$type];
        return view('channel-categories.edit', compact('category', 'type', 'config'));
    }

    /**
     * Store a new category.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $data['type'] = $type;
        $data['order'] = ChannelCategory::max('order') + 1;

        $category = ChannelCategory::create($data);

        flashSuccessMessage('Categoría creada correctamente.');
        return jsonIframeRedirection(route('channel-categories.edit', ['type' => $type, 'category' => $category->id]));
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, string $type, ChannelCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($data);

        flashSuccessMessage('Categoría actualizada correctamente.');
        return jsonIframeRedirection(route('channel-categories.edit', ['type' => $type, 'category' => $category->id]));
    }

    /**
     * Remove the specified category.
     */
    public function destroy(string $type, ChannelCategory $category): JsonResponse
    {
        if ($category->channels()->count() > 0) {
            flashDangerMessage('No se puede eliminar la categoría porque tiene canales asociados.');
            return jsonIframeRedirection(route('channel-categories.edit', ['type' => $type, 'category' => $category->id]));
        }

        $category->delete();

        flashSuccessMessage('Categoría eliminada correctamente.');
        return jsonIframeRedirection(route('channels.index', $type));
    }

    /**
     * Reorder categories.
     */
    public function reorder(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer|exists:channel_categories,id',
            'order.*.order' => 'required|integer|min:1',
        ]);

        foreach ($data['order'] as $item) {
            ChannelCategory::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
