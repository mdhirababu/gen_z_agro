<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordCalvingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('farm'));
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $event = $this->route('breedingEvent');

        return [
            'calved_on' => ['required', 'date', 'before_or_equal:today', 'after:' . $event->service_date->toDateString()],
            'calves' => ['required', 'array', 'min:1', 'max:3'],
            'calves.*.tag_number' => [
                'required',
                'string',
                'max:50',
                'distinct',
                Rule::unique('cows', 'tag_number')->where('farm_id', $farm->id),
            ],
            'calves.*.sex' => ['required', Rule::in(['female', 'male'])],
            'calves.*.name' => ['nullable', 'string', 'max:100'],
            'calves.*.weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:150'],
            'calves.*.alive' => ['nullable', 'boolean'],
            'update_dam' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'calved_on' => 'প্রসবের তারিখ',
            'calves' => 'বাছুর',
            'calves.*.tag_number' => 'বাছুরের ট্যাগ নম্বর',
            'calves.*.sex' => 'বাছুরের লিঙ্গ',
            'calves.*.weight_kg' => 'জন্মের ওজন',
        ];
    }

    public function messages(): array
    {
        return [
            'calved_on.after' => 'প্রসবের তারিখ সেবার তারিখের পরে হতে হবে।',
            'calved_on.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
            'calves.*.tag_number.unique' => 'এই ট্যাগ নম্বর এই খামারে আগেই ব্যবহার হয়েছে।',
            'calves.*.tag_number.distinct' => 'দুটি বাছুরের ট্যাগ নম্বর একই হতে পারে না।',
            'calves.*.weight_kg.max' => 'জন্মের ওজন ১৫০ কেজির বেশি হতে পারে না।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $cow = $this->route('breedingEvent')->cow;

            if (! $cow || $cow->status !== 'active') {
                $v->errors()->add('calves', 'মা গরুটি এখন সক্রিয় নেই, তাই প্রসব নথিভুক্ত করা যাবে না।');
            }
        });
    }
}
