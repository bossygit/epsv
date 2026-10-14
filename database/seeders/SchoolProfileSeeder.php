<?php

namespace Database\Seeders;

use App\Models\SchoolProfile;
use Illuminate\Database\Seeder;

class SchoolProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profile = SchoolProfile::current();

        $profile->fill([
            'name' => 'École Panafricaine Séguynaud de Vinci',
            'short_name' => 'EPSV',
            'motto' => 'Discipline — Travail — Excellence',
            'address' => 'Avenue de l\'Indépendance',
            'city' => 'Brazzaville',
            'country' => 'Congo',
            'phone' => '+242 06 000 00 00',
            'email' => 'contact@epsv.cg',
            'director_name' => 'La Direction',
            'director_title' => 'Directeur Général',
            'settings' => SchoolProfile::defaultSettings(),
        ]);

        $profile->save();
    }
}
