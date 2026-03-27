<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbTarif extends Model
{
    use HasFactory;

    protected $table = 'tb_tarif';
    protected $primaryKey = 'id_tarif';

    protected $fillable = [
        'jenis_kendaraan',
        'tarif_per_jam',
    ];

    protected $casts = [
        'tarif_per_jam' => 'decimal:2',
    ];

    // Relationships
    public function transaksi()
    {
        return $this->hasMany(TbTransaksi::class, 'id_tarif', 'id_tarif');
    }
}
