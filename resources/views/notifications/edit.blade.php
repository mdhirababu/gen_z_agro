@extends('layouts.farm')
@section('title', 'SMS বিজ্ঞপ্তি')

@section('content')
    @php
        $percent = $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0;
        $statusMap = [
            'sent' => ['পাঠানো হয়েছে', 'green'],
            'failed' => ['ব্যর্থ', 'red'],
            'skipped' => ['বাদ (সীমা)', 'gray'],
            'queued' => ['অপেক্ষায়', 'blue'],
        ];
        $kindMap = ['task_digest' => 'কাজের সারসংক্ষেপ', 'test' => 'পরীক্ষামূলক'];
    @endphp

    <div class="page-head">
        <div>
            <h1>SMS বিজ্ঞপ্তি</h1>
            <p>টিকা, গর্ভ পরীক্ষা ও প্রসবের সময় কাছে এলে মোবাইলে খবর।</p>
        </div>
    </div>

    @if ($driver === 'log')
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            এখন SMS আসলে যাচ্ছে না, শুধু লগ ফাইলে লেখা হচ্ছে। আসল গেটওয়ে জুড়তে <code>.env</code>-এ
            <code>SMS_DRIVER=http</code> আর গেটওয়ের সেটিং দিন।
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel h-auto mb-3">
                <div class="panel-head">সেটিং</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('farms.notifications.update', $farm) }}">
                        @csrf
                        @method('PUT')

                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom mb-3">
                            <div>
                                <label for="sms_enabled" class="fw-medium mb-0">SMS বিজ্ঞপ্তি চালু</label>
                                <div class="small text-muted">কাজের ৩ দিন আগে, সেদিন আর ১ দিন দেরিতে, সকাল ৮টায়।</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="sms_enabled"
                                    id="sms_enabled" value="1" @checked(old('sms_enabled', $farm->sms_enabled))>
                            </div>
                        </div>

                        <div class="field">
                            <label for="sms_phone" class="form-label">কোন নম্বরে পাঠানো হবে</label>
                            <div class="field-control">
                                <i class="bi bi-telephone lead-ic"></i>
                                <input id="sms_phone" type="text" name="sms_phone"
                                    value="{{ old('sms_phone', $farm->sms_phone) }}"
                                    class="form-control @error('sms_phone') is-invalid @enderror"
                                    placeholder="{{ $farm->phone ?: '01XXXXXXXXX' }}">
                            </div>
                            <div class="field-hint">ফাঁকা রাখলে খামারের ফোন নম্বরে ({{ $farm->phone ?: 'দেওয়া নেই' }})
                                যাবে।</div>
                            @error('sms_phone')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>সংরক্ষণ করুন</button>
                    </form>
                </div>
            </div>

            <div class="panel h-auto">
                <div class="panel-head">এই মাসের ব্যবহার</div>
                <div class="panel-body">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">SMS-এর অংশ (segment)</span>
                        <span class="fw-semibold">{{ $used }} / {{ $limit > 0 ? $limit : 'সীমাহীন' }}</span>
                    </div>
                    @if ($limit > 0)
                        <div class="usage-bar {{ $percent >= 90 ? 'is-full' : '' }}"><span
                                style="width: {{ $percent }}%"></span></div>
                    @endif
                    <div class="small text-muted mt-2">বাংলা SMS-এ প্রতি ৭০ অক্ষরে ১ segment। সীমা পার হলে SMS যাবে না।
                    </div>

                    <form method="POST" action="{{ route('farms.notifications.test', $farm) }}"
                        class="mt-3 pt-3 border-top">
                        @csrf
                        <button class="btn btn-outline-primary"><i class="bi bi-send me-1"></i>পরীক্ষামূলক SMS
                            পাঠান</button>
                        <div class="small text-muted mt-2">দিনে সর্বোচ্চ ৩টি। সংরক্ষিত নম্বরেই যায়।</div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-auto">
                <div class="panel-head">সাম্প্রতিক SMS</div>
                <div class="panel-body py-2">
                    @forelse ($logs as $log)
                        @php [$label, $pill] = $statusMap[$log->status] ?? [$log->status, 'gray']; @endphp
                        <div class="pay-row-item">
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $kindMap[$log->kind] ?? $log->kind }}</div>
                                <div class="small text-muted">{{ $log->created_at->format('d/m/Y H:i') }},
                                    {{ $log->phone }}, {{ $log->segments }} segment</div>
                                @if ($log->error)
                                    <div class="small text-danger">{{ $log->error }}</div>
                                @endif
                            </div>
                            <span class="pill {{ $pill }}">{{ $label }}</span>
                        </div>
                    @empty
                        <div class="text-muted py-2">এখনো কোনো SMS যায়নি।</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
