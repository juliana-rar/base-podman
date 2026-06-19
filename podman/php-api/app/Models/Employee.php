<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image_path',
        'works',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'works' => 'array',
        ];
    }

    /**
     * Atributs calculats afegits a la serialització.
     *
     * @var list<string>
     */
    protected $appends = [
        'url',
        'work_urls',
    ];

    /**
     * URL pública de la imatge de l'empleat (o null si no en té).
     */
    public function getUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    /**
     * URLs públiques de les fotos d'obres de l'empleat, en ordre.
     *
     * @return list<string>
     */
    public function getWorkUrlsAttribute(): array
    {
        return collect($this->works ?? [])
            ->map(fn (string $path): string => Storage::url($path))
            ->values()
            ->all();
    }

    /**
     * Serveis que pot fer aquest empleat.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * Opcions de serveis que fa aquest empleat.
     *
     * @return BelongsToMany<ServiceOption, $this>
     */
    public function serviceOptions(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOption::class);
    }
}
