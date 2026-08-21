<?php

namespace Database\Seeders;

use App\Models\Designer;
use Illuminate\Database\Seeder;

class DesignerSeeder extends Seeder
{
    public function run(): void
    {
        $designers = [
            ['name' => 'Euralíz', 'trello_member_id' => 'euraliz_trello_id'],
            ['name' => 'Adrián', 'trello_member_id' => 'adrian_trello_id'],
            ['name' => 'César', 'trello_member_id' => 'cesar_trello_id'],
        ];

        foreach ($designers as $designer) {
            Designer::updateOrCreate(
                ['name' => $designer['name']],
                ['trello_member_id' => $designer['trello_member_id'], 'active' => true]
            );
        }
    }
}
