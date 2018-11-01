<?php

use Illuminate\Database\Seeder;
use App\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Gun Gun Priatna',
            'email' => 'admin@recodeku.io',
            'password' => bcrypt('secret'),
            'status' => true
        ]);
    }
}
