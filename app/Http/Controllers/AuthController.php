<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => 'required|string|min:8|max:128',
            'role' => 'nullable|in:admin,organizer,viewer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'organizer',
            'last_active' => now(),
        ]);

        Setting::create([
            'user_id' => $user->id,
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'organization' => '',
            ],
            'notifications' => [
                'critical' => true,
                'warning' => true,
                'sound' => false,
                'browser' => false,
            ],
            'thresholds' => [
                'warning' => 70,
                'critical' => 90,
            ],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();

        if (!$user || !Hash::check($validated['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Nieprawidłowy adres e-mail lub hasło.'],
            ]);
        }

        $user->update(['last_active' => now()]);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'message' => 'Wylogowano pomyślnie.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Brak aktywnej sesji.'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'last_active' => $user->last_active,
        ]);
    }
}
