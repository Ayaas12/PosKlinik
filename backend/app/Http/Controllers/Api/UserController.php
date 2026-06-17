<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('role:id,name,display_name')
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->role_id, fn($q) => $q->where('role_id', $request->role_id))
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'role_id'   => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return response()->json(['message' => 'Pengguna berhasil ditambahkan.', 'user' => $user->load('role')], 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id'   => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        $user->update($validated);

        return response()->json(['message' => 'Pengguna berhasil diperbarui.', 'user' => $user->load('role')]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }
}
