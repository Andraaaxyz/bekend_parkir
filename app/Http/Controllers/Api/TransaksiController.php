<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbKendaraan;
use App\Models\TbAreaParkir;
use App\Models\TbTarif;
use App\Models\TbTransaksi;
use App\Models\TbLogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    // =========================================================================
    // KENDARAAN MASUK
    // =========================================================================

    /**
     * Proses kendaraan masuk.
     * Input: plat_nomor, jenis_kendaraan
     * Otomatis: waktu_masuk, pilih area tersedia, set status = masuk
     */
    public function kendaraanMasuk(Request $request)
    {
        $validated = $request->validate([
            'plat_nomor'      => 'required|string|max:20',
            'jenis_kendaraan' => 'required|in:motor,mobil,lainnya',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // 1. Cari atau daftarkan kendaraan berdasarkan plat_nomor
            $kendaraan = TbKendaraan::where('plat_nomor', strtoupper($validated['plat_nomor']))->first();

            if (! $kendaraan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kendaraan dengan plat nomor tersebut belum terdaftar. Daftarkan terlebih dahulu.',
                ], 404);
            }

            // 2. Cek apakah kendaraan sudah ada yang sedang parkir
            $transaksiAktif = TbTransaksi::where('id_kendaraan', $kendaraan->id_kendaraan)
                ->where('status', 'masuk')
                ->first();

            if ($transaksiAktif) {
                return response()->json([
                    'success' => false,
                    'message' => "Kendaraan {$kendaraan->plat_nomor} masih dalam kondisi parkir (ID Transaksi: #{$transaksiAktif->id_parkir}).",
                ], 422);
            }

            // 3. Cari tarif berdasarkan jenis kendaraan
            $tarif = TbTarif::where('jenis_kendaraan', $validated['jenis_kendaraan'])->first();

            if (! $tarif) {
                return response()->json([
                    'success' => false,
                    'message' => "Tarif untuk jenis kendaraan '{$validated['jenis_kendaraan']}' belum dikonfigurasi.",
                ], 422);
            }

            // 4. Pilih area parkir yang masih tersedia (kapasitas > terisi), gunakan lock untuk concurrency
            $area = TbAreaParkir::whereColumn('kapasitas', '>', 'terisi')
                ->lockForUpdate()
                ->first();

            if (! $area) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua area parkir penuh. Tidak dapat menerima kendaraan baru.',
                ], 422);
            }

            // 5. Buat transaksi
            $transaksi = TbTransaksi::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'waktu_masuk'  => now(),
                'waktu_keluar' => null,
                'id_tarif'     => $tarif->id_tarif,
                'durasi'       => null,
                'biaya_total'  => null,
                'status'       => 'masuk',
                'id_user'      => $request->user()->id_user,
                'id_area'      => $area->id_area,
            ]);

            // 6. Increment jumlah terisi pada area
            $area->increment('terisi');

            // 7. Log aktivitas
            TbLogAktivitas::create([
                'id_user'        => $request->user()->id_user,
                'aktivitas'      => "Kendaraan masuk: {$kendaraan->plat_nomor} ke area {$area->nama_area}",
                'waktu_aktivitas' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil masuk.',
                'data'    => [
                    'id_parkir'    => $transaksi->id_parkir,
                    'plat_nomor'   => $kendaraan->plat_nomor,
                    'jenis'        => $kendaraan->jenis_kendaraan,
                    'pemilik'      => $kendaraan->pemilik,
                    'area'         => $area->nama_area,
                    'waktu_masuk'  => $transaksi->waktu_masuk->format('Y-m-d H:i:s'),
                    'tarif_per_jam' => $tarif->tarif_per_jam,
                ],
            ], 201);
        });
    }

    // =========================================================================
    // KENDARAAN KELUAR
    // =========================================================================

    /**
     * Proses kendaraan keluar.
     * Cari berdasarkan plat_nomor dengan status masuk, hitung durasi & biaya.
     */
    public function kendaraanKeluar(Request $request)
    {
        $validated = $request->validate([
            'plat_nomor' => 'required|string|max:20',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // 1. Cari kendaraan
            $kendaraan = TbKendaraan::where('plat_nomor', strtoupper($validated['plat_nomor']))->first();

            if (! $kendaraan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kendaraan tidak ditemukan.',
                ], 404);
            }

            // 2. Cari transaksi aktif (status = masuk)
            $transaksi = TbTransaksi::with(['tarif', 'area', 'kendaraan'])
                ->where('id_kendaraan', $kendaraan->id_kendaraan)
                ->where('status', 'masuk')
                ->lockForUpdate()
                ->latest('waktu_masuk')
                ->first();

            if (! $transaksi) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak ada transaksi parkir aktif untuk kendaraan {$kendaraan->plat_nomor}.",
                ], 404);
            }

            // 3. Hitung durasi dan biaya
            $waktuKeluar = now();
            $waktuMasuk  = $transaksi->waktu_masuk;

            // Hitung durasi dalam jam (minimal 1 jam)
            $durasiJam = $waktuMasuk->diffInMinutes($waktuKeluar) / 60;
            $durasiJam = max(1, ceil($durasiJam)); // pembulatan ke atas, minimal 1 jam

            $biayaTotal = $durasiJam * $transaksi->tarif->tarif_per_jam;

            // 4. Update transaksi
            $transaksi->update([
                'waktu_keluar' => $waktuKeluar,
                'durasi'       => $durasiJam,
                'biaya_total'  => $biayaTotal,
                'status'       => 'keluar',
            ]);

            // 5. Decrement terisi pada area
            $area = $transaksi->area;
            $area->decrement('terisi');

            // 6. Log aktivitas
            TbLogAktivitas::create([
                'id_user'        => $request->user()->id_user,
                'aktivitas'      => "Kendaraan keluar: {$kendaraan->plat_nomor} dari area {$area->nama_area}, durasi: {$durasiJam} jam, biaya: Rp" . number_format($biayaTotal, 0, ',', '.'),
                'waktu_aktivitas' => now(),
            ]);

            // 7. Generate struk (JSON response)
            $struk = $this->generateStruk($transaksi, $kendaraan, $area, $durasiJam, $biayaTotal, $waktuMasuk, $waktuKeluar);

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil keluar.',
                'data'    => $struk,
            ]);
        });
    }

    // =========================================================================
    // CETAK STRUK
    // =========================================================================

    /**
     * Cetak/lihat struk berdasarkan ID transaksi.
     */
    public function cetakStruk($id)
    {
        $transaksi = TbTransaksi::with(['kendaraan', 'tarif', 'area', 'user:id_user,nama_lengkap'])
            ->findOrFail($id);

        if ($transaksi->status !== 'keluar') {
            return response()->json([
                'success' => false,
                'message' => 'Struk hanya dapat dicetak untuk kendaraan yang sudah keluar.',
            ], 422);
        }

        $struk = [
            'no_transaksi'   => $transaksi->id_parkir,
            'plat_nomor'     => $transaksi->kendaraan->plat_nomor,
            'jenis_kendaraan' => $transaksi->kendaraan->jenis_kendaraan,
            'pemilik'        => $transaksi->kendaraan->pemilik,
            'area_parkir'    => $transaksi->area->nama_area,
            'waktu_masuk'    => $transaksi->waktu_masuk->format('Y-m-d H:i:s'),
            'waktu_keluar'   => $transaksi->waktu_keluar->format('Y-m-d H:i:s'),
            'durasi_jam'     => $transaksi->durasi,
            'tarif_per_jam'  => 'Rp' . number_format($transaksi->tarif->tarif_per_jam, 0, ',', '.'),
            'biaya_total'    => 'Rp' . number_format($transaksi->biaya_total, 0, ',', '.'),
            'petugas'        => $transaksi->user->nama_lengkap,
            'dicetak_pada'   => now()->format('Y-m-d H:i:s'),
        ];

        return response()->json([
            'success' => true,
            'data'    => $struk,
        ]);
    }

    // =========================================================================
    // RIWAYAT & LIST TRANSAKSI
    // =========================================================================

    /**
     * Daftar semua transaksi (dengan filter opsional).
     */
    public function index(Request $request)
    {
        $query = TbTransaksi::with([
            'kendaraan:id_kendaraan,plat_nomor,jenis_kendaraan,pemilik',
            'tarif:id_tarif,jenis_kendaraan,tarif_per_jam',
            'area:id_area,nama_area',
            'user:id_user,nama_lengkap',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plat_nomor')) {
            $query->whereHas('kendaraan', function ($q) use ($request) {
                $q->where('plat_nomor', 'LIKE', '%' . $request->plat_nomor . '%');
            });
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('waktu_masuk', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('waktu_masuk', '<=', $request->tanggal_sampai);
        }

        $transaksi = $query->orderBy('waktu_masuk', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $transaksi,
        ]);
    }

    /**
     * Detail transaksi.
     */
    public function show($id)
    {
        $transaksi = TbTransaksi::with(['kendaraan', 'tarif', 'area', 'user:id_user,nama_lengkap'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $transaksi,
        ]);
    }

    // =========================================================================
    // HELPER PRIVATE
    // =========================================================================

    private function generateStruk(
        TbTransaksi $transaksi,
        TbKendaraan $kendaraan,
        TbAreaParkir $area,
        float $durasiJam,
        float $biayaTotal,
        $waktuMasuk,
        $waktuKeluar
    ): array {
        return [
            'no_transaksi'    => $transaksi->id_parkir,
            'plat_nomor'      => $kendaraan->plat_nomor,
            'jenis_kendaraan' => $kendaraan->jenis_kendaraan,
            'pemilik'         => $kendaraan->pemilik,
            'warna'           => $kendaraan->warna,
            'area_parkir'     => $area->nama_area,
            'waktu_masuk'     => $waktuMasuk->format('Y-m-d H:i:s'),
            'waktu_keluar'    => $waktuKeluar->format('Y-m-d H:i:s'),
            'durasi_jam'      => $durasiJam,
            'tarif_per_jam'   => 'Rp' . number_format($transaksi->tarif->tarif_per_jam, 0, ',', '.'),
            'biaya_total'     => 'Rp' . number_format($biayaTotal, 0, ',', '.'),
            'biaya_total_raw' => $biayaTotal,
        ];
    }
}
