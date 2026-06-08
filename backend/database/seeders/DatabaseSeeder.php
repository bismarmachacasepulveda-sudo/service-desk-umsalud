<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Support\Facades\Hash; // Importante para el password

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ÁREAS
        $areaIbba = Area::create(['name' => 'IBBA']);
        Area::create(['name' => 'IINSAD']);
        Area::create(['name' => 'ENFERMERIA']);
        
        // 2. CATEGORÍAS
        $catHardware = Category::create(['name' => 'Hardware']);
        Category::create(['name' => 'Software']);
        Category::create(['name' => 'Redes']);

        // 3. USUARIOS
        // Admin
        User::create([
            'name' => 'Brayan Machaca',
            'email' => 'admin@umsalud.bo',
            'password' => Hash::make('admin123'), // Usamos Hash::make explícito
            'role' => 'admin',
            'active' => true,
            'phone' => '00000000',
        ]);

        // Técnico
        $techBismar = User::create([
            'name' => 'Bismar Machaca',
            'email' => 'bismar@gmail.com',
            'password' => Hash::make('72509575'),
            'role' => 'tecnico',
            'phone' => '72509575',
            'expertise' => 'Hardware',
            'active' => true,
        ]);

        // Usuario Final
        $userLaura = User::create([
            'name' => 'Laura Flores',
            'email' => 'Lflores@gmail.com',
            'password' => Hash::make('72509575'),
            'role' => 'usuario',
            'phone' => '72534063',
            'area_id' => $areaIbba->id,
            'active' => true,
        ]);

        // 4. TICKET DE PRUEBA
        Ticket::create([
            'subject' => 'Falla de monitor',
            'description' => 'El monitor no enciende.',
            'priority' => 'media',
            'status' => 'abierto',
            'user_id' => $userLaura->id,
            'area_id' => $areaIbba->id,
            'category_id' => $catHardware->id
        ]);
    }
}