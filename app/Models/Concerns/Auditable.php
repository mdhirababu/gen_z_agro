<?php

namespace App\Models\Concerns;

use App\Support\Audit;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn($model) => Audit::fromModel('created', $model));
        static::updated(fn($model) => Audit::fromModel('updated', $model));
        static::deleted(fn($model) => Audit::fromModel('deleted', $model));

        // SoftDeletes থাকলে ফিরিয়ে আনাও লগে যায়
        if (method_exists(static::class, 'restored')) {
            static::restored(fn($model) => Audit::fromModel('restored', $model));
        }
    }

    /** model-এ $auditOnly থাকলে শুধু সেই ক্ষেত্রগুলো লগে যায় */
    public function auditOnlyFields(): ?array
    {
        return property_exists($this, 'auditOnly') ? $this->auditOnly : null;
    }

    /** model-এ $auditIgnore থাকলে সেই ক্ষেত্রগুলো বাদ (বাকি সব লগে যায়) */
    public function auditIgnoredFields(): array
    {
        return property_exists($this, 'auditIgnore') ? $this->auditIgnore : [];
    }

    /** true হলে "active" থেকে অন্য অবস্থায় যাওয়াকে "বাতিল" ধরা হয় */
    public function auditVoidsOnStatus(): bool
    {
        return property_exists($this, 'auditVoidsOnStatus') && $this->auditVoidsOnStatus;
    }

    public function auditLabel(): string
    {
        $type = config('audit.types.' . class_basename($this), class_basename($this));
        $ref = $this->getAttribute('tag_number')
            ?: ($this->getAttribute('name') ?: ($this->getKey() ? '#' . $this->getKey() : ''));

        return trim($type . ' ' . $ref);
    }
}
