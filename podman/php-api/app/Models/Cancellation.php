<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cancellation extends Model
{
    /** @use HasFactory<\Database\Factories\CancellationFactory> */
    use HasFactory;

    /**
     * Camps assignables.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'service_name',
        'slot_starts_at',
        'note',
        'reason',
    ];

    /**
     * Casts dels atributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_starts_at' => 'datetime',
        ];
    }

    /**
     * Usuari que va cancel·lar la reserva.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
