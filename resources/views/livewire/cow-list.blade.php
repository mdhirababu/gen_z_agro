<div>
    {{-- ===== ধরন অনুযায়ী ফিল্টার ===== --}}
    <div class="tab-chips">
        <button type="button" wire:click="setCategory('')" class="tab-chip {{ $category === '' ? 'active' : '' }}">
            সব <span class="count">{{ $total }}</span>
        </button>
        @foreach ($categories as $key => $meta)
            <button type="button" wire:click="setCategory('{{ $key }}')"
                class="tab-chip {{ $category === $key ? 'active' : '' }}">
                {{ $meta['label'] }} <span class="count">{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    {{-- ===== খোঁজা ও অবস্থা ===== --}}
    <div class="row g-2 mb-3">
        <div class="col-md-8">
            <div class="field-control">
                <i class="bi bi-search lead-ic"></i>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search"
                    placeholder="ট্যাগ নম্বর বা নাম দিয়ে খুঁজুন">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select" wire:model.live="status" aria-label="অবস্থা">
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
                <option value="all">সব অবস্থা</option>
            </select>
        </div>
    </div>

    {{-- ===== গরুর কার্ড ===== --}}
    @if ($cows->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-collection"></i>
                @if ($search !== '' || $category !== '' || $status !== 'active')
                    <h2>কোনো গরু পাওয়া যায়নি</h2>
                    <p>খোঁজা বা ফিল্টার বদলে আবার দেখুন।</p>
                @else
                    <h2>এখনো কোনো গরু নেই</h2>
                    <p>প্রথম গরু বা বাছুরটি যোগ করে শুরু করুন।</p>
                    @if ($canCreate)
                        <a href="{{ route('farms.cows.create', $farm) }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>নতুন গরু যোগ করুন
                        </a>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($cows as $cow)
                @php
                    $meta = $categories[$cow->category] ?? ['label' => $cow->category, 'tag' => 'gray'];
                    $photo = $cow->photoUrl();
                @endphp
                <div class="col-md-6 col-xl-4" wire:key="cow-{{ $cow->id }}">
                    <div class="cow-card">
                        <div class="cow-photo">
                            @if ($photo)
                                <img src="{{ $photo }}" alt="{{ $cow->tag_number }}" loading="lazy">
                            @else
                                <i class="bi bi-image"></i>
                            @endif
                        </div>

                        <div class="cow-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('farms.cows.show', [$farm, $cow]) }}"
                                        class="cow-tag stretched-link">{{ $cow->tag_number }}</a>
                                    @if ($cow->name)
                                        <div class="small text-muted text-truncate">{{ $cow->name }}</div>
                                    @endif
                                </div>
                                @if ($canEdit)
                                    <a href="{{ route('farms.cows.edit', [$farm, $cow]) }}" class="icon-btn sm"
                                        title="সম্পাদনা করুন" aria-label="সম্পাদনা করুন">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>

                            <div class="small text-muted mt-1">বয়স: {{ $cow->ageLabel() ?? 'অজানা' }}</div>

                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="tag {{ $meta['tag'] }}">{{ $meta['label'] }}</span>
                                <span class="tag blue">{{ config('farm.sexes')[$cow->sex] ?? $cow->sex }}</span>
                                @if ($cow->status !== 'active')
                                    <span
                                        class="tag {{ $cow->status === 'dead' ? 'red' : 'gray' }}">{{ $statuses[$cow->status] ?? $cow->status }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">{{ $cows->links() }}</div>
    @endif

    @if ($canCreate)
        <a href="{{ route('farms.cows.create', $farm) }}" class="fab" title="নতুন গরু যোগ করুন">
            <i class="bi bi-plus-lg"></i><span>নতুন গরু</span>
        </a>
    @endif
</div>
