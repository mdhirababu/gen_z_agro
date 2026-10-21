<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user) {
            return $user->is_super_admin ? true : null;
        });

        Relation::morphMap([
            'expense' => Expense::class,
        ]);

        Relation::morphMap([
            'expense' => Expense::class,
            'sale' => Sale::class,
        ]);
    }
}
