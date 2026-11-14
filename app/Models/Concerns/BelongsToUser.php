<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos de biblioteca que pertenecen a una cuenta: cada consulta se limita
 * a quien ha iniciado sesión y cada fila nueva queda a su nombre, así que
 * nadie ve ni toca los favoritos, listas o historial de otra persona.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            $userId = auth()->id();

            // Sin sesión no hay biblioteca: `where user_id = null` casaría filas sin dueño.
            $userId === null
                ? $query->whereRaw('1 = 0')
                : $query->where($query->qualifyColumn('user_id'), $userId);
        });

        static::creating(function (Model $model): void {
            $model->user_id ??= auth()->id();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
