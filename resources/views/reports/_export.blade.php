@php
    $farm->loadMissing('subscription.plan');
    $plan = $farm->subscription?->plan;
    $allowed =
        (bool) ($plan && $plan->allows('export')) &&
        ($report !== 'cows' || (bool) ($plan && $plan->allows('cow_profit_report')));
    $query = request()->only(['r', 'from', 'to', 'status', 'sort']);
@endphp

@if ($allowed)
    <div class="btn-group no-print">
        <a class="btn btn-outline-primary" href="{{ route('farms.reports.export', [$farm, $report, 'xlsx'] + $query) }}">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </a>
        <a class="btn btn-outline-primary" href="{{ route('farms.reports.export', [$farm, $report, 'pdf'] + $query) }}">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
    </div>
@else
    <span class="btn btn-outline-secondary disabled no-print" title="আপনার প্ল্যানে এই সুবিধা নেই">
        <i class="bi bi-lock me-1"></i>Excel ও PDF
    </span>
@endif
