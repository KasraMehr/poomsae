<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255'], 'device_name' => ['sometimes', 'required', 'string', 'max:100']];
    }

    public function authenticatedUser(): User
    {
        $user = User::where('email', $this->string('email')->toString())->first();
        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'اطلاعات ورود معتبر نیست.']);
        }

        return $user;
    }
}
