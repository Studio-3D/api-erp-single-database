<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items=[
            ['source'=>'Avito'],
            ['source'=>'Kekemonos'],
            ['source'=>'Palissade'],
            ['source'=>'Panneaux 4*3'],
            ['source'=>'Flyer'],
            ['source'=>'Caravane'],
            ['source'=>'Bouche à Oreille'],
            ['source'=>'Site Web'],
            ['source'=>'Facebook'],
            ['source'=>'Smsing'],
            ['source'=>'Phoning BDD'],
            ['source'=>'Youtube'],
            ['source'=>'Partenaire'],
            ['source'=>'Sarouty']
        ];
        foreach ($items as $item)
        {
            Source::factory()->create($item);
        }
    }
}
