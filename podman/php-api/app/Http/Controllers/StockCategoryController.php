<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\StockCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StockCategoryController extends Controller
{
    use ManagesImages;

    /**
     * L'admin crea una categoria d'stock nova (amb galeria d'imatges opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:stock_categories,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $paths = $this->syncImages($request, 'stock-categories');

        StockCategory::create([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'image_path' => $paths[0] ?? null,
            'images' => $paths ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria creada.']);

        return back();
    }

    /**
     * L'admin reanomena una categoria i/o reorganitza la seva galeria d'imatges.
     */
    public function update(Request $request, StockCategory $stockCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('stock_categories', 'name')->ignore($stockCategory->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $current = $stockCategory->images ?? ($stockCategory->image_path ? [$stockCategory->image_path] : []);
        $paths = $this->syncImages($request, 'stock-categories', $current);

        $stockCategory->name = trim($validated['name']);
        $stockCategory->description = $validated['description'] ?? null;
        $stockCategory->image_path = $paths[0] ?? null;
        $stockCategory->images = $paths ?: null;
        $stockCategory->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria actualitzada.']);

        return back();
    }

    /**
     * L'admin elimina una categoria (els seus articles queden sense categoria).
     */
    public function destroy(StockCategory $stockCategory): RedirectResponse
    {
        foreach ($stockCategory->images ?? ($stockCategory->image_path ? [$stockCategory->image_path] : []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $stockCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria eliminada.']);

        return back();
    }
}
