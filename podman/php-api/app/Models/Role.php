<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'screens',
    ];

    /**
     * Conversió de tipus dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screens' => 'array',
        ];
    }

    /**
     * Usuaris que tenen aquest rol de personal.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
