<?php

namespace App\Http\Requests;

use App\Models\Cow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farm = $this->route('farm');
        $cow = $this->route('cow');

        return $cow
            ? Gate::allows('update', $cow)
            : Gate::allows('create', [Cow::class, $farm]);
    }

    public function rules(): array
    {
        $farm = $this->route('farm');
        $cow = $this->route('cow');

        // মা অবশ্যই এই খামারের মাদি গরু
        $motherExists = Rule::exists('cows', 'id')->where(fn($q) => $q
            ->where('farm_id', $farm->id)
            ->where('sex', 'female')
            ->whereNull('deleted_at'));

        $mother = ['nullable', 'integer', $motherExists];
        if ($cow) {
            $mother[] = Rule::notIn([$cow->id]);
        }

        return [
            'tag_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cows', 'tag_number')->where('farm_id', $farm->id)->ignore($cow?->id),
            ],
            'name' => ['nullable', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(config('farm.categories')))],
            'breed' => ['nullable', Rule::in(array_keys(config('farm.breeds')))],
            'sex' => ['required', Rule::in(array_keys(config('farm.sexes')))],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today', 'required_if:acquisition_type,born'],
            'birth_date_estimated' => ['nullable', 'boolean'],
            'mother_id' => $mother,
            'sire_name' => ['nullable', 'string', 'max:100'],
            'acquisition_type' => ['required', Rule::in(array_keys(config('farm.acquisitions')))],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'opening_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['nullable', Rule::in(['active', 'dead', 'transferred'])], // sold শুধু বিক্রির লেনদেন থেকে
            'exit_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tag_number' => 'ট্যাগ নম্বর',
            'category' => 'গরুর ধরন',
            'sex' => 'লিঙ্গ',
            'birth_date' => 'জন্মতারিখ',
            'mother_id' => 'মা',
            'purchase_price' => 'ক্রয়মূল্য',
            'opening_value' => 'প্রারম্ভিক মূল্য',
            'purchase_date' => 'ক্রয়ের তারিখ',
            'exit_date' => 'তারিখ',
            'photo' => 'ছবি',
        ];
    }

    public function messages(): array
    {
        return [
            'tag_number.unique' => 'এই ট্যাগ নম্বর এই খামারে আগেই ব্যবহার হয়েছে।',
            'mother_id.exists' => 'মা অবশ্যই এই খামারের একটি মাদি গরু হতে হবে।',
            'birth_date.required_if' => 'খামারে জন্ম হলে জন্মতারিখ দিতে হবে।',
            'photo.max' => 'ছবি ৩ মেগাবাইটের বেশি হতে পারবে না।',
            'photo.image' => 'ফাইলটি একটি ছবি হতে হবে।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $category = $this->input('category');
            $sex = $this->input('sex');

            if (in_array($category, ['milking', 'dry', 'heifer'], true) && $sex !== 'female') {
                $v->errors()->add('sex', 'এই ধরনের গরু মাদি হতে হবে।');
            }
            if ($category === 'bull' && $sex !== 'male') {
                $v->errors()->add('sex', 'ষাঁড় নর হতে হবে।');
            }

            // মায়ের জন্ম বাছুরের জন্মের আগে হতে হবে
            if ($this->filled('mother_id') && $this->filled('birth_date') && ! $v->errors()->has('mother_id')) {
                $mother = $this->route('farm')->cows()->find($this->input('mother_id'));

                if (
                    $mother && $mother->birth_date
                    && $mother->birth_date->gte(Carbon::parse($this->input('birth_date')))
                ) {
                    $v->errors()->add('mother_id', 'মায়ের জন্মতারিখ বাছুরের জন্মতারিখের আগে হতে হবে।');
                }
            }
        });
    }

    /** যাচাই হওয়া তথ্য, ক্রয়ের ধরন অনুযায়ী পরিষ্কার করা */
    public function payload(): array
    {
        $cow = $this->route('cow');
        $data = Arr::except($this->validated(), ['photo', 'remove_photo']);

        $data['birth_date_estimated'] = $this->boolean('birth_date_estimated');
        $data['mother_id'] = $data['mother_id'] ?? null;

        switch ($data['acquisition_type']) {
            case 'born':
                $data['purchase_date'] = null;
                $data['purchase_price'] = 0;
                $data['opening_value'] = 0;
                break;
            case 'purchased':
                $data['purchase_price'] = $data['purchase_price'] ?? 0;
                $data['opening_value'] = 0;
                break;
            default: // existing
                $data['purchase_date'] = null;
                $data['purchase_price'] = 0;
                $data['opening_value'] = $data['opening_value'] ?? 0;
        }

        if ($cow) {
            if ($cow->status === Cow::STATUS_SOLD) {
                unset($data['status'], $data['exit_date']); // বিক্রিত গরুর অবস্থা এখানে বদলায় না
            } else {
                $status = $data['status'] ?? $cow->status;
                $data['status'] = $status;
                $data['exit_date'] = $status === 'active' ? null : ($data['exit_date'] ?? null);
            }
        } else {
            $data['status'] = Cow::STATUS_ACTIVE;
            $data['exit_date'] = null;
        }

        return $data;
    }
}
