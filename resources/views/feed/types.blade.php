@extends('layouts.farm')
@section('title', 'খাবারের ধরন')

@section('content')
    @php $units = config('farm.feed_units'); @endphp

    <div x-data="{ ed: { open: false, url: '', name: '', unit: 'kg', price: '' } }" @keydown.escape.window="ed.open = false">

        <div class="page-head">
            <div>
                <h1>খাবারের ধরন</h1>
                <p>খামারে যা যা খাওয়ানো হয়, তার তালিকা ও একক।</p>
            </div>
            <a href="{{ route('farms.feed.index', $farm) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>খাবারের রেকর্ড
            </a>
        </div>

        @if ($errors->edit->any())
            <div class="alert alert-danger">{{ $errors->edit->first() }}</div>
        @endif

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">নতুন ধরন</div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('farms.feed.types.store', $farm) }}">
                            @csrf
                            <div class="field">
                                <label for="name" class="form-label">নাম</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}"
                                    class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="field">
                                        <label for="unit" class="form-label">একক</label>
                                        <select id="unit" name="unit" class="form-select">
                                            @foreach ($units as $key => $label)
                                                <option value="{{ $key }}" @selected(old('unit', 'kg') === $key)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="field">
                                        <label for="price_per_unit" class="form-label">প্রতি এককের দাম</label>
                                        <div class="input-group">
                                            <span class="input-group-text">৳</span>
                                            <input id="price_per_unit" type="number" step="0.01" min="0"
                                                name="price_per_unit" value="{{ old('price_per_unit') }}"
                                                class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if ($canFinance)
                                <div class="field-hint mb-3">দাম শুধু আনুমানিক খরচ দেখাতে লাগে, ঐচ্ছিক।</div>
                            @endif
                            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>ধরন যোগ করুন</button>
                        </form>

                        @if ($types->isEmpty())
                            <form method="POST" action="{{ route('farms.feed.types.defaults', $farm) }}"
                                class="mt-3 pt-3 border-top">
                                @csrf
                                <div class="small text-muted mb-2">শুরু করতে সাধারণ কয়েকটা ধরন (ঘাস, খড়, ভুসি, দানা, খৈল)
                                    একবারে যোগ করে নিতে পারেন।</div>
                                <button class="btn btn-outline-primary w-100">সাধারণ ধরনগুলো যোগ করুন</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                @if ($types->isEmpty())
                    <div class="panel h-auto">
                        <div class="panel-body empty-state">
                            <i class="bi bi-basket"></i>
                            <h2>এখনো কোনো ধরন নেই</h2>
                            <p>বাঁ দিক থেকে নিজে যোগ করুন, অথবা সাধারণ ধরনগুলো একবারে নিন।</p>
                        </div>
                    </div>
                @else
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>নাম</th>
                                        <th>একক</th>
                                        <th class="text-end">দাম</th>
                                        <th>রেকর্ড</th>
                                        <th>অবস্থা</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($types as $t)
                                        <tr class="{{ $t->is_active ? '' : 'is-void' }}">
                                            <td class="fw-semibold">{{ $t->name }}</td>
                                            <td>{{ $t->unitLabel() }}</td>
                                            <td class="text-end">
                                                {{ $t->price_per_unit !== null ? \App\Support\Money::fmt($t->price_per_unit) : '—' }}
                                            </td>
                                            <td>{{ $t->records_count }}টি</td>
                                            <td><span
                                                    class="pill {{ $t->is_active ? 'green' : 'gray' }}">{{ $t->is_active ? 'চালু' : 'বন্ধ' }}</span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="icon-btn sm" title="সম্পাদনা করুন"
                                                    aria-label="সম্পাদনা করুন"
                                                    @click="ed = Object.assign(@js([
    'url' => route('farms.feed.types.update', [$farm, $t]),
    'name' => $t->name,
    'unit' => $t->unit,
    'price' => $t->price_per_unit !== null ? (string) $t->price_per_unit : '',
]), { open: true })">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST"
                                                    action="{{ route('farms.feed.types.toggle', [$farm, $t]) }}"
                                                    class="d-inline">
                                                    @csrf
                                                    <button class="icon-btn sm"
                                                        title="{{ $t->is_active ? 'বন্ধ করুন' : 'চালু করুন' }}"
                                                        aria-label="{{ $t->is_active ? 'বন্ধ করুন' : 'চালু করুন' }}">
                                                        <i
                                                            class="bi {{ $t->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">ধরন মোছা যায় না, পুরোনো রেকর্ডে নাম থাকে। আর দরকার না হলে "বন্ধ" করে
                        দিন।</div>
                @endif
            </div>
        </div>

        {{-- ===== সম্পাদনার পপ-আপ ===== --}}
        <div class="sheet-backdrop" x-show="ed.open" x-transition.opacity style="display: none"
            @click.self="ed.open = false">
            <div class="sheet" role="dialog" aria-modal="true">
                <form method="POST" :action="ed.url">
                    @csrf
                    @method('PUT')
                    <div class="sheet-head">
                        <span class="ic sky"><i class="bi bi-pencil"></i></span>
                        <div>
                            <h3>খাবারের ধরন বদলান</h3>
                        </div>
                    </div>
                    <div class="field">
                        <label class="form-label">নাম</label>
                        <input type="text" name="name" class="form-control" x-model="ed.name" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="field">
                                <label class="form-label">একক</label>
                                <select name="unit" class="form-select" x-model="ed.unit">
                                    @foreach ($units as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="field mb-0">
                                <label class="form-label">প্রতি এককের দাম</label>
                                <input type="number" step="0.01" min="0" name="price_per_unit"
                                    class="form-control" x-model="ed.price">
                            </div>
                        </div>
                    </div>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="ed.open = false">ফিরে
                            যান</button>
                        <button type="submit" class="btn btn-primary">সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
