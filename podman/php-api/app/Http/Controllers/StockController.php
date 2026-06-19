<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\Stock;
use App\Models\StockCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    use ManagesImages;

    /**
     * Pàgina d'admin: gestió de l'stock, agrupat per categoria.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Stock', [
            'categories' => StockCategory::with(['stocks' => function ($query) {
                $query->orderBy('name');
            }])->orderBy('name')->get(['id', 'name', 'description', 'image_path', 'images']),
            'uncategorized' => Stock::whereNull('stock_category_id')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'vat_rate', 'quantity', 'description', 'image_path', 'images', 'stock_category_id']),
        ]);
    }

    /**
     * L'admin crea un article d'stock nou (amb galeria d'imatges opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:stocks,name'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'stock_category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            ...$this->imageRules(),
        ]);

        $paths = $this->syncImages($request, 'stocks');

        Stock::create([
            'name' => trim($validated['name']),
            'quantity' => $validated['quantity'],
            'price' => $validated['price'],
            'vat_rate' => $validated['vat_rate'] ?? 21,
            'description' => $validated['description'] ?? null,
            'stock_category_id' => $validated['stock_category_id'] ?? null,
            'image_path' => $paths[0] ?? null,
            'images' => $paths ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Article creat.']);

        return redirect()->route('admin.stock');
    }

    /**
     * L'admin edita un article i/o reorganitza la seva galeria d'imatges.
     */
    public function update(Request $request, Stock $stock): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:stocks,name,'.$stock->id],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'stock_category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            ...$this->imageRules(),
        ]);

        $current = $stock->images ?? ($stock->image_path ? [$stock->image_path] : []);
        $paths = $this->syncImages($request, 'stocks', $current);

        $stock->name = trim($validated['name']);
        $stock->quantity = $validated['quantity'];
        $stock->price = $validated['price'];
        $stock->vat_rate = $validated['vat_rate'] ?? 21;
        $stock->description = $validated['description'] ?? null;
        $stock->stock_category_id = $validated['stock_category_id'] ?? null;
        $stock->image_path = $paths[0] ?? null;
        $stock->images = $paths ?: null;
        $stock->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Article actualitzat.']);

        return redirect()->route('admin.stock');
    }

    /**
     * L'admin elimina un article d'stock.
     */
    public function destroy(Stock $stock): RedirectResponse
    {
        foreach ($stock->images ?? ($stock->image_path ? [$stock->image_path] : []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $stock->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Article eliminat.']);

        return redirect()->route('admin.stock');
    }
}
