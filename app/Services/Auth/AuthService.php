<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * 建立新使用者並回傳 user 與 token。
     *
     * @param  array{name:string,email:string,password:string}  $payload
     * @return array{user: User, token: string}
     */
    public function register(array $payload): array
    {
        // password 會被 User model 的 'hashed' cast 自動加密
        $user = User::create([
            'name'     => $payload['name'],
            'email'    => $payload['email'],
            'password' => $payload['password'],
        ]);

        return [
            'user'  => $user,
            'token' => $user->createToken('spa')->plainTextToken,
        ];
    }

    /**
     * 驗證 email + 密碼，正確則發 token。
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['帳號或密碼不正確'],
            ]);
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('spa')->plainTextToken,
        ];
    }

    /**
     * 登出：撤銷目前使用的這顆 token。
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
