<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbUser;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Tampilkan semua user (Admin only).
     */
    public function index(Request $request)
    {
        $users = TbUser::select('id_user', 'nama_lengkap', 'username', 'role', 'status_aktif', 'created_at')
            ->orderBy('id_user')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * Tambah user baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'username'     => 'required|string|max:100|unique:tb_user,username',
            'password'     => 'required|string|min:6',
            'role'         => 'required|in:admin,petugas,owner',
            'status_aktif' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = TbUser::create($validated);

        $this->log($request, "Menambahkan user baru: {$user->username} (role: {$user->role})");

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data'    => $user->only(['id_user', 'nama_lengkap', 'username', 'role', 'status_aktif']),
        ], 201);
    }

    /**
     * Detail user berdasarkan ID.
     */
    public function show($id)
    {
        $user = TbUser::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $user->makeHidden(['password', 'remember_token']),
        ]);
    }

    /**
     * Update user.
     */
    public function update(Request $request, $id)
    {
        $user = TbUser::findOrFail($id);

        $validated = $request->validate([
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'username'     => 'sometimes|required|string|max:100|unique:tb_user,username,' . $id . ',id_user',
            'password'     => 'nullable|string|min:6',
            'role'         => 'sometimes|required|in:admin,petugas,owner',
            'status_aktif' => 'boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        $this->log($request, "Mengupdate user: {$user->username}");

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate.',
            'data'    => $user->fresh()->makeHidden(['password', 'remember_token']),
        ]);
    }

    /**
     * Hapus user.
     */
    public function destroy(Request $request, $id)
    {
        $user = TbUser::findOrFail($id);

        if ($user->id_user === $request->user()->id_user) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri.',
            ], 403);
        }

        $username = $user->username;
        $user->delete();

        $this->log($request, "Menghapus user: {$username}");

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }

    private function log(Request $request, string $aktivitas): void
    {
        TbLogAktivitas::create([
            'id_user'        => $request->user()->id_user,
            'aktivitas'      => $aktivitas,
            'waktu_aktivitas' => now(),
        ]);
    }
}
