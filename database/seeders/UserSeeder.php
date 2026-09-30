<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@kudos.com'],
            [
                'name' => 'Administrador Kudos',
                'password' => $password,
                'role' => UserRole::ADMIN,
                'active' => true,
            ]
        );

        // 2. Coordinator User (Camila)
        $coordinator = User::firstOrCreate(
            ['email' => 'camila@kudos.com'],
            [
                'name' => 'Camila (Coordinadora)',
                'password' => $password,
                'role' => UserRole::COORDINATOR,
                'active' => true,
            ]
        );
        $camilaDesigner = Designer::where('name', 'LIKE', '%Camila%')->first();
        if ($camilaDesigner) {
            $camilaDesigner->update(['user_id' => $coordinator->id]);
        }

        // 3. Euralíz (Admin & Designer)
        $euraliz = User::where('email', 'euraliz.jbg@gmail.com')
            ->orWhere('email', 'euraliz@kudos.com')
            ->first();

        if ($euraliz) {
            $euraliz->update([
                'name' => 'Euralíz Bravo',
                'role' => UserRole::ADMIN,
                'email' => 'euraliz.jbg@gmail.com',
                'password' => $password,
                'active' => true,
            ]);
        } else {
            $euraliz = User::create([
                'name' => 'Euralíz Bravo',
                'email' => 'euraliz.jbg@gmail.com',
                'password' => $password,
                'role' => UserRole::ADMIN,
                'active' => true,
            ]);
        }
        $euralizDesigner = Designer::find(1) ?? Designer::where('name', 'LIKE', '%Eural%')->first();
        if ($euralizDesigner) {
            $euralizDesigner->update(['user_id' => $euraliz->id]);
        }

        // 4. Adrián (Designer)
        $adrian = User::firstOrCreate(
            ['email' => 'adrian@kudos.com'],
            [
                'name' => 'Adrián Reinoza',
                'password' => $password,
                'role' => UserRole::DESIGNER,
                'active' => true,
            ]
        );
        $adrianDesigner = Designer::find(2) ?? Designer::where('name', 'LIKE', '%Adr%')->first();
        if ($adrianDesigner) {
            $adrianDesigner->update(['user_id' => $adrian->id]);
        }

        // 5. César (Designer)
        $cesar = User::firstOrCreate(
            ['email' => 'cesar@kudos.com'],
            [
                'name' => 'César Guzmán',
                'password' => $password,
                'role' => UserRole::DESIGNER,
                'active' => true,
            ]
        );
        $cesarDesigner = Designer::find(3) ?? Designer::where('name', 'LIKE', '%Cés%')->orWhere('name', 'LIKE', '%Cesar%')->first();
        if ($cesarDesigner) {
            $cesarDesigner->update(['user_id' => $cesar->id]);
        }

        // 6. Sales User
        User::firstOrCreate(
            ['email' => 'ventas@kudos.com'],
            [
                'name' => 'Ejecutivo Comercial',
                'password' => $password,
                'role' => UserRole::SALES,
                'active' => true,
            ]
        );
    }
}
