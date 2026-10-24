<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BreedingController;
use App\Http\Controllers\CowController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\FarmMemberController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MilkController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WeightRecordController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/', fn() => auth()->check()
    ? redirect()->route('farms.index')
    : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/home', fn() => redirect()->route('farms.index'))->name('home');

    // খামার তালিকা ও তৈরি
    Route::resource('farms', FarmController::class)
        ->only(['index', 'create', 'store']);

    // নির্দিষ্ট খামার: সদস্য না হলে 404, মেয়াদ শেষ হলে read-only
    Route::middleware(['farm.access', 'farm.subscription'])->group(function () {
        Route::resource('farms', FarmController::class)
            ->only(['show', 'edit', 'update', 'destroy']);

        Route::prefix('farms/{farm}')->name('farms.')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');

            Route::get('members', [FarmMemberController::class, 'index'])
                ->name('members.index');
            Route::post('members', [FarmMemberController::class, 'store'])
                ->name('members.store');
            Route::delete('members/{user}', [FarmMemberController::class, 'destroy'])
                ->name('members.destroy');

            Route::get('billing', [BillingController::class, 'index'])
                ->name('billing.index');
            Route::post('billing/payments', [BillingController::class, 'store'])
                ->name('billing.store');

            // দুধ উৎপাদন: যেকোনো সদস্য
            Route::get('milk', [MilkController::class, 'index'])->name('milk.index');
            Route::get('milk/entry', [MilkController::class, 'entry'])->name('milk.entry');
            Route::post('milk/entry', [MilkController::class, 'save'])->name('milk.save');

            // গরু ও ওজন: scopeBindings থাকায় {cow} অবশ্যই এই খামারের হতে হবে, নইলে 404
            Route::scopeBindings()->group(function () {
                Route::get('cows', [CowController::class, 'index'])->name('cows.index');
                Route::get('cows/create', [CowController::class, 'create'])->name('cows.create');
                Route::post('cows', [CowController::class, 'store'])->name('cows.store');
                Route::get('cows/{cow}', [CowController::class, 'show'])->name('cows.show');
                Route::get('cows/{cow}/edit', [CowController::class, 'edit'])->name('cows.edit');
                Route::put('cows/{cow}', [CowController::class, 'update'])->name('cows.update');
                Route::delete('cows/{cow}', [CowController::class, 'destroy'])->name('cows.destroy');

                Route::post('cows/{cow}/weights', [WeightRecordController::class, 'store'])
                    ->name('cows.weights.store');
                Route::delete('cows/{cow}/weights/{weight}', [WeightRecordController::class, 'destroy'])
                    ->name('cows.weights.destroy');
            });
            // খরচ ও পেমেন্ট: শুধু মালিক ও ম্যানেজার (controller-এ যাচাই হয়)
            Route::scopeBindings()->group(function () {
                Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
                Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
                Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
                Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
                Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');

                Route::post('expenses/{expense}/payments', [PaymentController::class, 'storeForExpense'])
                    ->name('expenses.payments.store');
                Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

                Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
                Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
                Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
                Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
                Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
                Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
                Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
                Route::post('sales/{sale}/payments', [PaymentController::class, 'storeForSale'])->name('sales.payments.store');

                Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
                Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
                Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
                Route::post('customers/{customer}/receive', [PaymentController::class, 'receiveForCustomer'])->name('customers.receive');
            });

            // স্বাস্থ্য: {healthEvent} অবশ্যই এই খামারের হতে হবে, নইলে 404
            Route::scopeBindings()->group(function () {
                Route::get('health', [HealthController::class, 'index'])->name('health.index');
                Route::get('health/create', [HealthController::class, 'create'])->name('health.create');
                Route::get('health/tasks', [HealthController::class, 'tasks'])->name('health.tasks');
                Route::post('health', [HealthController::class, 'store'])->name('health.store');
                Route::get('health/{healthEvent}/edit', [HealthController::class, 'edit'])->name('health.edit');
                Route::put('health/{healthEvent}', [HealthController::class, 'update'])->name('health.update');
                Route::delete('health/{healthEvent}', [HealthController::class, 'destroy'])->name('health.destroy');
                Route::post('health/{healthEvent}/recover', [HealthController::class, 'recover'])->name('health.recover');
                Route::post('health/{healthEvent}/done', [HealthController::class, 'done'])->name('health.done');
            });

            // প্রজনন: {breedingEvent} অবশ্যই এই খামারের হতে হবে, নইলে 404
            Route::scopeBindings()->group(function () {
                Route::get('breeding', [BreedingController::class, 'index'])->name('breeding.index');
                Route::get('breeding/create', [BreedingController::class, 'create'])->name('breeding.create');
                Route::post('breeding', [BreedingController::class, 'store'])->name('breeding.store');
                Route::get('breeding/{breedingEvent}', [BreedingController::class, 'show'])->name('breeding.show');
                Route::get('breeding/{breedingEvent}/edit', [BreedingController::class, 'edit'])->name('breeding.edit');
                Route::put('breeding/{breedingEvent}', [BreedingController::class, 'update'])->name('breeding.update');
                Route::delete('breeding/{breedingEvent}', [BreedingController::class, 'destroy'])->name('breeding.destroy');
                Route::post('breeding/{breedingEvent}/check', [BreedingController::class, 'check'])->name('breeding.check');
                Route::post('breeding/{breedingEvent}/calve', [BreedingController::class, 'calve'])->name('breeding.calve');
                Route::post('breeding/{breedingEvent}/abort', [BreedingController::class, 'abortPregnancy'])->name('breeding.abort');
            });

            // খাবার: {feedRecord}, {feedType} অবশ্যই এই খামারের হতে হবে, নইলে 404
            Route::scopeBindings()->group(function () {
                Route::get('feed', [FeedController::class, 'index'])->name('feed.index');
                Route::post('feed', [FeedController::class, 'store'])->name('feed.store');
                Route::delete('feed/{feedRecord}', [FeedController::class, 'destroy'])->name('feed.destroy');

                Route::get('feed/types', [FeedController::class, 'types'])->name('feed.types');
                Route::post('feed/types', [FeedController::class, 'storeType'])->name('feed.types.store');
                Route::post('feed/types/defaults', [FeedController::class, 'defaults'])->name('feed.types.defaults');
                Route::put('feed/types/{feedType}', [FeedController::class, 'updateType'])->name('feed.types.update');
                Route::post('feed/types/{feedType}/toggle', [FeedController::class, 'toggleType'])->name('feed.types.toggle');
            });

            // SMS বিজ্ঞপ্তির সেটিং (শুধু মালিক)
            Route::get('notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
            Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
            Route::post('notifications/test', [NotificationController::class, 'test'])->name('notifications.test');
        });
    });

    // Super Admin প্যানেল
    Route::middleware('super.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('index');

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])
            ->name('payments.approve');
        Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])
            ->name('payments.reject');

        Route::get('plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
        Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
    });
});
