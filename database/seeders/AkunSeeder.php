<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class AkunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('akun')->insert([
            ['header_akun' => 1, 'kode_akun' => '1101', 'nama_akun' => 'Kas', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1102', 'nama_akun' => 'Bank', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1103', 'nama_akun' => 'Piutang Rental', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1201', 'nama_akun' => 'Kendaraan Rental', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1202', 'nama_akun' => 'Akumulasi Penyusutan Kendaraan', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1301', 'nama_akun' => 'Peralatan Kantor', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 1, 'kode_akun' => '1302', 'nama_akun' => 'Akumulasi Penyusutan Peralatan', 'created_at' => $now, 'updated_at' => $now],

            ['header_akun' => 2, 'kode_akun' => '2101', 'nama_akun' => 'Hutang Usaha', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 2, 'kode_akun' => '2102', 'nama_akun' => 'Hutang Pajak', 'created_at' => $now, 'updated_at' => $now],

            ['header_akun' => 3, 'kode_akun' => '3101', 'nama_akun' => 'Modal Pemilik', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 3, 'kode_akun' => '3201', 'nama_akun' => 'Prive Pemilik', 'created_at' => $now, 'updated_at' => $now],

            ['header_akun' => 4, 'kode_akun' => '4101', 'nama_akun' => 'Pendapatan Rental Motor', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 4, 'kode_akun' => '4102', 'nama_akun' => 'Pendapatan Rental Mobil', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 4, 'kode_akun' => '4103', 'nama_akun' => 'Pendapatan Denda', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 4, 'kode_akun' => '4104', 'nama_akun' => 'Pendapatan Lain-lain', 'created_at' => $now, 'updated_at' => $now],

            ['header_akun' => 5, 'kode_akun' => '5101', 'nama_akun' => 'Beban Bensin', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5102', 'nama_akun' => 'Beban Servis Kendaraan', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5103', 'nama_akun' => 'Beban Gaji', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5104', 'nama_akun' => 'Beban Listrik', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5105', 'nama_akun' => 'Beban Internet', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5106', 'nama_akun' => 'Beban Administrasi', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5107', 'nama_akun' => 'Beban Pajak Kendaraan', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5108', 'nama_akun' => 'Beban Penyusutan Kendaraan', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5109', 'nama_akun' => 'Beban ATK', 'created_at' => $now, 'updated_at' => $now],
            ['header_akun' => 5, 'kode_akun' => '5110', 'nama_akun' => 'Beban Operasional', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
