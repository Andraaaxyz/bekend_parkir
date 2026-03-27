<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbKendaraan extends Model
{
    use HasFactory;

    protected $table = 'tb_kendaraan';
    protected $primaryKey = 'id_kendaraan';

    protected $fillable = [
        'plat_nomor',
        'jenis_kendaraan',
        'warna',
        'pemilik',
        'id_user',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(TbUser::class, 'id_user', 'id_user');
    }

    public function transaksi()
    {
        return $this->hasMany(TbTransaksi::class, 'id_kendaraan', 'id_kendaraan');
    }

    public function transaksiAktif()
    {
        return $this->hasOne(TbTransaksi::class, 'id_kendaraan', 'id_kendaraan')
                    ->where('status', 'masuk')
                    ->latest('waktu_masuk');
    }
}
