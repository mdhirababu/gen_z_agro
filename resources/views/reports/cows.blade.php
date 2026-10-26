@extends('layouts.farm')
@section('title', 'গরুভিত্তিক লাভ-ক্ষতি')

@section('content')
    @php
        $money = fn($v) => \App\Support\Money::fmt($v);
        $tone = fn($v) => $v > 0 ? 'text-success' : ($v < 0 ? 'text-danger' : '');
        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.') ?: '0';

        $cats = config('farm.categories');
        $ruleLabels = [
            'feed_records' => 'খাবারের রেকর্ড অনুযায়ী (পরিমাণ × দাম)',
            'cow_days' => 'গরু-দিন অনুযায়ী',
            'equal' => 'সমান ভাগে',
        ];
        $statusPills = [
            'active' => ['সক্রিয়', 'green'],
            'sold' => ['বিক্রিত', 'gray'],
            'dead' => ['মৃত', 'red'],
            'transferred' => ['সরানো', 'gray'],
        ];

        $link = fn(array $over = []) => route(
            'farms.reports.cows',
            [$farm] + array_filter(array_merge(request()->query(), $over), fn($v) => $v !== null && $v !== ''),
        );

        $t = $report['totals'];
        $un = $report['unallocated'];
        $shown = fn(string $key) => collect($rows)->sum($key);
    @endphp

    <div class="page-head">
        <div>
            <h1>গরুভিত্তিক লাভ-ক্ষতি</h1>
            <p>প্রতিটি গরুর আয়, খরচ (যৌথ খরচের ভাগসহ) আর ফল।</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('farms.reports.index', $farm) }}" class="btn btn-outline-secondary"><i
                    class="bi bi-arrow-left me-1"></i>সব রিপোর্ট</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i
                    class="bi bi-printer me-1"></i>প্রিন্ট</button>
        </div>
    </div>

    <div class="d-none d-print-block mb-3"><strong>{{ $farm->name }}</strong>, গরুভিত্তিক লাভ-ক্ষতি, {{ $period->label }}
    </div>

    @include('reports._period', ['route' => 'farms.reports.cows'])

    {{-- ===== সারসংক্ষেপ ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi sky">
                <div class="kpi-label">সব গরুর ফলের যোগফল</div>
                <div class="kpi-value {{ $tone($t['result']) }}">{{ $money($t['result']) }}</div>
                <div class="kpi-note">{{ count($report['rows']) }}টি গরু</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi lilac">
                <div class="kpi-label">ভাগ না হওয়া আয় − খরচ</div>
                <div class="kpi-value {{ $tone($un['income'] - $un['expense']) }}">
                    {{ $money($un['income'] - $un['expense']) }}</div>
                <div class="kpi-note">যেমন গোবর, অন্যান্য আয়</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi {{ $report['check_net'] >= 0 ? '' : 'rose' }}">
                <div class="kpi-label">খামারের আনুমানিক নিট ফল</div>
                <div class="kpi-value {{ $tone($report['check_net']) }}">{{ $money($report['check_net']) }}</div>
                <div class="kpi-note">লাভ-ক্ষতি রিপোর্টের নিট ফলের সমান</div>
            </div>
        </div>
    </div>

    {{-- ===== ভাগের নিয়ম ===== --}}
    <details class="panel h-auto mb-3 no-print">
        <summary class="panel-head" style="cursor: pointer">যৌথ খরচ ও দুধের আয় কীভাবে ভাগ হয়েছে</summary>
        <div class="panel-body">
            <ul class="mb-0">
                <li><strong>যৌথ খাবারের খরচ:</strong>
                    {{ $ruleLabels[$report['rules']['feed']] ?? $report['rules']['feed'] }}
                    @if ($report['feed_fallback'])
                        <span class="pill orange">খাবারের রেকর্ড বা দাম নেই, তাই গরু-দিনে ভাগ হয়েছে</span>
                    @endif
                </li>
                <li><strong>অন্যান্য যৌথ খরচ</strong> (শ্রমিক, বিদ্যুৎ, গোয়ালঘর ইত্যাদি):
                    {{ $ruleLabels[$report['rules']['other']] ?? $report['rules']['other'] }}</li>
                <li><strong>দুধের আয়:</strong> সময়কালে কোন গাভি কত লিটার দুধ দিয়েছে, সেই অনুপাতে।</li>
                <li><strong>গরু-দিন:</strong> সময়কালে গরুটি যত দিন খামারে ছিল।</li>
                <li><strong>গরুর ভিত্তিমূল্য</strong> খরচ হিসেবে আসে শুধু গরু বিক্রি হলে, মারা গেলে বা অন্যত্র সরালে।</li>
            </ul>
        </div>
    </details>

    {{-- ===== ফিল্টার ===== --}}
    <div class="no-print d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="tab-chips mb-0">
            @foreach (['all' => 'সব', 'active' => 'সক্রিয়', 'sold' => 'বিক্রিত', 'gone' => 'মৃত বা সরানো'] as $key => $label)
                <a href="{{ $link(['status' => $key === 'all' ? null : $key]) }}"
                    class="tab-chip {{ $status === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('farms.reports.cows', $farm) }}" class="ms-auto">
            @foreach (request()->except('sort') as $name => $value)
                @if (is_string($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <select name="sort" class="form-select" onchange="this.form.submit()" aria-label="সাজানো">
                <option value="result_desc" @selected($sort === 'result_desc')>বেশি লাভ আগে</option>
                <option value="result_asc" @selected($sort === 'result_asc')>বেশি ক্ষতি আগে</option>
                <option value="tag" @selected($sort === 'tag')>ট্যাগ নম্বর অনুযায়ী</option>
            </select>
        </form>
    </div>

    {{-- ===== গরুর তালিকা ===== --}}
    @if (count($rows) === 0)
        <div class="panel h-auto mb-3">
            <div class="panel-body empty-state">
                <i class="bi bi-collection"></i>
                <h2>এই সময়ে দেখানোর মতো কোনো গরু নেই</h2>
                <p>সময়কাল বা ফিল্টার বদলে দেখুন।</p>
            </div>
        </div>
    @else
        <div class="table-card mb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>গরু</th>
                            <th class="text-end">খামারে</th>
                            <th class="text-end">আয়</th>
                            <th class="text-end">সরাসরি খরচ</th>
                            <th class="text-end">যৌথ খরচের ভাগ</th>
                            <th class="text-end">ভিত্তিমূল্য</th>
                            <th class="text-end">ফল</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $cow = $row['cow'];
                                [$sLabel, $sPill] = $statusPills[$cow->status] ?? [$cow->status, 'gray'];
                            @endphp
                            <tr>
                                <td>
                                    <a class="fw-semibold"
                                        href="{{ route('farms.cows.show', [$farm, $cow]) }}">{{ $cow->tag_number }}</a>
                                    @if ($cow->name)
                                        <span class="small text-muted">{{ $cow->name }}</span>
                                    @endif
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <span
                                            class="tag {{ $cats[$cow->category]['tag'] ?? 'gray' }}">{{ $cats[$cow->category]['label'] ?? $cow->category }}</span>
                                        <span class="pill {{ $sPill }}">{{ $sLabel }}</span>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ $row['days'] }} দিন
                                    @if ($row['liters'] > 0)
                                        <div class="small text-muted">{{ $fmt($row['liters']) }} লি.</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ $money($row['income']) }}
                                    @if ($row['income'] > 0)
                                        <div class="small text-muted">দুধ {{ $money($row['income_milk']) }}, বিক্রি
                                            {{ $money($row['income_sale']) }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">{{ $money($row['cost_direct']) }}</td>
                                <td class="text-end text-nowrap">
                                    {{ $money($row['joint']) }}
                                    @if ($row['joint'] > 0)
                                        <div class="small text-muted">খাবার {{ $money($row['joint_feed']) }}, অন্য
                                            {{ $money($row['joint_other']) }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">{{ $money($row['cost_base']) }}</td>
                                <td class="text-end text-nowrap fw-semibold {{ $tone($row['result']) }}">
                                    {{ $money($row['result']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="2">এই তালিকার মোট</td>
                            <td class="text-end">{{ $money($shown('income')) }}</td>
                            <td class="text-end">{{ $money($shown('cost_direct')) }}</td>
                            <td class="text-end">{{ $money($shown('joint')) }}</td>
                            <td class="text-end">{{ $money($shown('cost_base')) }}</td>
                            <td class="text-end {{ $tone($shown('result')) }}">{{ $money($shown('result')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- ===== মেলানো ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">খামারের নিট ফলের সঙ্গে মেলানো</div>
        <div class="panel-body">
            <div class="d-flex justify-content-between py-1">
                <span>সব গরুর ফলের যোগফল (ফিল্টার ছাড়া)</span>
                <strong class="{{ $tone($t['result']) }}">{{ $money($t['result']) }}</strong>
            </div>

            @foreach ($un['income_lines'] as $line)
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">+ {{ $line['label'] }}</span>
                    <span>{{ $money($line['amount']) }}</span>
                </div>
            @endforeach
            @foreach ($un['expense_lines'] as $line)
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">− {{ $line['label'] }}</span>
                    <span>{{ $money($line['amount']) }}</span>
                </div>
            @endforeach

            <div class="d-flex justify-content-between py-2 border-top mt-1">
                <span class="fw-semibold">আনুমানিক নিট ফল</span>
                <strong class="fs-5 {{ $tone($report['check_net']) }}">{{ $money($report['check_net']) }}</strong>
            </div>
            <div class="small text-muted no-print">
                এই সংখ্যা <a
                    href="{{ route('farms.reports.profit-loss', [$farm] + request()->only(['r', 'from', 'to'])) }}">লাভ-ক্ষতি
                    রিপোর্টের</a> নিট ফলের সমান হওয়ার কথা। না মিললে জানিও।
            </div>
        </div>
    </div>

    <div class="small text-muted">
        <ul class="mb-0">
            <li>এটা <strong>আনুমানিক</strong> হিসাব। যৌথ খরচ ও দুধের আয়ের ভাগ একটা নিয়ম ধরে করা, আসল ব্যবহারের হুবহু মাপ
                নয়।</li>
            <li>অবিক্রীত গরুর বর্তমান বাজারদর ধরা নেই। গরুর ভিত্তিমূল্য শুধু বিক্রি, মৃত্যু বা সরানোর সময় খরচ।</li>
            <li>আজীবনের (কেনা থেকে বিক্রি পর্যন্ত) সরাসরি খরচ দেখতে গরুর পাতার "হিসাব" ট্যাব খুলুন।</li>
        </ul>
    </div>
@endsection
