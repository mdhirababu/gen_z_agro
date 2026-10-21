<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->route('farm');

        // যোগ করা: যেকোনো সদস্য। বদলানো: মালিক ও ম্যানেজার
        return $this->route('healthEvent')
            ? Gate::allows('manageHealth', $farm)
            : Gate::allows('view', $farm);
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $editing = (bool) $this->route('healthEvent');
        $kind = $this->input('kind');

        $titleRequired = $kind === 'treatment'
            || ($kind === 'vaccination' && $this->input('vaccine_code') === 'other');

        $rules = [
            'kind' => ['required', Rule::in(array_keys(config('farm.health_kinds')))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'vaccine_code' => [Rule::requiredIf($kind === 'vaccination'), 'nullable', Rule::in(array_keys(config('farm.vaccines')))],
            'title' => [Rule::requiredIf($titleRequired), 'nullable', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
            'medicine' => ['nullable', 'string', 'max:150'],
            'vet_name' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', Rule::in(['ongoing', 'recovered'])],
            'milk_withdrawal_until' => ['nullable', 'date', 'after_or_equal:event_date'],
            'next_due_date' => ['nullable', 'date', 'after:event_date'],
        ];

        if (! $editing) {
            $rules['cow_ids'] = ['required', 'array', 'min:1', 'max:200'];
            $rules['cow_ids.*'] = ['integer', 'distinct', Rule::exists('cows', 'id')->where(fn($q) => $q
                ->where('farm_id', $farm->id)
                ->whereNull('deleted_at'))];

            // খরচের অংশ শুধু মালিক ও ম্যানেজারের জন্য। স্টাফের পাঠানো খরচের তথ্য ধরা হয় না।
            if (Gate::allows('manageFinance', $farm)) {
                $hasCost = $this->filled('cost_amount');

                $rules['cost_amount'] = ['nullable', 'numeric', 'gt:0', 'max:99999999'];
                $rules['pay_mode'] = [Rule::requiredIf($hasCost), 'nullable', Rule::in(['paid', 'unpaid', 'partial'])];
                $rules['paid_now'] = [Rule::requiredIf($hasCost && $this->input('pay_mode') === 'partial'), 'nullable', 'numeric', 'gt:0'];
                $rules['pay_method'] = ['nullable', Rule::in(array_keys(config('farm.payment_methods')))];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'kind' => 'ধরন',
            'event_date' => 'তারিখ',
            'vaccine_code' => 'টিকা',
            'title' => 'নাম',
            'cow_ids' => 'গরু',
            'milk_withdrawal_until' => 'দুধ বিক্রি বন্ধের তারিখ',
            'next_due_date' => 'পরের তারিখ',
            'cost_amount' => 'খরচ',
            'paid_now' => 'পরিশোধের পরিমাণ',
        ];
    }

    public function messages(): array
    {
        return [
            'cow_ids.required' => 'অন্তত একটি গরু বাছুন।',
            'cow_ids.*.exists' => 'গরুটি এই খামারের হতে হবে।',
            'title.required' => 'রোগ বা টিকার নাম লিখুন।',
            'next_due_date.after' => 'পরের তারিখ রেকর্ডের তারিখের পরে হতে হবে।',
            'milk_withdrawal_until.after_or_equal' => 'দুধ বিক্রি বন্ধের তারিখ রেকর্ডের তারিখের আগে হতে পারে না।',
            'event_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (
                $this->filled('cost_amount')
                && $this->input('pay_mode') === 'partial'
                && (float) $this->input('paid_now') >= (float) $this->input('cost_amount')
            ) {
                $v->errors()->add('paid_now', 'আংশিক পরিশোধ মোট খরচের চেয়ে কম হতে হবে।');
            }
        });
    }

    public function payload(): array
    {
        $d = Arr::only($this->validated(), [
            'kind',
            'event_date',
            'vaccine_code',
            'title',
            'details',
            'medicine',
            'vet_name',
            'outcome',
            'milk_withdrawal_until',
            'next_due_date',
        ]);

        $kind = $d['kind'];

        if ($kind !== 'vaccination') {
            $d['vaccine_code'] = null;
        }

        if ($kind === 'treatment') {
            $d['outcome'] = $d['outcome'] ?? 'ongoing';
        } else {
            $d['outcome'] = null;
            $d['milk_withdrawal_until'] = null;
        }

        // পরিচিত টিকার নাম নিজে থেকে বসে, যাতে একই টিকা সবসময় একই নামে থাকে
        if ($kind === 'vaccination' && ($d['vaccine_code'] ?? null) !== 'other') {
            $d['title'] = config('farm.vaccines')[$d['vaccine_code']]['label'] ?? 'টিকা';
        }

        if (empty($d['title'])) {
            $d['title'] = match ($kind) {
                'deworming' => 'কৃমিনাশক',
                'checkup' => 'স্বাস্থ্য পরীক্ষা',
                default => 'চিকিৎসা',
            };
        }

        return $d;
    }

    public function cowIds(): array
    {
        return array_map('intval', $this->validated()['cow_ids'] ?? []);
    }

    /** খরচ না থাকলে null */
    public function cost(): ?array
    {
        $amount = Arr::get($this->validated(), 'cost_amount');

        if ($amount === null) {
            return null;
        }

        return [
            'amount' => round((float) $amount, 2),
            'pay' => [
                'mode' => $this->input('pay_mode', 'paid'),
                'amount' => $this->input('paid_now'),
                'method' => $this->input('pay_method', 'cash'),
                'date' => $this->input('event_date'),
            ],
        ];
    }
}
