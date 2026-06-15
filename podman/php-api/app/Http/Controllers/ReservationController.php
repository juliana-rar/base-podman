<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\Cancellation;
use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    use ManagesImages;

    /**
     * Historial de totes les reserves fetes (només admin).
     */
    public function history(): Response
    {
        return Inertia::render('admin/Historial', [
            'reservations' => Reservation::query()
                ->with(['user:id,name,email,phone', 'slot:id,starts_at,notes', 'service:id,name,price', 'serviceOption:id,price'])
                ->latest()
                ->get(['id', 'slot_id', 'user_id', 'service_id', 'service_option_id', 'note', 'created_at']),
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
            'service_option_id' => [
                'nullable',
                'integer',
                Rule::exists('service_options', 'id')->where('service_id', $request->input('service_id')),
            ],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
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
            'service_option_id' => $validated['service_option_id'] ?? null,
            'employee_id' => $validated['employee_id'],
            'note' => $validated['note'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva feta!']);

        return back();
    }

    /**
     * Llistat de valoracions fetes pels usuaris (només admin).
     */
    public function reviews(): Response
    {
        return Inertia::render('admin/ReservesAdmin', [
            'reviews' => Reservation::query()
                ->whereNotNull('rating')
                ->with(['user:id,name,email', 'slot:id,starts_at', 'service:id,name'])
                ->orderByDesc('updated_at')
                ->paginate(10, ['id', 'user_id', 'slot_id', 'service_id', 'rating', 'review', 'review_images', 'review_published', 'note', 'updated_at'])
                ->withQueryString(),
        ]);
    }

    /**
     * L'admin decideix si una valoració es mostra (o no) a la pàgina d'inici.
     */
    public function toggleReviewPublished(Reservation $reservation): RedirectResponse
    {
        if ($reservation->rating === null) {
            abort(404);
        }

        $reservation->review_published = ! $reservation->review_published;
        $reservation->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $reservation->review_published ? 'Valoració publicada a l\'inici.' : 'Valoració retirada de l\'inici.',
        ]);

        return back();
    }

    /**
     * L'usuari valora una reserva ja feta: puntuació, comentari i imatges.
     */
    public function review(Request $request, Reservation $reservation): RedirectResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            abort(403);
        }

        $reservation->loadMissing('slot:id,starts_at');

        if (! $reservation->slot || $reservation->slot->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'rating' => 'Només es poden valorar les reserves ja fetes.',
            ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $paths = $this->syncImages($request, 'reviews', $reservation->review_images ?? []);

        $reservation->rating = $validated['rating'];
        $reservation->review = $validated['review'] ?? null;
        $reservation->review_images = $paths ?: null;
        $reservation->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Valoració desada.']);

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

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $reservation->loadMissing('slot:id,starts_at', 'service:id,name');

        Cancellation::create([
            'user_id' => $reservation->user_id,
            'service_name' => $reservation->service?->name,
            'slot_starts_at' => $reservation->slot?->starts_at,
            'note' => $reservation->note,
            'reason' => $validated['reason'],
        ]);

        $reservation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva cancel·lada.']);

        return back();
    }
}
