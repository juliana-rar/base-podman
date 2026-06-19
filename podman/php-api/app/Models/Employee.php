<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'slug',
        'image_path',
        'works',
        'description',
        'work_captions',
    ];

    /**
     * Genera automàticament un slug únic a partir del nom en crear l'empleat.
     */
    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if (blank($employee->slug)) {
                $employee->slug = static::uniqueSlug((string) $employee->name);
            }
        });
    }

    /**
     * Construeix un slug únic afegint un sufix numèric si cal.
     * Es pot ignorar un id (el del propi empleat en editar-lo).
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'empleat';
        $slug = $base;
        $i = 2;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'works' => 'array',
            'work_captions' => 'array',
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
     * Peus de foto de les obres, alineats a l'ordre de `works`
     * (cadena buida quan una obra no en té).
     *
     * @return list<string>
     */
    public function captionList(): array
    {
        $captions = $this->work_captions ?? [];

        return collect($this->works ?? [])
            ->map(fn (string $path): string => $captions[$path] ?? '')
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
