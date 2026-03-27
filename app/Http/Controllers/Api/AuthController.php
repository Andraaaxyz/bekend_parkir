<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbUser;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user dan kembalikan token Sanctum.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = TbUser::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if (! $user->status_aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif. Hubungi Admin.',
            ], 403);
        }

        // Hapus token lama, buat baru
        $user->tokens()->delete();
        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

        // Log aktivitas
        TbLogAktivitas::create([
            'id_user'        => $user->id_user,
            'aktivitas'      => "Login berhasil sebagai {$user->role}",
            'waktu_aktivitas' => now(),
        ]);

        // Tentukan URL redirect sesuai role
        $redirectUrl = match ($user->role) {
            'admin'   => '/admin/dashboard',
            'petugas' => '/petugas/dashboard',
            'owner'   => '/owner/dashboard',
            default   => '/dashboard',
        };

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'token'        => $token,
                'redirect_url' => $redirectUrl,
                'user'         => [
                    'id_user'      => $user->id_user,
                    'nama_lengkap' => $user->nama_lengkap,
                    'username'     => $user->username,
                    'role'         => $user->role,
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        TbLogAktivitas::create([
            'id_user'        => $user->id_user,
            'aktivitas'      => 'Logout',
            'waktu_aktivitas' => now(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Profil user yang sedang login.
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user(),
        ]);
    }
}
