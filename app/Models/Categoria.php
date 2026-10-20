<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'activo', 'parent_id'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('nombre');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * @return list<int>
     */
    public static function idsWithDescendants(int $id): array
    {
        $ids = [$id];
        $pending = [$id];

        while ($pending !== []) {
            $children = static::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $pending);
        }

        return $ids;
    }
}
