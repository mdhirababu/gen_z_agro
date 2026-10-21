@extends('layouts.farm')
@section('title', 'দুধ উৎপাদন')

@section('content')
    @php
        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.') ?: '0';
        $total = (float) $totals->total;
        $sessionTiles = [
            ['প্রথম বার', $totals->s1, 'sky'],
            ['দ্বিতীয় বার', $totals->s2, 'mint'],
            ['তৃতীয় বার', $totals->s3, 'peach'],
        ];
        $soldPercent = $sold !== null && $total > 0 ? min(100, round(($sold / $total) * 100)) : 0;
    @endphp

    <div class="page-head">
        <div>
            <h1>দুধ উৎপাদন</h1>
            <p>গরুভিত্তিক দৈনিক দুধের হিসাব ও মাসিক সারসংক্ষেপ।</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="{{ route('farms.milk.index', $farm) }}">
                <select name="m" class="form-select" onchange="this.form.submit()" aria-label="মাস">
                    @foreach ($months as $m)
                        <option value="{{ $m }}" @selected($month === $m)>
                            {{ substr($m, 5) }}/{{ substr($m, 0, 4) }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('farms.milk.entry', $farm) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>দুধ
                এন্ট্রি</a>
        </div>
    </div>

    @if ((int) $totals->records === 0)
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-droplet-half"></i>
                <h2>এই মাসে কোনো দুধের হিসাব নেই</h2>
                <p>আজকের দুধ লিখে শুরু করুন। এক পাতায় সব গাভির হিসাব দেওয়া যায়।</p>
                <a href="{{ route('farms.milk.entry', $farm) }}" class="btn btn-primary">দুধ এন্ট্রি করুন</a>
            </div>
        </div>
    @else
        <div class="row g-2 mb-2">
            @foreach ($sessionTiles as [$label, $value, $tone])
                <div class="col-4">
                    <div class="tile {{ $tone }} text-center">
                        <div class="tile-label">{{ $label }}</div>
                        <div class="tile-value">{{ $fmt($value) }} <small class="fw-normal">লি.</small></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="tile lilac text-center mb-2">
            <div class="tile-label">মোট দুধ</div>
            <div class="tile-value fs-3">{{ $fmt($total) }} <small class="fw-normal">লিটার</small></div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="tile text-center bg-white">
                    <div class="tile-label">রেকর্ড</div>
                    <div class="tile-value">{{ $totals->records }}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="tile text-center bg-white">
                    <div class="tile-label">গরু</div>
                    <div class="tile-value">{{ $totals->cows }}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="tile text-center bg-white">
                    <div class="tile-label">দৈনিক গড়</div>
                    <div class="tile-value">{{ $fmt($avg) }} <small class="fw-normal">লি.</small></div>
                </div>
            </div>
        </div>

        @if ($sold !== null)
            <div class="panel h-auto mb-3">
                <div class="panel-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <span>উৎপাদন: <strong>{{ $fmt($total) }} লিটার</strong></span>
                        <span>বিক্রি হয়েছে (লিটার): <strong>{{ $fmt($sold) }}</strong></span>
                    </div>
                    <div class="usage-bar {{ $sold > $total ? 'is-full' : '' }}"><span
                            style="width: {{ $soldPercent }}%"></span></div>
                    <div class="small text-muted mt-2">
                        @if ($sold > $total + 0.05)
                            বিক্রি উৎপাদনের চেয়ে বেশি দেখাচ্ছে, কিছু দিনের দুধ হয়তো এন্ট্রি করা হয়নি।
                        @else
                            উৎপাদনের {{ $soldPercent }}% বিক্রি নথিভুক্ত হয়েছে, বাকিটা ঘরে ব্যবহার বা নথিভুক্ত হয়নি।
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <h5 class="mb-2">দিনভিত্তিক হিসাব</h5>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th class="text-end">১ম</th>
                                    <th class="text-end">২য়</th>
                                    <th class="text-end">৩য়</th>
                                    <th class="text-end">মোট</th>
                                    <th class="text-end">রেকর্ড</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($daily as $d)
                                    <tr>
                                        <td class="text-nowrap">{{ $d->milk_date->format('d/m') }}</td>
                                        <td class="text-end">{{ $fmt($d->s1) }}</td>
                                        <td class="text-end">{{ $fmt($d->s2) }}</td>
                                        <td class="text-end">{{ $fmt($d->s3) }}</td>
                                        <td class="text-end fw-semibold">{{ $fmt($d->total) }}</td>
                                        <td class="text-end">{{ $d->records }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('farms.milk.entry', [$farm, 'date' => $d->milk_date->toDateString()]) }}"
                                                class="icon-btn sm" title="এই দিনের এন্ট্রি দেখুন বা বদলান"
                                                aria-label="এই দিনের এন্ট্রি দেখুন বা বদলান"><i
                                                    class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td>মোট</td>
                                    <td class="text-end">{{ $fmt($totals->s1) }}</td>
                                    <td class="text-end">{{ $fmt($totals->s2) }}</td>
                                    <td class="text-end">{{ $fmt($totals->s3) }}</td>
                                    <td class="text-end">{{ $fmt($total) }}</td>
                                    <td class="text-end">{{ $totals->records }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <h5 class="mb-2">গরুভিত্তিক হিসাব</h5>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>গাভি</th>
                                    <th class="text-end">মোট</th>
                                    <th class="text-end">দিন</th>
                                    <th class="text-end">দৈনিক গড়</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($perCow as $row)
                                    @php $cow = $cows->get($row->cow_id); @endphp
                                    <tr>
                                        <td>
                                            @if ($cow && !$cow->trashed())
                                                <a class="fw-semibold"
                                                    href="{{ route('farms.cows.show', [$farm, $cow]) }}">{{ $cow->tag_number }}</a>
                                            @else
                                                <span class="fw-semibold">{{ $cow ? $cow->tag_number : '—' }}</span>
                                            @endif
                                            @if ($cow && $cow->name)
                                                <div class="small text-muted">{{ $cow->name }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ $fmt($row->total) }}</td>
                                        <td class="text-end">{{ $row->days }}</td>
                                        <td class="text-end">{{ $fmt($row->days > 0 ? $row->total / $row->days : 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
