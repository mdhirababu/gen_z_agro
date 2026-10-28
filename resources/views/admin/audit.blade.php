@extends('layouts.farm')
@section('title', 'সব খামারের অডিট লগ')

@section('content')
    @php
        $types = config('audit.types');
        $events = config('audit.events');
        $pills = [
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'voided' => 'red',
            'restored' => 'green',
            'exported' => 'gray',
            'login' => 'green',
            'login_failed' => 'orange',
            'lockout' => 'red',
            'password_reset' => 'blue',
        ];
    @endphp

    <div class="page-head">
        <div>
            <h1>সব খামারের অডিট লগ</h1>
            <p>সুপার অ্যাডমিনের জন্য। এই তালিকা কেউ বদলাতে বা মুছতে পারে না।</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <div>
            <label for="from" class="form-label small mb-1">শুরু</label>
            <input id="from" type="date" name="from" value="{{ $from }}" max="{{ now()->toDateString() }}"
                class="form-control">
        </div>
        <div>
            <label for="to" class="form-label small mb-1">শেষ</label>
            <input id="to" type="date" name="to" value="{{ $to }}"
                max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <div>
            <label for="farm" class="form-label small mb-1">খামার (id)</label>
            <input id="farm" type="number" min="1" name="farm" value="{{ $farmId }}"
                class="form-control" style="width: 110px">
        </div>
        <div>
            <label for="type" class="form-label small mb-1">কিসের</label>
            <select id="type" name="type" class="form-select">
                <option value="">সব</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="event" class="form-label small mb-1">কী হয়েছে</label>
            <select id="event" name="event" class="form-select">
                <option value="">সব</option>
                @foreach ($events as $key => $label)
                    <option value="{{ $key }}" @selected($event === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-primary">দেখুন</button>
    </form>

    @if ($logs->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-journal-text"></i>
                <h2>এই সময়ে কোনো রেকর্ড নেই</h2>
            </div>
        </div>
    @else
        <div class="table-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>সময়</th>
                            <th>খামার</th>
                            <th>কে</th>
                            <th>কী হয়েছে</th>
                            <th>বিস্তারিত</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->farm_id ? $farms[$log->farm_id] ?? '#' . $log->farm_id : '—' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $log->actor_name ?: 'সিস্টেম' }}</div>
                                    <div class="small text-muted">
                                        {{ $log->source === 'web' ? ($log->ip_address ?: 'ওয়েব') : 'স্বয়ংক্রিয়' }}</div>
                                </td>
                                <td>
                                    <span
                                        class="pill {{ $pills[$log->event] ?? 'gray' }}">{{ $events[$log->event] ?? $log->event }}</span>
                                    <div class="mt-1">
                                        {{ $log->subject ?: $types[$log->auditable_type] ?? $log->auditable_type }}</div>
                                </td>
                                <td>@include('audit._changes', ['log' => $log])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $logs->links() }}</div>
    @endif
@endsection
