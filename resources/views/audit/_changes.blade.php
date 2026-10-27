@php
    $labels = config('audit.fields');
    $fmt = fn($v) => \Illuminate\Support\Str::limit(
        $v === null ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)),
        60,
    );
    $old = $log->old_values ?? [];
    $new = $log->new_values ?? [];
@endphp

@if (in_array($log->event, ['updated', 'voided'], true))
    @foreach ($new as $key => $value)
        <div class="small"><span class="text-muted">{{ $labels[$key] ?? $key }}:</span> {{ $fmt($old[$key] ?? null) }} →
            {{ $fmt($value) }}</div>
    @endforeach
@else
    @foreach (collect($new ?: $old)->take(5) as $key => $value)
        <div class="small"><span class="text-muted">{{ $labels[$key] ?? $key }}:</span> {{ $fmt($value) }}</div>
    @endforeach
@endif

@foreach ($log->meta ?? [] as $key => $value)
    <div class="small"><span class="text-muted">{{ $labels[$key] ?? $key }}:</span> {{ $fmt($value) }}</div>
@endforeach
