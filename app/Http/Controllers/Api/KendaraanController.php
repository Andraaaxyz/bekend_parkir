<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbKendaraan;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;

class KendaraanController extends Controller
{
    public function index(Request $request)
    {
        $query = TbKendaraan::with(['user:id_user,nama_lengkap,username']);

        // Filter berdasarkan jenis_kendaraan jika ada
        if ($request->filled('jenis_kendaraan')) {
            $query->where('jenis_kendaraan', $request->jenis_kendaraan);
        }

        $kendaraan = $query->orderBy('plat_nomor')->get();

        return response()->json([
            'success' => true,
            'data'    => $kendaraan,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plat_nomor'      => 'required|string|max:20|unique:tb_kendaraan,plat_nomor',
            'jenis_kendaraan' => 'required|in:motor,mobil,lainnya',
            'warna'           => 'nullable|string|max:50',
            'pemilik'         => 'required|string|max:255',
            'id_user'         => 'required|exists:tb_user,id_user',
        ]);

        $kendaraan = TbKendaraan::create($validated);

        $this->log($request, "Menambahkan kendaraan: {$kendaraan->plat_nomor} ({$kendaraan->jenis_kendaraan})");

        return response()->json([
            'success' => true,
            'message' => 'Kendaraan berhasil ditambahkan.',
            'data'    => $kendaraan,
        ], 201);
    }

    public function show($id)
    {
        $kendaraan = TbKendaraan::with(['user:id_user,nama_lengkap,username', 'transaksi' => function ($q) {
            $q->with(['area:id_area,nama_area', 'tarif:id_tarif,jenis_kendaraan,tarif_per_jam'])
              ->orderBy('waktu_masuk', 'desc')
              ->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $kendaraan,
        ]);
    }

    public function update(Request $request, $id)
    {
        $kendaraan = TbKendaraan::findOrFail($id);

        $validated = $request->validate([
            'plat_nomor'      => 'sometimes|required|string|max:20|unique:tb_kendaraan,plat_nomor,' . $id . ',id_kendaraan',
            'jenis_kendaraan' => 'sometimes|required|in:motor,mobil,lainnya',
            'warna'           => 'nullable|string|max:50',
            'pemilik'         => 'sometimes|required|string|max:255',
            'id_user'         => 'sometimes|required|exists:tb_user,id_user',
        ]);

        $kendaraan->update($validated);

        $this->log($request, "Mengupdate kendaraan: {$kendaraan->plat_nomor}");

        return response()->json([
            'success' => true,
            'message' => 'Kendaraan berhasil diupdate.',
            'data'    => $kendaraan->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $kendaraan = TbKendaraan::findOrFail($id);

        // Cek apakah kendaraan sedang parkir
        if ($kendaraan->transaksi()->where('status', 'masuk')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kendaraan sedang dalam kondisi parkir. Tidak dapat dihapus.',
            ], 422);
        }

        $plat = $kendaraan->plat_nomor;
        $kendaraan->delete();

        $this->log($request, "Menghapus kendaraan: {$plat}");

        return response()->json([
            'success' => true,
            'message' => 'Kendaraan berhasil dihapus.',
        ]);
    }

    /**
     * Cari kendaraan berdasarkan plat nomor.
     */
    public function cariPlat(Request $request)
    {
        $request->validate([
            'plat_nomor' => 'required|string',
        ]);

        $kendaraan = TbKendaraan::where('plat_nomor', 'LIKE', '%' . $request->plat_nomor . '%')
            ->with(['user:id_user,nama_lengkap', 'transaksiAktif'])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $kendaraan,
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
