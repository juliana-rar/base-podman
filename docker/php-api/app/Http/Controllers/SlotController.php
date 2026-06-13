<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlotController extends Controller
{
    /**
     * Pàgina d'usuari: franges disponibles per reservar i reserves pròpies.
     */
    public function index(Request $request): Response
    {
        $available = Slot::query()
            ->doesntHave('reservation')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get(['id', 'starts_at', 'notes']);

        $myReservations = $request->user()
            ->reservations()
            ->with('slot:id,starts_at,notes', 'service:id,name')
            ->get()
            ->sortBy('slot.starts_at')
            ->values();

        return Inertia::render('Reservar', [
            'availableSlots' => $available,
            'myReservations' => $myReservations,
            'services' => Service::with('category:id,name,description,image_path')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'duration_minutes', 'description', 'image_path', 'service_category_id']),
        ]);
    }

    /**
     * Pàgina d'admin: gestió de totes les franges horàries.
     */
    public function manage(): Response
    {
        // Només les franges d'avui en endavant; les de dies passats no es mostren.
        $slots = Slot::query()
            ->with('reservation.user:id,name,email', 'reservation.service:id,name')
            ->whereDate('starts_at', '>=', now()->toDateString())
            ->orderBy('starts_at')
            ->get();

        // Franges per al resum/estadístiques: finestra amb passat i futur.
        $statsSlots = Slot::query()
            ->with('reservation.user:id,name,email', 'reservation.service:id,name')
            ->whereBetween('starts_at', [now()->subDays(61)->startOfDay(), now()->addDays(62)->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('admin/Horas', [
            'slots' => $slots,
            'statsSlots' => $statsSlots,
        ]);
    }

    /**
     * L'admin crea una nova franja horària disponible.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date', 'after:now', 'unique:slots,starts_at'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        Slot::create([
            'starts_at' => $validated['starts_at'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Franja afegida.']);

        return back();
    }

    /**
     * L'admin elimina una franja horària.
     */
    public function destroy(Slot $slot): RedirectResponse
    {
        $slot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Franja eliminada.']);

        return back();
    }
}
