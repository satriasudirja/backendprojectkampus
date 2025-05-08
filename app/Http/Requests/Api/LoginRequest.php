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
            'captcha_id' => 'required|string|uuid',
            'slider_position' => 'required|numeric|min:0|max:100',
        ];
    }

    public function messages()
    {
        return [
            'nip.required' => 'NIP wajib diisi',
            'password.required' => 'Password wajib diisi',
            'captcha_id.required' => 'CAPTCHA ID diperlukan',
            'captcha_id.uuid' => 'CAPTCHA ID tidak valid',
            'slider_position.required' => 'Posisi slider diperlukan',
            'slider_position.numeric' => 'Posisi slider harus berupa angka',
            'slider_position.min' => 'Posisi slider tidak valid',
            'slider_position.max' => 'Posisi slider tidak valid',
        ];
    }
}