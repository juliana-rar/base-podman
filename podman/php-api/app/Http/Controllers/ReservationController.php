<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\Cancellation;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\Slot;
use App\Models\Stock;
use App\Models\StockCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                ->with([
                    'user:id,name,email,phone',
                    'slot:id,starts_at,notes',
                    'service:id,name,price,vat_rate,service_category_id',
                    'service.category:id,name',
                    'serviceOption:id,name,price',
                    'stocks:id,name,price,vat_rate',
                ])
                ->latest()
                ->get(['id', 'slot_id', 'user_id', 'service_id', 'service_option_id', 'employee_id', 'note', 'created_at']),
            'services' => Service::with('options:id,service_id,name,price')
                ->orderBy('name')
                ->get(['id', 'name', 'price']),
            'employees' => Employee::orderBy('name')->get(['id', 'name']),
            // Catàleg d'stock (tots els articles, agrupats) per poder editar els productes
            // d'una reserva des del modal.
            'stockCategories' => StockCategory::with(['stocks' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (StockCategory $c) => $c->stocks->isNotEmpty())
                ->values()
                ->map(fn (StockCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'products' => $c->stocks->map(fn (Stock $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'price' => $s->price,
                        'quantity' => $s->quantity,
                    ]),
                ]),
            'uncategorizedStock' => Stock::whereNull('stock_category_id')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'quantity'])
                ->map(fn (Stock $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => $s->price,
                    'quantity' => $s->quantity,
                ]),
        ]);
    }

    /**
     * L'admin edita una reserva ja feta (servei, opció, empleat o nota poden canviar).
     */
    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_option_id' => [
                'nullable',
                'integer',
                Rule::exists('service_options', 'id')->where('service_id', $request->input('service_id')),
            ],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'note' => ['required', 'string', 'max:1000'],
            'products' => ['nullable', 'array'],
            'products.*.stock_id' => ['required_with:products', 'integer', 'distinct', 'exists:stocks,id'],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1'],
        ]);

        $reservation->update([
            'service_id' => $validated['service_id'],
            'service_option_id' => $validated['service_option_id'] ?? null,
            'employee_id' => $validated['employee_id'],
            'note' => $validated['note'],
        ]);

        $sync = [];

        foreach ($validated['products'] ?? [] as $product) {
            $sync[$product['stock_id']] = ['quantity' => $product['quantity']];
        }

        $reservation->stocks()->sync($sync);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva actualitzada.']);

        return back();
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
            'note' => ['nullable', 'string', 'max:1000'],
            'products' => ['nullable', 'array'],
            'products.*.stock_id' => ['required_with:products', 'integer', 'distinct', 'exists:stocks,id'],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1'],
        ]);

        $slot = Slot::findOrFail($validated['slot_id']);

        if ($slot->reservation()->exists()) {
            throw ValidationException::withMessages([
                'slot_id' => 'Aquesta franja ja està reservada.',
            ]);
        }

        $reservation = Reservation::create([
            'slot_id' => $slot->id,
            'user_id' => $request->user()->id,
            'service_id' => $validated['service_id'],
            'service_option_id' => $validated['service_option_id'] ?? null,
            'employee_id' => $validated['employee_id'],
            'note' => $validated['note'] ?? null,
        ]);

        $attach = $this->stockAttachments($validated['products'] ?? []);
        $reservation->stocks()->attach($attach);

        // Notificació automàtica amb els detalls de la reserva al fil de xat de l'usuari.
        Message::create([
            'user_id' => $reservation->user_id,
            'sender' => 'system',
            'body' => $this->reservationSummary($slot, $validated, $attach),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva feta!']);

        return back();
    }

    /**
     * Resum llegible amb els detalls d'una reserva per al missatge de xat.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, array{quantity: int}>  $attach
     */
    private function reservationSummary(Slot $slot, array $validated, array $attach): string
    {
        $service = Service::find($validated['service_id']);
        $option = ! empty($validated['service_option_id']) ? ServiceOption::find($validated['service_option_id']) : null;
        $employee = Employee::find($validated['employee_id']);

        $serviceLine = $service?->name ?? 'servei';
        if ($option) {
            $serviceLine .= ' ('.$option->name.')';
        }

        $lines = [
            '📅 Nova reserva',
            'Servei: '.$serviceLine,
            'Empleat: '.($employee?->name ?? '—'),
            'Data: '.Carbon::parse($slot->starts_at)->format('d/m/Y H:i'),
        ];

        if ($attach !== []) {
            $names = Stock::whereIn('id', array_keys($attach))->pluck('name', 'id');
            $products = [];
            foreach ($attach as $id => $pivot) {
                $products[] = $pivot['quantity'].'× '.($names[$id] ?? '');
            }
            $lines[] = 'Productes: '.implode(', ', $products);
        }

        if (! empty($validated['note'])) {
            $lines[] = 'Nota: '.$validated['note'];
        }

        return implode("\n", $lines);
    }

    /**
     * Prepara els productes a lligar a la reserva, limitant la quantitat a l'stock
     * disponible i descartant els que ja no en tinguin.
     *
     * @param  list<array{stock_id: int, quantity: int}>  $products
     * @return array<int, array{quantity: int}>
     */
    private function stockAttachments(array $products): array
    {
        $attach = [];

        foreach ($products as $product) {
            $stock = Stock::find($product['stock_id']);
            $quantity = min($product['quantity'], $stock?->quantity ?? 0);

            if ($quantity > 0) {
                $attach[$product['stock_id']] = ['quantity' => $quantity];
            }
        }

        return $attach;
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
