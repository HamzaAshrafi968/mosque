<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UuidTrait
{
    public function initializeUuidTrait(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootUuidTrait(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
