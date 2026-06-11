<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image_path',
    ];

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
}
