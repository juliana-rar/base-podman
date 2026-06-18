<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Stock extends Model
{
    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'quantity',
        'price',
        'description',
        'image_path',
        'images',
        'stock_category_id',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'images' => 'array',
        ];
    }

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
     * Categoria a la qual pertany l'article (o cap).
     *
     * @return BelongsTo<StockCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }
}
