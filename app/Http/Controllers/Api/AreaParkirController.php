<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbAreaParkir;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;

class AreaParkirController extends Controller
{
    public function index()
    {
        $areas = TbAreaParkir::orderBy('nama_area')->get()
            ->makeHidden([])
            ->each->append('sisa_slot');

        return response()->json([
            'success' => true,
            'data'    => $areas,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_area'  => 'required|string|max:255|unique:tb_area_parkir,nama_area',
            'kapasitas'  => 'required|integer|min:1',
        ]);

        $validated['terisi'] = 0;

        $area = TbAreaParkir::create($validated);

        $this->log($request, "Menambahkan area parkir: {$area->nama_area} (kapasitas: {$area->kapasitas})");

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil ditambahkan.',
            'data'    => $area,
        ], 201);
    }

    public function show($id)
    {
        $area = TbAreaParkir::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => tap($area)->append('sisa_slot'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $area = TbAreaParkir::findOrFail($id);

        $validated = $request->validate([
            'nama_area' => 'sometimes|required|string|max:255|unique:tb_area_parkir,nama_area,' . $id . ',id_area',
            'kapasitas' => 'sometimes|required|integer|min:1',
        ]);

        // Kapasitas tidak boleh lebih kecil dari jumlah yang sudah terisi
        if (isset($validated['kapasitas']) && $validated['kapasitas'] < $area->terisi) {
            return response()->json([
                'success' => false,
                'message' => "Kapasitas tidak boleh kurang dari jumlah kendaraan yang sedang parkir ({$area->terisi}).",
            ], 422);
        }

        $area->update($validated);

        $this->log($request, "Mengupdate area parkir: {$area->nama_area}");

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil diupdate.',
            'data'    => $area->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $area = TbAreaParkir::findOrFail($id);

        if ($area->terisi > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Area parkir masih memiliki kendaraan. Tidak dapat dihapus.',
            ], 422);
        }

        $nama = $area->nama_area;
        $area->delete();

        $this->log($request, "Menghapus area parkir: {$nama}");

        return response()->json([
            'success' => true,
            'message' => 'Area parkir berhasil dihapus.',
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
