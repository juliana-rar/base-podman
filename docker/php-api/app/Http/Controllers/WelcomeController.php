<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Pàgina pública de presentació amb els posts publicats per l'admin.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'posts' => Post::with('author:id,name', 'tags:id,name,color')
                ->latest()
                ->get(['id', 'title', 'slug', 'body', 'cover_image', 'images', 'user_id', 'created_at']),
        ]);
    }
}
