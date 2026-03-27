<?php

namespace Database\Seeders;

use App\Models\TbUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Bikin Admin Utama
        TbUser::create([
            'nama_lengkap' => 'Administrator Parkir',
            'username'     => 'admin',
            'password'     => Hash::make('admin123'),
            'role'         => 'admin',
            'status_aktif' => true,
        ]);

        // Bikin Petugas
        TbUser::create([
            'nama_lengkap' => 'Petugas Parkir',
            'username'     => 'petugas',
            'password'     => Hash::make('petugas123'),
            'role'         => 'petugas',
            'status_aktif' => true,
        ]);

        // Bikin Owner (Pemilik)
        TbUser::create([
            'nama_lengkap' => 'Ceo Parkir',
            'username'     => 'owner',
            'password'     => Hash::make('owner123'),
            'role'         => 'owner',
            'status_aktif' => true,
        ]);
    }
}
