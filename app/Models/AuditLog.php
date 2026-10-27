<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    // শুধু Audit ক্লাস থেকে তৈরি হয়, ব্যবহারকারীর ইনপুট কখনো এখানে আসে না
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // অডিট লগ লেখার পর আর বদলানো বা মোছা যায় না
        static::updating(fn() => throw new \LogicException('অডিট লগ বদলানো যায় না।'));
        static::deleting(fn() => throw new \LogicException('অডিট লগ মোছা যায় না।'));
    }
}
