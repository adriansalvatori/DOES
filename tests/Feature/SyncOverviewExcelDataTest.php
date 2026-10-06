<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncOverviewExcelDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_substatus_enum_contains_new_and_updated_cases(): void
    {
        $this->assertEquals('CANCELADA', Substatus::CANCELADA->label());
        $this->assertEquals(CoreStatus::ARCHIVED, Substatus::CANCELADA->defaultCoreStatus());

        $this->assertEquals('NO REALIZADA / TRANSFERIDA', Substatus::NO_REALIZADA_TRANSFERIDA->label());
        $this->assertEquals(CoreStatus::ARCHIVED, Substatus::NO_REALIZADA_TRANSFERIDA->defaultCoreStatus());

        // Ticket has lavender styling
        $this->assertStringContainsString('#EAD1DC', Substatus::TICKET->badgeStyle());
    }

    public function test_sync_command_dry_run_executes_successfully(): void
    {
        $this->artisan('app:sync-overview-excel', ['--dry-run' => true])
            ->expectsOutputToContain('MODO SIMULACIÓN (DRY RUN) ACTIVADO')
            ->expectsOutputToContain('Simulación finalizada con éxito')
            ->assertSuccessful();
    }
}
