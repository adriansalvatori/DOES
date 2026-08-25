<?php

namespace Database\Seeders;

use App\Models\Designer;
use Illuminate\Database\Seeder;

class DesignerSeeder extends Seeder
{
    public function run(): void
    {
        $designers = [
            ['name' => 'Euralíz', 'trello_member_id' => '604b988f402e011cb47af102'],
            ['name' => 'Adrián', 'trello_member_id' => '673f6a10a72ad738f55b5c6a'],
            ['name' => 'César', 'trello_member_id' => '597276cab5d158dd73106f3d'],
        ];

        foreach ($designers as $designer) {
            Designer::updateOrCreate(
                ['name' => $designer['name']],
                ['trello_member_id' => $designer['trello_member_id'], 'active' => true]
            );
        }
    }
}
