<?php

namespace App\Http\Requests;

use App\Models\BreedingEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBreedingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->route('farm');

        // নতুন সেবা: যেকোনো সদস্য। বদলানো: মালিক ও ম্যানেজার
        return $this->route('breedingEvent')
            ? Gate::allows('manageHealth', $farm)
            : Gate::allows('view', $farm);
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $editing = (bool) $this->route('breedingEvent');

        $rules = [
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(config('farm.breeding_methods')))],
            'sire_name' => ['nullable', 'string', 'max:100'],
            'technician' => ['nullable', 'string', 'max:100'],
            'next_check_date' => ['nullable', 'date', 'after:service_date'],
            'expected_calving_date' => ['nullable', 'date', 'after:service_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $editing) {
            $rules['cow_id'] = ['required', 'integer', Rule::exists('cows', 'id')->where(fn($q) => $q
                ->where('farm_id', $farm->id)
                ->whereNull('deleted_at'))];

            // খরচের অংশ শুধু মালিক ও ম্যানেজারের জন্য
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
            'cow_id' => 'গরু',
            'service_date' => 'সেবার তারিখ',
            'method' => 'পদ্ধতি',
            'next_check_date' => 'গর্ভ পরীক্ষার তারিখ',
            'expected_calving_date' => 'প্রসবের সম্ভাব্য তারিখ',
            'cost_amount' => 'খরচ',
            'paid_now' => 'পরিশোধের পরিমাণ',
        ];
    }

    public function messages(): array
    {
        return [
            'cow_id.exists' => 'গরুটি এই খামারের হতে হবে।',
            'service_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
            'next_check_date.after' => 'গর্ভ পরীক্ষার তারিখ সেবার তারিখের পরে হতে হবে।',
            'expected_calving_date.after' => 'প্রসবের তারিখ সেবার তারিখের পরে হতে হবে।',
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

            if ($this->route('breedingEvent') || ! $this->filled('cow_id') || $v->errors()->has('cow_id')) {
                return;
            }

            $cow = $this->route('farm')->cows()->find($this->input('cow_id'));

            if (! $cow || $cow->sex !== 'female' || $cow->status !== 'active' || $cow->category === 'calf') {
                $v->errors()->add('cow_id', 'শুধু সক্রিয় প্রাপ্তবয়স্ক মাদি গরুর সেবা নথিভুক্ত করা যায়।');

                return;
            }

            $pregnant = BreedingEvent::withoutGlobalScope('farm')
                ->where('cow_id', $cow->id)->where('status', 'pregnant')->exists();

            if ($pregnant) {
                $v->errors()->add('cow_id', 'এই গরুটি আগে থেকেই গর্ভবতী। আগে প্রসব, গর্ভপাত বা "গর্ভ নেই" নথিভুক্ত করুন।');
            }
        });
    }

    public function payload(): array
    {
        $d = Arr::only($this->validated(), [
            'service_date',
            'method',
            'sire_name',
            'technician',
            'next_check_date',
            'expected_calving_date',
            'notes',
        ]);

        // তারিখ না দিলে সাধারণ ব্যবধান ধরে বসে
        $service = Carbon::parse($d['service_date']);

        $d['next_check_date'] = $d['next_check_date']
            ?? $service->copy()->addDays((int) config('farm.breeding.check_after_days'))->toDateString();

        $d['expected_calving_date'] = $d['expected_calving_date']
            ?? $service->copy()->addDays((int) config('farm.breeding.gestation_days'))->toDateString();

        return $d;
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
                'date' => $this->input('service_date'),
            ],
        ];
    }
}
