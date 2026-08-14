<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
            'tag_id' => [
                'required',
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tag_id.exists' => 'This tag could not be found.',
        ];
    }
}