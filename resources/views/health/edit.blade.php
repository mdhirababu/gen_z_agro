@extends('layouts.farm')
@section('title', 'স্বাস্থ্য রেকর্ড সম্পাদনা')

@section('content')
    @php
        $meta = $event->kindMeta();
        $vaccines = config('farm.vaccines');
    @endphp

    <div class="page-head">
        <div class="d-flex align-items-center gap-3">
            <span class="ic {{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
            <div>
                <h1>{{ $meta['label'] }} সম্পাদনা</h1>
                <p>{{ $event->cow ? $event->cow->tag_number : '' }}, {{ $event->event_date->format('d/m/Y') }}</p>
            </div>
        </div>
        <a href="{{ route('farms.health.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            @if ($event->expense && $canFinance)
                <div class="alert alert-info">
                    এই রেকর্ডের খরচ ({{ \App\Support\Money::fmt($event->expense->total_amount) }}) এখান থেকে বদলানো যায় না।
                    <a href="{{ route('farms.expenses.show', [$farm, $event->expense]) }}">খরচের পাতায় যান</a>।
                </div>
            @endif

            <form method="POST" action="{{ route('farms.health.update', [$farm, $event]) }}" x-data="{ kind: @js($event->kind), code: @js((string) old('vaccine_code', $event->vaccine_code)) }">
                @csrf
                @method('PUT')
                <input type="hidden" name="kind" value="{{ $event->kind }}">

                <div class="panel h-auto mb-3">
                    <div class="panel-head">বিবরণ</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="event_date" class="form-label">তারিখ</label>
                                    <input id="event_date" type="date" name="event_date"
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('event_date', $event->event_date->format('Y-m-d')) }}"
                                        class="form-control @error('event_date') is-invalid @enderror" required>
                                    @error('event_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @if ($event->kind === 'vaccination')
                                <div class="col-md-6">
                                    <div class="field">
                                        <label for="vaccine_code" class="form-label">কোন টিকা</label>
                                        <select id="vaccine_code" name="vaccine_code" class="form-select" x-model="code">
                                            @foreach ($vaccines as $key => $v)
                                                <option value="{{ $key }}">{{ $v['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6"
                                @if ($event->kind === 'vaccination') x-show="code === 'other'" x-cloak @endif>
                                <div class="field">
                                    <label for="title" class="form-label">নাম</label>
                                    <input id="title" type="text" name="title"
                                        value="{{ old('title', $event->title) }}"
                                        class="form-control @error('title') is-invalid @enderror">
                                    @error('title')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="details" class="form-label">কী সমস্যা, কী করা হলো</label>
                            <textarea id="details" name="details" rows="2" class="form-control">{{ old('details', $event->details) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="medicine" class="form-label">ওষুধ বা টিকার নাম (ব্র্যান্ড)</label>
                                    <input id="medicine" type="text" name="medicine"
                                        value="{{ old('medicine', $event->medicine) }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="vet_name" class="form-label">ডাক্তারের নাম</label>
                                    <input id="vet_name" type="text" name="vet_name"
                                        value="{{ old('vet_name', $event->vet_name) }}" class="form-control">
                                </div>
                            </div>
                        </div>

                        @if ($event->kind === 'treatment')
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="field">
                                        <label class="form-label d-block">বর্তমান অবস্থা</label>
                                        <div class="btn-group" role="group">
                                            <input type="radio" class="btn-check" name="outcome" id="oc-ongoing"
                                                value="ongoing" @checked(old('outcome', $event->outcome) === 'ongoing')>
                                            <label class="btn btn-outline-primary" for="oc-ongoing">চিকিৎসা চলছে</label>
                                            <input type="radio" class="btn-check" name="outcome" id="oc-rec"
                                                value="recovered" @checked(old('outcome', $event->outcome) === 'recovered')>
                                            <label class="btn btn-outline-primary" for="oc-rec">সুস্থ</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field">
                                        <label for="milk_withdrawal_until" class="form-label">দুধ বিক্রি বন্ধ কত তারিখ
                                            পর্যন্ত</label>
                                        <input id="milk_withdrawal_until" type="date" name="milk_withdrawal_until"
                                            value="{{ old('milk_withdrawal_until', $event->milk_withdrawal_until ? $event->milk_withdrawal_until->format('Y-m-d') : '') }}"
                                            class="form-control @error('milk_withdrawal_until') is-invalid @enderror">
                                        @error('milk_withdrawal_until')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="field mb-0" style="max-width: 20rem">
                            <label for="next_due_date" class="form-label">পরের তারিখ</label>
                            <input id="next_due_date" type="date" name="next_due_date"
                                value="{{ old('next_due_date', $event->next_due_date ? $event->next_due_date->format('Y-m-d') : '') }}"
                                class="form-control @error('next_due_date') is-invalid @enderror">
                            <div class="field-hint">তারিখ বদলালে কাজটি আবার "আসন্ন কাজ"-এ আসবে। মুছে দিলে কাজটি সরে যাবে।
                            </div>
                            @error('next_due_date')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>আপডেট
                        করুন</button>
                    <a href="{{ route('farms.health.index', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>
    </div>
@endsection
