<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Pàgina de gestió del catàleg d'etiquetes.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Tags', [
            'tags' => Tag::withCount('posts')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Crea una etiqueta nova al catàleg (si no existeix ja).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        Tag::firstOrCreate(
            ['name' => trim($validated['name'])],
            ['color' => $validated['color'] ?? '#4f46e5'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiqueta creada.']);

        return back();
    }

    /**
     * Edita el color (i el nom) d'una etiqueta del catàleg.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('tags', 'name')->ignore($tag->id)],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiqueta actualitzada.']);

        return back();
    }

    /**
     * Elimina una etiqueta del catàleg (es desvincula de tots els posts).
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiqueta eliminada.']);

        return back();
    }
}
