<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    /**
     * Historial de totes les reserves fetes (només admin).
     */
    public function history(): Response
    {
        return Inertia::render('admin/Historial', [
            'reservations' => Reservation::query()
                ->with(['user:id,name,email', 'slot:id,starts_at,notes', 'service:id,name'])
                ->latest()
                ->get(['id', 'slot_id', 'user_id', 'service_id', 'note', 'created_at']),
        ]);
    }

    /**
     * L'usuari reserva una franja horària disponible.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $slot = Slot::findOrFail($validated['slot_id']);

        if ($slot->reservation()->exists()) {
            throw ValidationException::withMessages([
                'slot_id' => 'Aquesta franja ja està reservada.',
            ]);
        }

        Reservation::create([
            'slot_id' => $slot->id,
            'user_id' => $request->user()->id,
            'service_id' => $validated['service_id'],
            'note' => $validated['note'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva feta!']);

        return back();
    }

    /**
     * L'usuari (o l'admin) cancel·la una reserva.
     */
    public function destroy(Request $request, Reservation $reservation): RedirectResponse
    {
        if ($reservation->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $reservation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva cancel·lada.']);

        return back();
    }
}
