<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Post;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\SlideImage;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Pàgina pública de presentació amb els posts publicats per l'admin i els serveis
     * que es poden reservar (els que tenen algun empleat assignat).
     */
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'posts' => Post::with('author:id,name', 'tags:id,name,color')
                ->latest()
                ->get(['id', 'title', 'slug', 'body', 'summary', 'cover_image', 'images', 'user_id', 'created_at']),
            'slides' => SlideImage::orderBy('id')->get(['id', 'path']),
            // Empleats que tenen fotos d'obres, per a la secció "Obres de…" del home.
            'employees' => Employee::whereNotNull('works')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'works', 'work_captions'])
                ->filter(fn (Employee $e) => ! empty($e->works))
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'slug' => $e->slug,
                    'description' => $e->description,
                    'work_urls' => $e->work_urls,
                    'work_captions' => $e->captionList(),
                ])
                ->values(),
            'services' => Service::whereHas('employees')
                ->with('category:id,name,image_path,images', 'options:id,service_id,name,price,duration_minutes,description,image_path,images')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'duration_minutes', 'description', 'image_path', 'images', 'service_category_id']),
            'reviews' => Reservation::query()
                ->whereNotNull('rating')
                ->where('review_published', true)
                ->with('user:id,name', 'service:id,name', 'employee:id,name', 'slot:id,starts_at')
                ->orderByDesc('updated_at')
                ->get(['id', 'user_id', 'service_id', 'employee_id', 'slot_id', 'rating', 'review', 'review_images', 'updated_at']),
        ]);
    }
}
