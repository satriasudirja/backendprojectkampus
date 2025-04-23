<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nip' => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'nip.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
        ];
    }
}