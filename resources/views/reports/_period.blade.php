@php $presets = \App\Support\ReportPeriod::presets(); @endphp

<div class="no-print mb-3">
    @if ($period->notice)
        <div class="alert alert-warning">{{ $period->notice }}</div>
    @endif

    <div class="tab-chips mb-2">
        @foreach ($presets as $key => $label)
            @if ($key !== 'custom')
                <a href="{{ route($route, [$farm, 'r' => $key]) }}"
                    class="tab-chip {{ $period->key === $key ? 'active' : '' }}">{{ $label }}</a>
            @endif
        @endforeach
    </div>

    <form method="GET" action="{{ route($route, $farm) }}" class="d-flex flex-wrap align-items-end gap-2">
        <input type="hidden" name="r" value="custom">
        <div>
            <label for="from" class="form-label small mb-1">শুরু</label>
            <input id="from" type="date" name="from" value="{{ $period->from->toDateString() }}"
                max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <div>
            <label for="to" class="form-label small mb-1">শেষ</label>
            <input id="to" type="date" name="to" value="{{ $period->to->toDateString() }}"
                max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <button class="btn btn-outline-primary">দেখুন</button>
    </form>

    <div class="small text-muted mt-2">সময়কাল: {{ $period->label }}</div>
</div>
