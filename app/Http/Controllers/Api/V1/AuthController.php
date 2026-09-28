<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password) || !$user->is_active) {
            throw ValidationException::withMessages(['email' => ['بيانات الدخول غير صحيحة أو الحساب غير نشط.']]);
        }

        if (!$user->stations()->whereKey($data['station_id'])->exists()) {
            throw ValidationException::withMessages(['station_id' => ['المستخدم غير مرتبط بهذه المحطة.']]);
        }

        $user->tokens()->where('name', $data['device_name'])->delete();

        $token = $user->createToken(
            $data['device_name'],
            ['api', 'station:' . $data['station_id']],
            now()->addDays(30)
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
                'user' => $user->only(['id', 'name', 'email']),
                'station_id' => (int) $data['station_id'],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load('stations')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
    }
}
