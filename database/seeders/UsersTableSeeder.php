<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Admin Anas',
            'email' => 'admin@black.com',
            'email_verified_at' => now(),
            'password' => Hash::make('secret'),
            'api_key' => 'aJtCqGSfXG0VOIQ9FAiPnMr5XfHWUCNfnM23X0zlkm4lInumC04XB2vtzpGQNUXp',
            'api_secret' => 'hpO4mcliMbGmOqxO1wV4WyymeiazmgdxmURo16PW2bAAxBhGp9eyZ3rvgodghtIm',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
