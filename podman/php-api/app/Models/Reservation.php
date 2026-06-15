<?php

namespace App\Models;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * Camps que es poden assignar de forma massiva.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slot_id',
        'user_id',
        'service_id',
        'service_option_id',
        'employee_id',
        'note',
        'rating',
        'review',
        'review_images',
        'review_published',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'review_images' => 'array',
            'review_published' => 'boolean',
        ];
    }

    /**
     * Atributs calculats afegits a la serialització.
     *
     * @var list<string>
     */
    protected $appends = [
        'review_image_urls',
    ];

    /**
     * URLs públiques de les imatges de la valoració, en ordre.
     *
     * @return list<string>
     */
    public function getReviewImageUrlsAttribute(): array
    {
        return collect($this->review_images ?? [])
            ->map(fn (string $path): string => Storage::url($path))
            ->values()
            ->all();
    }

    /**
     * Franja reservada.
     *
     * @return BelongsTo<Slot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Servei de la reserva.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Usuari que ha fet la reserva.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Empleat escollit per fer el servei (o cap).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Opció del servei escollida (o cap).
     *
     * @return BelongsTo<ServiceOption, $this>
     */
    public function serviceOption(): BelongsTo
    {
        return $this->belongsTo(ServiceOption::class);
    }
}
