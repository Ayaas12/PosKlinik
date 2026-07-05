<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login – authenticates user and returns a Sanctum API token.
     * SECURITY: No credentials in URL params. Token stored client-side.
     * Rate limiting: applied via throttle:10,1 middleware in routes.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            // SECURITY: Generic error message — no credential leak in logs
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Akun tidak aktif. Hubungi administrator.'], 403);
        }

        // Revoke all old tokens for this user (single session policy)
        $user->tokens()->delete();

        // Create a new token
        $token = $user->createToken('pos-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role?->name,
                'role_display' => $user->role?->display_name,
            ],
        ]);
    }

    /**
     * Logout – revoke the current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('role');

        return response()->json([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role?->name,
                'role_display' => $user->role?->display_name,
                'is_active'    => $user->is_active,
            ],
        ]);
    }

    /**
     * Change password for current user.
     * SECURITY: Old password must be verified before changing.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak sesuai.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}
