<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasStringId
{
    public function initializeHasStringId(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasStringId(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->getAttribute($model->getKeyName()))) {
                $model->setAttribute($model->getKeyName(), static::nextId());
            }
        });
    }

    public static function nextId(): string
    {
        $model = new static();
        $key = $model->getKeyName();
        $prefix = $model->idPrefix;

        $last = static::query()
            ->where($key, 'like', $prefix.'%')
            ->orderByRaw("LENGTH(`{$key}`) DESC")
            ->orderByDesc($key)
            ->value($key);

        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $number, $model->idLength, '0', STR_PAD_LEFT);
    }

    public static function nextIds(int $count): array
    {
        $model = new static();
        $prefix = $model->idPrefix;
        $start = (int) substr(static::nextId(), strlen($prefix));

        return array_map(
            fn ($i) => $prefix.str_pad((string) ($start + $i), $model->idLength, '0', STR_PAD_LEFT),
            range(0, max(0, $count - 1)),
        );
    }
}
