<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Listeners\AuditAuthEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use App\Support\QueryMonitor;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, function () {
            return config('sms.driver') === 'http'
                ? new HttpSmsGateway(config('sms.http'))
                : new LogSmsGateway();
        });
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

        // পাসওয়ার্ড: ন্যূনতম ৮ অক্ষর, অন্তত একটা অক্ষর ও একটা সংখ্যা
        Password::defaults(fn() => Password::min(8)->letters()->numbers());

        // লগইন, ভুল চেষ্টা, আটকানো ও পাসওয়ার্ড রিসেট অডিট লগে
        Event::subscribe(AuditAuthEvents::class);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // তারিখের পরিসর: whereDate কলামে DATE() বসায় বলে MySQL ইনডেক্স ব্যবহার করতে পারে না।
        // এটা "শুরু <= তারিখ < শেষের পরের দিন" দেয়, ইনডেক্স কাজ করে, SQLite-এও ঠিক চলে।
        EloquentBuilder::macro('whereDateBetween', function (string $column, $from, $to) {
            return $this
                ->where($column, '>=', Carbon::parse($from)->toDateString())
                ->where($column, '<', Carbon::parse($to)->addDay()->toDateString());
        });

        // ধীর query লগে ধরা (SQL যায়, মান বা বাইন্ডিং যায় না)
        DB::listen(fn($query) => QueryMonitor::handle($query));

        // N+1 query ধরা: ব্যতিক্রম ছোঁড়ে না, শুধু লগে লেখে (প্রোডাকশনে বন্ধ)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function ($model, string $relation) {
            logger()->warning('N+1: ' . $model::class . '::' . $relation, ['path' => request()->path()]);
        });
    }
}
