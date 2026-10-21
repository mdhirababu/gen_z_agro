<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageFinance', $this->route('farm'));
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $type = $this->input('sale_type');
        $isQtyType = in_array($type, ['milk', 'manure'], true);
        $isTotalType = in_array($type, ['cow', 'other_income'], true);

        $inFarm = fn(string $table) => Rule::exists($table, 'id')->where(fn($q) => $q
            ->where('farm_id', $farm->id)
            ->whereNull('deleted_at'));

        return [
            'sale_type' => ['required', Rule::in(array_keys(config('farm.sale_types')))],
            'customer_id' => ['nullable', 'integer', $inFarm('customers')],
            'cow_id' => [Rule::requiredIf($type === 'cow'), 'nullable', 'integer', $inFarm('cows')],
            'sold_at' => ['required', 'date', 'before_or_equal:today'],
            'description' => [Rule::requiredIf($type === 'other_income'), 'nullable', 'string', 'max:255'],
            'quantity' => [Rule::requiredIf($isQtyType), 'nullable', 'numeric', 'gt:0', 'max:9999999'],
            'unit_price' => [Rule::requiredIf($isQtyType), 'nullable', 'numeric', 'gt:0', 'max:9999999'],
            'total_amount' => [Rule::requiredIf($isTotalType), 'nullable', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'pay_mode' => ['required', Rule::in(['paid', 'unpaid', 'partial'])],
            'paid_now' => [Rule::requiredIf($this->input('pay_mode') === 'partial'), 'nullable', 'numeric', 'gt:0'],
            'pay_method' => ['required', Rule::in(array_keys(config('farm.payment_methods')))],
        ];
    }

    public function attributes(): array
    {
        return [
            'sale_type' => 'বিক্রির ধরন',
            'customer_id' => 'ক্রেতা',
            'cow_id' => 'গরু',
            'sold_at' => 'তারিখ',
            'description' => 'বিবরণ',
            'quantity' => 'পরিমাণ',
            'unit_price' => 'দর',
            'total_amount' => 'মোট টাকা',
            'paid_now' => 'আদায়ের পরিমাণ',
            'pay_method' => 'মাধ্যম',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'ক্রেতা এই খামারের হতে হবে।',
            'cow_id.exists' => 'গরুটি এই খামারের হতে হবে।',
            'sold_at.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $mode = $this->input('pay_mode');

            if ($mode !== 'paid' && ! $this->filled('customer_id')) {
                $v->errors()->add('customer_id', 'বকেয়া বা আংশিক টাকার বিক্রিতে ক্রেতা বেছে নিতে হবে, নইলে কার কাছে পাওনা সেটা ধরা যাবে না।');
            }

            $total = $this->computedTotal();
            if ($mode === 'partial' && $total > 0 && (float) $this->input('paid_now') >= $total) {
                $v->errors()->add('paid_now', 'আংশিক আদায় মোট টাকার চেয়ে কম হতে হবে। পুরোটা পেলে "পুরো টাকা পেয়েছি" বেছে নিন।');
            }

            if ($this->input('sale_type') === 'cow' && $this->filled('cow_id') && ! $v->errors()->has('cow_id')) {
                $cow = $this->route('farm')->cows()->find($this->input('cow_id'));

                if (! $cow || $cow->status !== 'active') {
                    $v->errors()->add('cow_id', 'এই গরুটি এখন বিক্রির জন্য সক্রিয় নেই।');
                } elseif (
                    $cow->birth_date && ! $v->errors()->has('sold_at')
                    && $cow->birth_date->gt(Carbon::parse($this->input('sold_at')))
                ) {
                    $v->errors()->add('sold_at', 'বিক্রির তারিখ গরুর জন্মের আগে হতে পারে না।');
                }
            }
        });
    }

    /** দুধ ও গোবরে পরিমাণ × দর, বাকিগুলোতে লেখা মোট টাকা */
    private function computedTotal(): float
    {
        if (in_array($this->input('sale_type'), ['milk', 'manure'], true)) {
            return round((float) $this->input('quantity') * (float) $this->input('unit_price'), 2);
        }

        return round((float) $this->input('total_amount'), 2);
    }

    public function total(): float
    {
        return $this->computedTotal();
    }

    public function payload(): array
    {
        $type = $this->input('sale_type');

        $data = Arr::only($this->validated(), [
            'sale_type',
            'customer_id',
            'cow_id',
            'sold_at',
            'description',
            'quantity',
            'unit_price',
            'notes',
        ]);

        $data['unit'] = config("farm.sale_types.$type.unit");

        if (! in_array($type, ['milk', 'manure'], true)) {
            $data['quantity'] = null;
            $data['unit_price'] = null;
        }

        if ($type !== 'cow') {
            $data['cow_id'] = null;
        }

        return $data;
    }

    public function payment(): array
    {
        return [
            'mode' => $this->input('pay_mode', 'unpaid'),
            'amount' => $this->input('paid_now'),
            'method' => $this->input('pay_method', 'cash'),
            'date' => $this->input('sold_at'),
        ];
    }
}
