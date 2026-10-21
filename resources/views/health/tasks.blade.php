@extends('layouts.farm')
@section('title', 'আসন্ন কাজ')

@section('content')
    @php
        $today = now()->startOfDay();
        $overdue = $tasks->filter(fn($e) => $e->next_due_date->lt($today))->values();
        $week = $tasks
            ->filter(fn($e) => $e->next_due_date->gte($today) && $e->next_due_date->lte($today->copy()->addDays(7)))
            ->values();
        $month = $tasks->filter(fn($e) => $e->next_due_date->gt($today->copy()->addDays(7)))->values();

        $groups = [['দেরি হয়ে গেছে', $overdue, 'red'], ['এই সপ্তাহে', $week, 'orange'], ['এই মাসে', $month, 'green']];
    @endphp

    <div class="page-head">
        <div>
            <h1>আসন্ন কাজ</h1>
            <p>আগামী ৩০ দিনের টিকা, কৃমিনাশক ও পরীক্ষা, আর যেগুলোর সময় পেরিয়ে গেছে।</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('farms.health.index', $farm) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>স্বাস্থ্যের তালিকা
            </a>
            <a href="{{ route('farms.health.create', $farm) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>নতুন রেকর্ড
            </a>
        </div>
    </div>

    @if ($tasks->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-check2-circle"></i>
                <h2>এখন কোনো কাজ বাকি নেই</h2>
                <p>পরের তারিখসহ টিকা বা পরীক্ষার রেকর্ড দিলে সময় কাছে এলে এখানে দেখা যাবে।</p>
            </div>
        </div>
    @else
        @foreach ($groups as [$label, $items, $pill])
            @if ($items->isNotEmpty())
                <h5 class="mt-3 mb-2">{{ $label }} <span
                        class="pill {{ $pill }} ms-1">{{ $items->count() }}</span></h5>
                <div class="panel h-auto">
                    <div class="panel-body py-1">
                        @foreach ($items as $e)
                            @php
                                $meta = $e->kindMeta();
                                $d = $e->daysToDue();
                            @endphp
                            <div class="pay-row-item">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <span class="ic sm {{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold">
                                            <a
                                                href="{{ route('farms.cows.show', [$farm, $e->cow]) }}">{{ $e->cow->tag_number }}</a>,
                                            {{ $e->title }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ $e->next_due_date->format('d/m/Y') }}:
                                            {{ $d < 0 ? abs($d) . ' দিন দেরি' : ($d === 0 ? 'আজ' : $d . ' দিন পরে') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <a href="{{ route('farms.health.create', [$farm, 'kind' => $e->kind, 'vaccine' => $e->vaccine_code, 'cow' => $e->cow_id]) }}"
                                        class="btn btn-sm btn-primary">এখন করুন</a>
                                    <form method="POST" action="{{ route('farms.health.done', [$farm, $e]) }}">
                                        @csrf
                                        <button class="icon-btn sm" title="আগেই করা হয়েছে বা আর দরকার নেই"
                                            aria-label="আগেই করা হয়েছে বা আর দরকার নেই">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if ($later > 0)
            <div class="small text-muted mt-3">৩০ দিনের পরের আরও {{ $later }}টি কাজ আছে, সময় কাছে এলে এখানে আসবে।
            </div>
        @endif
    @endif
@endsection
