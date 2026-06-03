<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

       // $this->call([SocieteSeeder::class, ]);
        $this->call([SocieteSeederMigrationSociete::class, ]);
        $this->call([UserSeeder::class,]);
        $this->call([ServicesPrestatairesSeeder::class,]);
         $this->call([SourceSeeder::class,]);
        $this->call([TypeFreinSeeder::class,]);


    }
}
