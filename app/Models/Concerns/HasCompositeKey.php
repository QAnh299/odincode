<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositeKey
{
    protected function setKeysForSaveQuery($query): Builder
    {
        foreach ($this->compositeKey as $key) {
            $query->where($key, $this->getOriginal($key) ?? $this->getAttribute($key));
        }

        return $query;
    }

    protected function setKeysForSelectQuery($query): Builder
    {
        return $this->setKeysForSaveQuery($query);
    }
}
