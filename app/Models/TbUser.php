<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class TbUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'tb_user';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama_lengkap',
        'username',
        'password',
        'role',
        'status_aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password'    => 'hashed',
        'status_aktif' => 'boolean',
    ];

    // Relationships
    public function kendaraan()
    {
        return $this->hasMany(TbKendaraan::class, 'id_user', 'id_user');
    }

    public function transaksi()
    {
        return $this->hasMany(TbTransaksi::class, 'id_user', 'id_user');
    }

    public function logAktivitas()
    {
        return $this->hasMany(TbLogAktivitas::class, 'id_user', 'id_user');
    }
}
