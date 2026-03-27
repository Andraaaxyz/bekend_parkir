<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbTransaksi;
use App\Models\TbAreaParkir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    /**
     * Laporan ringkasan pendapatan dan jumlah kendaraan.
     * Filter: tanggal_dari, tanggal_sampai
     * Role: owner
     */
    public function ringkasan(Request $request)
    {
        $request->validate([
            'tanggal_dari'   => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari',
        ]);

        $query = TbTransaksi::where('status', 'keluar');

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('waktu_masuk', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('waktu_masuk', '<=', $request->tanggal_sampai);
        }

        $totalPendapatan  = (clone $query)->sum('biaya_total');
        $jumlahKendaraan  = (clone $query)->count();

        // Pecah per jenis kendaraan
        $perJenis = (clone $query)
            ->join('tb_kendaraan', 'tb_transaksi.id_kendaraan', '=', 'tb_kendaraan.id_kendaraan')
            ->select(
                'tb_kendaraan.jenis_kendaraan',
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(tb_transaksi.biaya_total) as total_pendapatan')
            )
            ->groupBy('tb_kendaraan.jenis_kendaraan')
            ->get();

        // Pendapatan harian
        $harian = (clone $query)
            ->select(
                DB::raw('DATE(waktu_masuk) as tanggal'),
                DB::raw('COUNT(*) as jumlah_kendaraan'),
                DB::raw('SUM(biaya_total) as pendapatan')
            )
            ->groupBy(DB::raw('DATE(waktu_masuk)'))
            ->orderBy('tanggal', 'desc')
            ->get();

        // Status area parkir saat ini
        $area = TbAreaParkir::select('id_area', 'nama_area', 'kapasitas', 'terisi')
            ->get()
            ->map(function ($a) {
                $a->sisa_slot = $a->kapasitas - $a->terisi;
                return $a;
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'          => [
                    'dari'   => $request->tanggal_dari   ?? 'Semua',
                    'sampai' => $request->tanggal_sampai ?? 'Semua',
                ],
                'total_pendapatan'  => 'Rp' . number_format($totalPendapatan, 0, ',', '.'),
                'total_pendapatan_raw' => $totalPendapatan,
                'jumlah_kendaraan'  => $jumlahKendaraan,
                'per_jenis'         => $perJenis,
                'harian'            => $harian,
                'status_area'       => $area,
            ],
        ]);
    }

    /**
     * Laporan detail transaksi (dapat difilter).
     */
    public function detail(Request $request)
    {
        $request->validate([
            'tanggal_dari'    => 'nullable|date',
            'tanggal_sampai'  => 'nullable|date|after_or_equal:tanggal_dari',
            'jenis_kendaraan' => 'nullable|in:motor,mobil,lainnya',
            'id_area'         => 'nullable|exists:tb_area_parkir,id_area',
        ]);

        $query = TbTransaksi::with([
            'kendaraan:id_kendaraan,plat_nomor,jenis_kendaraan,pemilik',
            'tarif:id_tarif,tarif_per_jam',
            'area:id_area,nama_area',
            'user:id_user,nama_lengkap',
        ])->where('status', 'keluar');

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('waktu_masuk', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('waktu_masuk', '<=', $request->tanggal_sampai);
        }

        if ($request->filled('jenis_kendaraan')) {
            $query->whereHas('kendaraan', function ($q) use ($request) {
                $q->where('jenis_kendaraan', $request->jenis_kendaraan);
            });
        }

        if ($request->filled('id_area')) {
            $query->where('id_area', $request->id_area);
        }

        $transaksi = $query->orderBy('waktu_masuk', 'desc')->paginate(25);

        return response()->json([
            'success' => true,
            'data'    => $transaksi,
        ]);
    }

    /**
     * Laporan kendaraan yang masih parkir (status = masuk).
     */
    public function kendaraanAktif()
    {
        $aktif = TbTransaksi::with([
            'kendaraan:id_kendaraan,plat_nomor,jenis_kendaraan,pemilik,warna',
            'area:id_area,nama_area',
            'tarif:id_tarif,tarif_per_jam',
        ])->where('status', 'masuk')
          ->orderBy('waktu_masuk')
          ->get()
          ->map(function ($t) {
              $t->estimasi_durasi_jam = ceil(now()->diffInMinutes($t->waktu_masuk) / 60);
              $t->estimasi_biaya      = 'Rp' . number_format(
                  $t->estimasi_durasi_jam * $t->tarif->tarif_per_jam,
                  0,
                  ',',
                  '.'
              );
              return $t;
          });

        return response()->json([
            'success'          => true,
            'jumlah_aktif'     => $aktif->count(),
            'data'             => $aktif,
        ]);
    }
}
