<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Audit
{
    private const ALWAYS_IGNORE = ['created_at', 'updated_at', 'deleted_at'];

    private const SENSITIVE = ['password', 'remember_token', 'token', 'api_key', 'secret', 'otp', 'pin'];

    /** সরাসরি লগ লেখা (যেমন রিপোর্ট ডাউনলোড)। ব্যর্থ হলেও মূল কাজ আটকায় না। */
    public static function log(
        string $event,
        ?int $farmId,
        string $type,
        ?int $id = null,
        ?string $subject = null,
        array $old = [],
        array $new = [],
        array $meta = [],
    ): void {
        if (! config('audit.enabled', true)) {
            return;
        }

        try {
            $user = auth()->user();
            $web = request()->route() !== null;

            AuditLog::create([
                'farm_id' => $farmId,
                'user_id' => $user?->id,
                'actor_name' => $user ? Str::limit((string) $user->name, 100, '') : null,
                'event' => $event,
                'auditable_type' => $type,
                'auditable_id' => $id,
                'subject' => $subject ? Str::limit($subject, 150, '') : null,
                'old_values' => self::clean($old) ?: null,
                'new_values' => self::clean($new) ?: null,
                'meta' => self::clean($meta) ?: null,
                'ip_address' => $web ? request()->ip() : null,
                'user_agent' => $web ? Str::limit((string) request()->userAgent(), 255, '') : null,
                'source' => $web ? 'web' : 'console',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Auditable model-এর ইভেন্ট থেকে লগ */
    public static function fromModel(string $event, Model $model): void
    {
        if (! config('audit.enabled', true)) {
            return;
        }

        try {
            $old = [];
            $new = [];

            if ($event === 'updated') {
                foreach ($model->getChanges() as $key => $value) {
                    if (! self::tracked($model, (string) $key)) {
                        continue;
                    }

                    $old[$key] = $model->getRawOriginal($key);
                    $new[$key] = $value;
                }

                if ($new === []) {
                    return; // শুধু ঘোরানো বা সময়ের ক্ষেত্র বদলেছে, লগ লাগবে না
                }

                if (
                    $model->auditVoidsOnStatus()
                    && array_key_exists('status', $new)
                    && ($old['status'] ?? null) === 'active'
                    && $new['status'] !== 'active'
                ) {
                    $event = 'voided';
                }
            } elseif ($event === 'created') {
                $new = self::trackedAttributes($model);
            } elseif ($event === 'deleted') {
                $old = self::trackedAttributes($model);
            }

            $farmId = $model instanceof Farm ? $model->getKey() : $model->getAttribute('farm_id');

            self::log(
                $event,
                $farmId ? (int) $farmId : null,
                class_basename($model),
                $model->getKey() ? (int) $model->getKey() : null,
                $model->auditLabel(),
                $old,
                $new,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function tracked(Model $model, string $key): bool
    {
        if (in_array($key, self::ALWAYS_IGNORE, true)) {
            return false;
        }

        $only = $model->auditOnlyFields();

        if ($only !== null) {
            return in_array($key, $only, true);
        }

        return ! in_array($key, $model->auditIgnoredFields(), true);
    }

    private static function trackedAttributes(Model $model): array
    {
        $out = [];

        foreach ($model->getAttributes() as $key => $value) {
            if (self::tracked($model, (string) $key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** গোপন ও সময়ের ক্ষেত্র সরানো, মান ছোট ও সাধারণ করা */
    private static function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $lower = strtolower((string) $key);

            if (in_array($lower, self::ALWAYS_IGNORE, true) || in_array($lower, self::SENSITIVE, true)) {
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($value)) {
                $value = Str::limit($value, 500, '…');
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
