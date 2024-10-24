<?php

namespace Database\Seeders;

use App\Models\ServicesPrestataires;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServicesPrestatairesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items=[
            ['nom'=>'Revêtement Sol'],
            ['nom'=>'Peinture'],
            ['nom'=>'Plomberie'],
            ['nom'=>'Electricite'],
            ['nom'=>'Menuiserie Bois'],
            ['nom'=>'Menuiserie Aluminium'],
            ['nom'=>'Maçonnerie'],
            ['nom'=>'Climatisation'],
            ['nom'=>'Vernissage'],
            ['nom'=>'Revêtement Mûr'],
            ['nom'=>'Ferronnerie'],

        ];
        foreach ($items as $item)
        {
            ServicesPrestataires::factory()->create($item);
        }
    }
}
