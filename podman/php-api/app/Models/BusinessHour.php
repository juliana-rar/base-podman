<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessHourFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'weekday',
        'closed',
        'opens',
        'closes',
    ];

    /**
     * Casts dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed' => 'boolean',
        ];
    }
}
