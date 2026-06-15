<?php

namespace App\Http\Controllers;

use App\Models\SlideImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SlideImageController extends Controller
{
    /**
     * Pàgina d'admin: gestió de les imatges del carrusel de presentació.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Imatges', [
            'images' => SlideImage::orderBy('id')->get(['id', 'path', 'is_visible']),
        ]);
    }

    /**
     * L'admin puja una o més imatges al carrusel.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['image', 'max:5120'],
        ]);

        foreach ($request->file('images') as $image) {
            SlideImage::create([
                'path' => $image->store('slides', 'public'),
                'is_visible' => true,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imatges afegides.']);

        return redirect()->route('admin.imatges');
    }

    /**
     * Mostra/amaga una imatge al carrusel de presentació.
     */
    public function update(Request $request, SlideImage $image): RedirectResponse
    {
        $request->validate(['is_visible' => ['required', 'boolean']]);

        $image->update(['is_visible' => $request->boolean('is_visible')]);

        return redirect()->route('admin.imatges');
    }

    /**
     * Elimina una imatge del carrusel.
     */
    public function destroy(SlideImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imatge eliminada.']);

        return redirect()->route('admin.imatges');
    }
}
