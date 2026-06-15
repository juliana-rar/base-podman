<?php

namespace App\Models;

use Database\Factories\ServiceOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class ServiceOption extends Model
{
    /** @use HasFactory<ServiceOptionFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'name',
        'price',
        'duration_minutes',
        'description',
        'image_path',
        'images',
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
     * URL pública de la imatge de portada de l'opció (o null si no en té).
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
     * Servei al qual pertany l'opció.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Empleats que fan aquesta opció.
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }
}
