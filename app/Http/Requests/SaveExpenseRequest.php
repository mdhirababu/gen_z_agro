<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageFinance', $this->route('farm'));
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $expense = $this->route('expense');
        $creating = ! $expense;

        $cowExists = Rule::exists('cows', 'id')->where(fn($q) => $q
            ->where('farm_id', $farm->id)
            ->whereNull('deleted_at'));

        $rules = [
            'category' => ['required', Rule::in(array_keys(config('farm.expense_categories')))],
            'cow_id' => ['nullable', 'integer', $cowExists],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        // পেমেন্ট শুরু হওয়ার পর অঙ্ক বদলানো যায় না
        if ($creating || (float) $expense->paid_amount === 0.0) {
            $rules['total_amount'] = ['required', 'numeric', 'gt:0', 'max:99999999'];
        }

        if ($creating) {
            $rules['pay_mode'] = ['required', Rule::in(['paid', 'unpaid', 'partial'])];
            $rules['paid_now'] = [Rule::requiredIf($this->input('pay_mode') === 'partial'), 'nullable', 'numeric', 'gt:0'];
            $rules['pay_method'] = ['required', Rule::in(array_keys(config('farm.payment_methods')))];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'category' => 'খরচের ধরন',
            'cow_id' => 'গরু',
            'expense_date' => 'তারিখ',
            'total_amount' => 'মোট টাকা',
            'quantity' => 'পরিমাণ',
            'paid_now' => 'পরিশোধের পরিমাণ',
            'pay_method' => 'মাধ্যম',
        ];
    }

    public function messages(): array
    {
        return [
            'cow_id.exists' => 'গরুটি এই খামারের হতে হবে।',
            'expense_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->route('expense') && $this->input('pay_mode') === 'partial') {
                $total = (float) $this->input('total_amount');
                $paid = (float) $this->input('paid_now');

                if ($total > 0 && $paid >= $total) {
                    $v->errors()->add('paid_now', 'আংশিক পরিশোধ মোট খরচের চেয়ে কম হতে হবে। পুরোটা দিলে "পুরো টাকা দেওয়া হয়েছে" বেছে নিন।');
                }
            }
        });
    }

    public function payload(): array
    {
        return Arr::only($this->validated(), [
            'category',
            'cow_id',
            'expense_date',
            'description',
            'vendor_name',
            'quantity',
            'unit',
            'notes',
        ]);
    }

    public function total(): ?float
    {
        $total = Arr::get($this->validated(), 'total_amount');

        return $total === null ? null : round((float) $total, 2);
    }

    public function payment(): array
    {
        return [
            'mode' => $this->input('pay_mode', 'unpaid'),
            'amount' => $this->input('paid_now'),
            'method' => $this->input('pay_method', 'cash'),
            'date' => $this->input('expense_date'),
        ];
    }
}
