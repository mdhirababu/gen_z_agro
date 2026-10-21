<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * বর্তমান খামার (app('currentFarm')) ঠিক থাকলে সব query ওই খামারে সীমাবদ্ধ হয়।
 * কনসোল বা অ্যাডমিনের কাজে দরকার হলে ->withoutGlobalScope('farm') ব্যবহার করবে।
 */
trait BelongsToFarm
{
    protected static function bootBelongsToFarm(): void
    {
        static::addGlobalScope('farm', function (Builder $builder) {
            if (app()->bound('currentFarm')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.farm_id',
                    app('currentFarm')->id
                );
            }
        });

        static::creating(function (Model $model) {
            if (! $model->farm_id && app()->bound('currentFarm')) {
                $model->farm_id = app('currentFarm')->id;
            }
        });
    }
}
