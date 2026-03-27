<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;

class LogAktivitasController extends Controller
{
    /**
     * Tampilkan log aktivitas (Admin only).
     */
    public function index(Request $request)
    {
        $request->validate([
            'tanggal_dari'   => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
            'id_user'        => 'nullable|exists:tb_user,id_user',
        ]);

        $query = TbLogAktivitas::with(['user:id_user,nama_lengkap,username,role']);

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('waktu_aktivitas', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('waktu_aktivitas', '<=', $request->tanggal_sampai);
        }

        if ($request->filled('id_user')) {
            $query->where('id_user', $request->id_user);
        }

        $logs = $query->orderBy('waktu_aktivitas', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * Detail log.
     */
    public function show($id)
    {
        $log = TbLogAktivitas::with(['user:id_user,nama_lengkap,username,role'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $log,
        ]);
    }
}
