<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbTransaksi extends Model
{
    use HasFactory;

    protected $table = 'tb_transaksi';
    protected $primaryKey = 'id_parkir';

    protected $fillable = [
        'id_kendaraan',
        'waktu_masuk',
        'waktu_keluar',
        'id_tarif',
        'durasi',
        'biaya_total',
        'status',
        'id_user',
        'id_area',
    ];

    protected $casts = [
        'waktu_masuk'  => 'datetime',
        'waktu_keluar' => 'datetime',
        'durasi'       => 'decimal:2',
        'biaya_total'  => 'decimal:2',
    ];

    // Relationships
    public function kendaraan()
    {
        return $this->belongsTo(TbKendaraan::class, 'id_kendaraan', 'id_kendaraan');
    }

    public function tarif()
    {
        return $this->belongsTo(TbTarif::class, 'id_tarif', 'id_tarif');
    }

    public function user()
    {
        return $this->belongsTo(TbUser::class, 'id_user', 'id_user');
    }

    public function area()
    {
        return $this->belongsTo(TbAreaParkir::class, 'id_area', 'id_area');
    }
}
