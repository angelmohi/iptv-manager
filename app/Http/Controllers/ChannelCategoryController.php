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
     * Display a listing of the categories.
     */
    public function index() : View
    {
        $categories = ChannelCategory::orderBy('order')->get();
        return view('channel-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create() : View
    {
        return view('channel-categories.create');
    }

    /**
     * Show the form for editing the category.
     */
    public function edit(ChannelCategory $category) : View
    {
        return view('channel-categories.edit', compact('category'));
    }

    /**
     * Store a new category.
     */
    public function store(Request $request) : JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
        ]);

        $category = ChannelCategory::create($data);

        flashSuccessMessage('Categoría creada correctamente.');
        return jsonIframeRedirection(route('channel-categories.edit', $category->id));
        
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, ChannelCategory $category) : JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'order' => 'required|integer',
        ]);

        $category->update($data);

        flashSuccessMessage('Categoría actualizada correctamente.');
        return jsonIframeRedirection(route('channel-categories.edit', $category->id));
    }

    /**
     * Remove the specified category.
     */
    public function destroy(ChannelCategory $category) : JsonResponse
    {
        if ($category->channels()->count() > 0) {
            flashDangerMessage('No se puede eliminar la categoría porque tiene canales asociados.');
            return jsonIframeRedirection(route('channel-categories.index'));
        }
        
        $category->delete();

        flashSuccessMessage('Categoría eliminada correctamente.');
        return jsonIframeRedirection(route('channel-categories.index'));
    }
}
