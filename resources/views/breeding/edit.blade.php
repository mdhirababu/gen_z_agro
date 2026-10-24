@extends('layouts.farm')
@section('title', 'সেবা সম্পাদনা')

@section('content')
    <div class="page-head">
        <div>
            <h1>সেবা সম্পাদনা</h1>
            <p>{{ $event->cow ? $event->cow->tag_number : '' }}, {{ $event->service_date->format('d/m/Y') }}</p>
        </div>
        <a href="{{ route('farms.breeding.show', [$farm, $event]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>বিস্তারিতে ফিরুন
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="alert alert-info">অবস্থা (গর্ভবতী, প্রসব ইত্যাদি) এখান থেকে বদলায় না। সেবার পাতার ফর্ম থেকে বদলাতে
                হয়।</div>

            <form method="POST" action="{{ route('farms.breeding.update', [$farm, $event]) }}">
                @csrf
                @method('PUT')

                <div class="panel h-auto mb-3">
                    <div class="panel-head">সেবার তথ্য</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="service_date" class="form-label">সেবার তারিখ</label>
                                    <input id="service_date" type="date" name="service_date"
                                        max="{{ now()->toDateString() }}"
                                        value="{{ old('service_date', $event->service_date->format('Y-m-d')) }}"
                                        class="form-control @error('service_date') is-invalid @enderror" required>
                                    @error('service_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label class="form-label d-block">পদ্ধতি</label>
                                    <div class="btn-group" role="group">
                                        @foreach (config('farm.breeding_methods') as $key => $label)
                                            <input type="radio" class="btn-check" name="method"
                                                id="m-{{ $key }}" value="{{ $key }}"
                                                @checked(old('method', $event->method) === $key)>
                                            <label class="btn btn-outline-primary"
                                                for="m-{{ $key }}">{{ $label }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="sire_name" class="form-label">বীজ বা ষাঁড়</label>
                                    <input id="sire_name" type="text" name="sire_name"
                                        value="{{ old('sire_name', $event->sire_name) }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="technician" class="form-label">কে করলেন</label>
                                    <input id="technician" type="text" name="technician"
                                        value="{{ old('technician', $event->technician) }}" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            @if ($event->status === 'served')
                                <div class="col-md-6">
                                    <div class="field">
                                        <label for="next_check_date" class="form-label">গর্ভ পরীক্ষার তারিখ</label>
                                        <input id="next_check_date" type="date" name="next_check_date"
                                            value="{{ old('next_check_date', $event->next_check_date ? $event->next_check_date->format('Y-m-d') : '') }}"
                                            class="form-control @error('next_check_date') is-invalid @enderror">
                                        @error('next_check_date')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="expected_calving_date" class="form-label">প্রসবের সম্ভাব্য তারিখ</label>
                                    <input id="expected_calving_date" type="date" name="expected_calving_date"
                                        value="{{ old('expected_calving_date', $event->expected_calving_date ? $event->expected_calving_date->format('Y-m-d') : '') }}"
                                        class="form-control @error('expected_calving_date') is-invalid @enderror">
                                    @error('expected_calving_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field mb-0">
                            <label for="notes" class="form-label">নোট</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $event->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>আপডেট
                        করুন</button>
                    <a href="{{ route('farms.breeding.show', [$farm, $event]) }}"
                        class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>
    </div>
@endsection
