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
        'description',
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
     * URL pública de la imatge de l'opció (o null si no en té).
     */
    public function getUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
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
