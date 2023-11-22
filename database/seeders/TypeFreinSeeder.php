<?php

namespace Database\Seeders;

use App\Models\TypeFrein;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeFreinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items=[
            ['description'=>'Tranche'],
            ['description'=>'Etage'],
            ['description'=>'Orientation'],
            ['description'=>'Avance'],
            ['description'=>'Prix'],
            ['description'=>'Superficie'],
            ['description'=>'Prix/Superficie'],
            ['description'=>'Emplacement'],
            ['description'=>'Typologie'],
            ['description'=>'Vue'],
            ['description'=>'Ne souhaite plus investir'],

        ];
        foreach ($items as $item)
        {
            TypeFrein::factory()->create($item);
        }
    }
}
