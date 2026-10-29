@php
    $o = $onboarding;

    $tones = [
        'cows' => 'mint',
        'milk' => 'sky',
        'expense' => 'peach',
        'sale' => 'mint',
        'health' => 'lilac',
        'profile' => 'sand',
        'members' => 'sky',
        'sms' => 'peach',
    ];

    if ($o['done'] === 0) {
        $title = 'শুরু করা যাক';
        $subtitle = 'খামারের হিসাব সাজাতে এই ধাপগুলো সারুন। কয়েক মিনিটেই হয়ে যায়।';
    } elseif ($o['core_done']) {
        $title = 'মূল ধাপ শেষ! আরও কিছু করে নিতে পারেন';
        $subtitle = 'বাকিগুলো ঐচ্ছিক, তবে কাজে আসবে।';
    } else {
        $title = 'আর কয়েকটা ধাপ বাকি';
        $subtitle = $o['done'] . '/' . $o['total'] . ' ধাপ শেষ, চালিয়ে যান।';
    }
@endphp

@if ($o['visible'])
    <section class="panel h-auto mb-3" aria-labelledby="onb-title">
        <div class="panel-body">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h2 id="onb-title" class="h5 mb-1">{{ $title }}</h2>
                    <p class="text-muted mb-2">{{ $subtitle }}</p>
                </div>

                <form method="POST" action="{{ route('farms.onboarding.dismiss', $farm) }}" class="no-print">
                    @csrf
                    <button class="btn btn-sm btn-link text-muted text-nowrap">লুকান</button>
                </form>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="progress flex-grow-1" style="height: 8px" role="progressbar"
                    aria-valuenow="{{ $o['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: {{ $o['percent'] }}%"></div>
                </div>
                <small class="text-muted text-nowrap">{{ $o['done'] }}/{{ $o['total'] }} ধাপ</small>
            </div>

            <ul class="list-unstyled mb-0">
                @foreach ($o['steps'] as $step)
                    @php $isNext = $o['next'] === $step['key']; @endphp

                    <li
                        class="d-flex flex-wrap gap-3 align-items-start py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        @if ($step['done'])
                            <span class="ic mint"><i class="bi bi-check-circle-fill"></i></span>
                        @else
                            <span class="ic {{ $tones[$step['key']] ?? 'mint' }}"><i
                                    class="bi {{ $step['icon'] }}"></i></span>
                        @endif

                        <div class="flex-grow-1" style="min-width: 220px">
                            <div
                                class="fw-semibold {{ $step['done'] ? 'text-muted text-decoration-line-through' : '' }}">
                                {{ $step['title'] }}</div>

                            @unless ($step['done'])
                                <div class="small text-muted">{{ $step['hint'] }}</div>

                                @if ($step['locked'])
                                    <div class="small text-warning-emphasis"><i class="bi bi-lock me-1"></i>আগে গরু যোগ
                                        করুন।</div>
                                @endif
                            @endunless
                        </div>

                        @unless ($step['done'] || $step['locked'])
                            <div class="d-flex flex-wrap gap-2 no-print">
                                <a href="{{ $step['url'] }}"
                                    class="btn btn-sm {{ $isNext ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $step['label'] }}</a>

                                @if ($step['alt'])
                                    <a href="{{ $step['alt']['url'] }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-excel me-1"></i>{{ $step['alt']['label'] }}
                                    </a>
                                @endif
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@elseif ($o['restorable'])
    <form method="POST" action="{{ route('farms.onboarding.restore', $farm) }}" class="mb-3 no-print">
        @csrf
        <button class="btn btn-sm btn-link text-muted p-0"><i class="bi bi-compass me-1"></i>শুরুর গাইড আবার
            দেখান</button>
    </form>
@endif
