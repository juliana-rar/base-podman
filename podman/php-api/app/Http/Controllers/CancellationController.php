<?php

namespace App\Http\Controllers;

use App\Models\Cancellation;
use Inertia\Inertia;
use Inertia\Response;

class CancellationController extends Controller
{
    /**
     * Pàgina d'admin: llistat de les cancel·lacions amb el seu motiu.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Cancellacions', [
            'cancellations' => Cancellation::query()
                ->with('user:id,name,email,phone')
                ->latest()
                ->get(['id', 'user_id', 'service_name', 'slot_starts_at', 'note', 'reason', 'created_at']),
        ]);
    }
}
