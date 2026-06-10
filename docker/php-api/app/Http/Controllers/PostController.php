<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Columnes carregades per als llistats (les imatges es necessiten per als
     * accessors cover_url / image_urls).
     *
     * @var list<string>
     */
    private array $columns = ['id', 'title', 'slug', 'body', 'cover_image', 'images', 'user_id', 'created_at'];

    /**
     * Pàgina pública de detall d'un post, amb portada i galeria.
     */
    public function show(Post $post): Response
    {
        return Inertia::render('PostDetail', [
            'post' => $post->load('author:id,name', 'tags:id,name,color'),
        ]);
    }

    /**
     * Pàgina d'admin: gestió dels posts i del catàleg d'etiquetes.
     */
    public function manage(): Response
    {
        return Inertia::render('admin/Posts', [
            'posts' => Post::with('author:id,name', 'tags:id,name,color')
                ->latest()
                ->get($this->columns),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * L'admin publica un nou post, opcionalment amb imatge de portada i galeria.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'max:5120'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $coverPath = $request->file('cover')?->store('posts', 'public');

        $imagePaths = [];
        foreach ($request->file('images', []) as $image) {
            $imagePaths[] = $image->store('posts', 'public');
        }

        $post = $request->user()->posts()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'cover_image' => $coverPath,
            'images' => $imagePaths,
        ]);

        $this->syncTags($post, $validated['tags'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Post publicat.']);

        return back();
    }

    /**
     * L'admin edita un post. Les imatges només es reemplacen si se'n pugen de noves.
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'max:5120'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $data = [
            'title' => $validated['title'],
            'body' => $validated['body'],
        ];

        // Si canvia el títol, regenerem el slug (URL) mantenint-lo únic.
        if ($post->title !== $validated['title']) {
            $data['slug'] = Post::uniqueSlug($validated['title'], $post->id);
        }

        if ($request->hasFile('cover')) {
            if ($post->cover_image) {
                Storage::disk('public')->delete($post->cover_image);
            }
            $data['cover_image'] = $request->file('cover')->store('posts', 'public');
        }

        if ($request->hasFile('images')) {
            if ($post->images) {
                Storage::disk('public')->delete($post->images);
            }

            $paths = [];
            foreach ($request->file('images') as $image) {
                $paths[] = $image->store('posts', 'public');
            }
            $data['images'] = $paths;
        }

        $post->update($data);

        $this->syncTags($post, $validated['tags'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Post actualitzat.']);

        return back();
    }

    /**
     * Vincula el post amb les etiquetes indicades pel nom, creant les que no existeixin.
     *
     * @param  list<string>  $names
     */
    private function syncTags(Post $post, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id)
            ->all();

        $post->tags()->sync($ids);
    }

    /**
     * L'admin elimina un post i les seves imatges.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $toDelete = array_filter([$post->cover_image, ...($post->images ?? [])]);

        if ($toDelete !== []) {
            Storage::disk('public')->delete($toDelete);
        }

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Post eliminat.']);

        return back();
    }
}
