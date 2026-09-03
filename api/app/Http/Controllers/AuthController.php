<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'preferred_currency' => 'NPR',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => Str::lower($request->string('email')->toString()),
            'password' => $request->string('password')->toString(),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The email or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Signed in successfully.',
            'user' => new UserResource($request->user()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            (new UserResource($request->user()))->resolve($request)
        );
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out successfully.']);
    }
}
