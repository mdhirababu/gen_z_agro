@extends('layouts.farm')
@section('title', 'দৈনিক দুধ এন্ট্রি')

@section('content')
    <div class="page-head">
        <div>
            <h1>দৈনিক দুধ এন্ট্রি</h1>
            <p>{{ $date->format('d/m/Y') }}{{ $date->isToday() ? ', আজ' : '' }}: প্রতিটি গাভির প্রতি বারের দুধ লিটারে লিখুন।
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route('farms.milk.entry', [$farm, 'date' => $prev]) }}" class="icon-btn" title="আগের দিন"
                aria-label="আগের দিন"><i class="bi bi-chevron-left"></i></a>
            <form method="GET" action="{{ route('farms.milk.entry', $farm) }}">
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}"
                    class="form-control" onchange="this.form.submit()" aria-label="তারিখ">
            </form>
            @if ($next)
                <a href="{{ route('farms.milk.entry', [$farm, 'date' => $next]) }}" class="icon-btn" title="পরের দিন"
                    aria-label="পরের দিন"><i class="bi bi-chevron-right"></i></a>
            @endif
            <a href="{{ route('farms.milk.index', $farm) }}" class="btn btn-outline-secondary"><i
                    class="bi bi-bar-chart me-1"></i>সারসংক্ষেপ</a>
        </div>
    </div>

    @if ($cows->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-droplet-half"></i>
                <h2>দুধ দেওয়ার মতো কোনো গাভি নেই</h2>
                <p>গরুর তালিকায় সক্রিয় মাদি গরুর ধরন "দুধ দিচ্ছে" রাখলে এখানে দেখা যাবে।</p>
                <a href="{{ route('farms.cows.index', $farm) }}" class="btn btn-primary">গরুর তালিকায় যান</a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('farms.milk.save', $farm) }}" x-data="{
            rows: @js(old('rows', $initial)),
            t(id) {
                const r = this.rows[id] || {};
                return (parseFloat(r[1]) || 0) + (parseFloat(r[2]) || 0) + (parseFloat(r[3]) || 0);
            },
            col(n) {
                return Object.keys(this.rows).reduce((s, id) => s + (parseFloat((this.rows[id] || {})[n]) || 0), 0);
            },
            get grand() { return Object.keys(this.rows).reduce((s, id) => s + this.t(id), 0); },
            fmt(n) { return (Math.round(n * 100) / 100).toString(); }
        }">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">

            <div class="table-card mb-3">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>গাভি</th>
                                @foreach (config('farm.milk_sessions', [1 => 'প্রথম বার', 2 => 'দ্বিতীয় বার', 3 => 'তৃতীয় বার']) as $n => $label)
                                    <th class="text-end">{{ $label }}</th>
                                @endforeach
                                <th class="text-end">মোট</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cows as $cow)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $cow->tag_number }}</div>
                                        <div class="small text-muted">{{ $cow->name ?: '—' }}</div>
                                        @if ($withdrawal->has($cow->id))
                                            <span class="pill orange mt-1">দুধ বিক্রি বন্ধ:
                                                {{ $withdrawal[$cow->id]->format('d/m') }} পর্যন্ত</span>
                                        @endif
                                    </td>
                                    @foreach ([1, 2, 3] as $n)
                                        <td class="text-end">
                                            <input type="number" inputmode="decimal" step="0.1" min="0"
                                                max="100" name="rows[{{ $cow->id }}][{{ $n }}]"
                                                x-model="rows[{{ $cow->id }}][{{ $n }}]"
                                                class="form-control form-control-sm text-end ms-auto milk-input {{ $errors->has('rows.' . $cow->id . '.' . $n) ? 'is-invalid' : '' }}">
                                        </td>
                                    @endforeach
                                    <td class="text-end fw-semibold text-nowrap"><span
                                            x-text="fmt(t({{ $cow->id }}))"></span> লি.</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>মোট</td>
                                <td class="text-end"><span x-text="fmt(col(1))"></span></td>
                                <td class="text-end"><span x-text="fmt(col(2))"></span></td>
                                <td class="text-end"><span x-text="fmt(col(3))"></span></td>
                                <td class="text-end text-nowrap"><span x-text="fmt(grand)"></span> লি.</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            <div class="d-flex flex-wrap align-items-center gap-3">
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>সংরক্ষণ করুন</button>
                <span class="small text-muted">খালি ঘর বাদ যাবে। কোনো গাভি সেদিন দুধ না দিলে ০ লিখুন।</span>
            </div>
        </form>
    @endif
@endsection
