<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbAreaParkir extends Model
{
    use HasFactory;

    protected $table = 'tb_area_parkir';
    protected $primaryKey = 'id_area';

    protected $fillable = [
        'nama_area',
        'kapasitas',
        'terisi',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'terisi'    => 'integer',
    ];

    // Relationships
    public function transaksi()
    {
        return $this->hasMany(TbTransaksi::class, 'id_area', 'id_area');
    }

    // Accessor: sisa slot
    public function getSisaSlotAttribute(): int
    {
        return $this->kapasitas - $this->terisi;
    }

    // Helper: cek apakah area tersedia
    public function isTersedia(): bool
    {
        return $this->kapasitas > $this->terisi;
    }
}
