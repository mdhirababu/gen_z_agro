@extends('layouts.farm')
@section('title', 'ক্রেতা ও বকেয়া')

@section('content')
    <div x-data="{
        ed: { open: false, url: '', name: '', phone: '', address: '' },
        del: { open: false, url: '', name: '' }
    }" @keydown.escape.window="ed.open = false; del.open = false">

        <div class="page-head">
            <div>
                <h1>ক্রেতা ও বকেয়া</h1>
                <p>কার কাছে কত টাকা পাওনা, এক নজরে।</p>
            </div>
            <span class="pill {{ $totalDue > 0 ? 'orange' : 'green' }}">মোট পাওনা
                {{ \App\Support\Money::fmt($totalDue) }}</span>
        </div>

        @if ($errors->edit->any())
            <div class="alert alert-danger">{{ $errors->edit->first() }}</div>
        @endif

        <div class="row g-3">
            {{-- ===== নতুন ক্রেতা ===== --}}
            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">নতুন ক্রেতা</div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('farms.customers.store', $farm) }}">
                            @csrf
                            <div class="field">
                                <label for="name" class="form-label">নাম <span class="text-danger">*</span></label>
                                <div class="field-control">
                                    <i class="bi bi-person lead-ic"></i>
                                    <input id="name" type="text" name="name" value="{{ old('name') }}"
                                        class="form-control @error('name') is-invalid @enderror" required>
                                </div>
                                @error('name')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label for="phone" class="form-label">ফোন</label>
                                <div class="field-control">
                                    <i class="bi bi-telephone lead-ic"></i>
                                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="field">
                                <label for="address" class="form-label">ঠিকানা</label>
                                <input id="address" type="text" name="address" value="{{ old('address') }}"
                                    class="form-control">
                            </div>
                            <button class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i>ক্রেতা যোগ
                                করুন</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ===== তালিকা ===== --}}
            <div class="col-lg-8">
                @if ($customers->isEmpty())
                    <div class="panel h-auto">
                        <div class="panel-body empty-state">
                            <i class="bi bi-person-lines-fill"></i>
                            <h2>এখনো কোনো ক্রেতা নেই</h2>
                            <p>নিয়মিত দুধ বা গরু কেনেন এমন ক্রেতা যোগ করুন, বকেয়ার হিসাব রাখা সহজ হবে।</p>
                        </div>
                    </div>
                @else
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ক্রেতা</th>
                                        <th class="text-end">মোট বিক্রি</th>
                                        <th class="text-end">পাওনা</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $c)
                                        @php $due = max(0, (float) $c->total_sum - (float) $c->paid_sum); @endphp
                                        <tr>
                                            <td>
                                                <a class="fw-semibold"
                                                    href="{{ route('farms.customers.show', [$farm, $c]) }}">{{ $c->name }}</a>
                                                <div class="small text-muted">{{ $c->phone ?: 'ফোন নেই' }},
                                                    {{ $c->sales_count }}টি বিক্রি</div>
                                            </td>
                                            <td class="text-end text-nowrap">{{ \App\Support\Money::fmt($c->total_sum) }}
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <span
                                                    class="pill {{ $due > 0 ? 'red' : 'green' }}">{{ $due > 0 ? \App\Support\Money::fmt($due) : 'নেই' }}</span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <a href="{{ route('farms.customers.show', [$farm, $c]) }}"
                                                    class="icon-btn sm" title="বিস্তারিত দেখুন"
                                                    aria-label="বিস্তারিত দেখুন">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="icon-btn sm" title="সম্পাদনা করুন"
                                                    aria-label="সম্পাদনা করুন"
                                                    @click="ed = Object.assign(@js([
                                                        'url' => route('farms.customers.update', [$farm, $c]),
                                                        'name' => $c->name,
                                                        'phone' => $c->phone,
                                                        'address' => $c->address,
                                                    ]), { open: true })">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                @if ($c->sales_count === 0)
                                                    <button type="button" class="icon-btn sm danger" title="ক্রেতা সরান"
                                                        aria-label="ক্রেতা সরান"
                                                        @click="del = Object.assign(@js(['url' => route('farms.customers.destroy', [$farm, $c]), 'name' => $c->name]), { open: true })">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                            <h3>ক্রেতার তথ্য বদলান</h3>
                        </div>
                    </div>

                    <div class="field">
                        <label class="form-label">নাম</label>
                        <input type="text" name="name" class="form-control" x-model="ed.name" required>
                    </div>
                    <div class="field">
                        <label class="form-label">ফোন</label>
                        <input type="text" name="phone" class="form-control" x-model="ed.phone">
                    </div>
                    <div class="field mb-0">
                        <label class="form-label">ঠিকানা</label>
                        <input type="text" name="address" class="form-control" x-model="ed.address">
                    </div>

                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="ed.open = false">ফিরে
                            যান</button>
                        <button type="submit" class="btn btn-primary">সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== সরানোর পপ-আপ ===== --}}
        <div class="sheet-backdrop" x-show="del.open" x-transition.opacity style="display: none"
            @click.self="del.open = false">
            <div class="sheet" role="dialog" aria-modal="true">
                <form method="POST" :action="del.url">
                    @csrf
                    @method('DELETE')
                    <div class="sheet-head">
                        <span class="ic rose"><i class="bi bi-trash3"></i></span>
                        <div>
                            <h3>ক্রেতাকে সরাবেন?</h3>
                            <p x-text="del.name"></p>
                        </div>
                    </div>
                    <p class="sheet-note mb-0">কোনো বিক্রি নথিভুক্ত হয়নি বলেই সরানো যাচ্ছে। বিক্রির হিসাব থাকলে ক্রেতা
                        সরানো যায় না।</p>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="del.open = false">ফিরে
                            যান</button>
                        <button type="submit" class="btn btn-danger">হ্যাঁ, সরান</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
