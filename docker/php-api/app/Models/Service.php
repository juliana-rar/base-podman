<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'duration_minutes',
        'image_path',
        'service_category_id',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * Atributs calculats afegits a la serialització.
     *
     * @var list<string>
     */
    protected $appends = [
        'url',
    ];

    /**
     * URL pública de la imatge representativa (o null si no en té).
     */
    public function getUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    /**
     * Reserves d'aquest servei.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Categoria a la qual pertany el servei (o cap).
     *
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
