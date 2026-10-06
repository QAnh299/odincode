<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Khoá chính dạng chuỗi theo chuẩn odin.sql: tiền tố + số thứ tự (LEAD001, ROLE01, ...).
 *
 * Model sử dụng trait cần khai báo:
 *   protected string $idPrefix = 'LEAD';
 *   protected int $idLength = 3;   // số chữ số phần đuôi
 */
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

    /**
     * Sinh mã tiếp theo, ví dụ LEAD012 -> LEAD013.
     */
    public static function nextId(): string
    {
        $model = new static;
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
}
