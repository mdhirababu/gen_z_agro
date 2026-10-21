<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';

    protected $fillable = [
        'owner_user_id',
        'name',
        'phone',
        'address',
        'district',
        'logo_path',
        'currency',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function roleOf(User $user): ?string
    {
        return $this->users()
            ->where('users.id', $user->id)
            ->first()?->pivot->role;
    }

    /** প্ল্যানের সদস্য-সীমার মধ্যে আরেকজন যোগ করা যাবে কি না (মালিকসহ গণনা) */
    public function canAddMembers(): bool
    {
        $plan = $this->subscription?->plan;

        if (! $plan) {
            return false;
        }

        return $plan->max_members === null
            || $this->users()->count() < $plan->max_members;
    }

    public function cows(): HasMany
    {
        return $this->hasMany(Cow::class);
    }

    /** প্ল্যানের গরুর সীমার মধ্যে আরেকটি সক্রিয় গরু যোগ করা যাবে কি না */
    public function canAddCows(): bool
    {
        $plan = $this->subscription?->plan;

        if (! $plan) {
            return false;
        }

        return $plan->max_cows === null
            || $this->cows()->where('status', Cow::STATUS_ACTIVE)->count() < $plan->max_cows;
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function milkRecords(): HasMany
    {
        return $this->hasMany(MilkRecord::class);
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }
}
