<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Hỗ trợ cập nhật / xoá bản ghi có khoá chính ghép (Eloquent không hỗ trợ sẵn).
 *
 * Model sử dụng trait cần khai báo:
 *   protected array $compositeKey = ['quotation_id', 'course_id'];
 */
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
