<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StockCategory extends Model
{
    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'image_path',
        'images',
    ];

    /**
     * Atributs calculats afegits a la serialització.
     *
     * @var list<string>
     */
    protected $appends = [
        'url',
        'image_urls',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }

    /**
     * URL pública de la imatge de portada (o null si no en té).
     */
    public function getUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    /**
     * URLs públiques de tota la galeria, en ordre (la primera és la portada).
     *
     * @return list<string>
     */
    public function getImageUrlsAttribute(): array
    {
        $paths = ! empty($this->images) ? $this->images : ($this->image_path ? [$this->image_path] : []);

        return collect($paths)
            ->map(fn (string $path): string => Storage::url($path))
            ->values()
            ->all();
    }

    /**
     * Articles d'stock que pertanyen a aquesta categoria.
     *
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
