<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SlideImage extends Model
{
    /** @use HasFactory<\Database\Factories\SlideImageFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'is_visible',
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
     * Casts dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    /**
     * URL pública de la imatge.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
