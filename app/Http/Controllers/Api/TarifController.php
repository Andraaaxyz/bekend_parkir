<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbTarif;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;

class TarifController extends Controller
{
    public function index()
    {
        $tarif = TbTarif::orderBy('jenis_kendaraan')->get();

        return response()->json([
            'success' => true,
            'data'    => $tarif,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis_kendaraan' => 'required|in:motor,mobil,lainnya',
            'tarif_per_jam'   => 'required|numeric|min:0',
        ]);

        // Cek jika sudah ada tarif untuk jenis yang sama
        $existing = TbTarif::where('jenis_kendaraan', $validated['jenis_kendaraan'])->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Tarif untuk {$validated['jenis_kendaraan']} sudah ada. Gunakan update.",
            ], 422);
        }

        $tarif = TbTarif::create($validated);

        $this->log($request, "Menambahkan tarif untuk {$tarif->jenis_kendaraan}: Rp{$tarif->tarif_per_jam}/jam");

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil ditambahkan.',
            'data'    => $tarif,
        ], 201);
    }

    public function show($id)
    {
        $tarif = TbTarif::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $tarif,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tarif = TbTarif::findOrFail($id);

        $validated = $request->validate([
            'jenis_kendaraan' => 'sometimes|required|in:motor,mobil,lainnya',
            'tarif_per_jam'   => 'sometimes|required|numeric|min:0',
        ]);

        $tarif->update($validated);

        $this->log($request, "Mengupdate tarif ID {$id}: Rp{$tarif->tarif_per_jam}/jam");

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil diupdate.',
            'data'    => $tarif->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tarif = TbTarif::findOrFail($id);

        // Cek apakah tarif masih digunakan dalam transaksi aktif
        if ($tarif->transaksi()->where('status', 'masuk')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tarif masih digunakan dalam transaksi aktif.',
            ], 422);
        }

        $jenis = $tarif->jenis_kendaraan;
        $tarif->delete();

        $this->log($request, "Menghapus tarif untuk {$jenis}");

        return response()->json([
            'success' => true,
            'message' => 'Tarif berhasil dihapus.',
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
