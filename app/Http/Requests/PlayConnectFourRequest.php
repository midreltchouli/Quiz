<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayConnectFourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'column' => ['required', 'integer', 'between:0,6'],
            'move' => ['required', 'integer', 'between:0,42'],
        ];
    }
}
