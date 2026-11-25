<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignTeamScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_team_includes_designers_and_the_lead_even_when_the_lead_is_an_admin(): void
    {
        $designer = User::factory()->designer()->create(['name' => 'Adrián']);
        $leadAdmin = User::factory()->admin()->create(['name' => 'Euralíz', 'is_lead' => true]);
        $admin = User::factory()->admin()->create(['name' => 'Office admin']);
        $reviewer = User::factory()->create(['name' => 'Camila', 'role' => 'coordinator', 'is_reviewer' => true]);
        $inactive = User::factory()->designer()->create(['name' => 'Former designer', 'active' => false]);

        $team = User::query()->designTeam()->pluck('id')->all();

        $this->assertContains($designer->id, $team);
        $this->assertContains($leadAdmin->id, $team);
        $this->assertNotContains($admin->id, $team);
        $this->assertNotContains($reviewer->id, $team);
        $this->assertNotContains($inactive->id, $team);
    }
}
