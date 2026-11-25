<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Dashboard\Index;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HoyTest extends TestCase
{
    use RefreshDatabase;

    private function seedAttention(): array
    {
        $overdue = Order::create([
            'company_name' => 'Clinica Arce', 'task_name' => 'Rotulo luminoso', 'wo_number' => '16398',
            'location_name' => 'Centro', 'in_workspace' => true, 'core_status' => CoreStatus::ENTRANTE,
            'current_due_date' => today()->subDays(2),
        ]);
        $blocked = Order::create([
            'company_name' => 'Ferreteria Andes', 'task_name' => 'Vinil vitrina', 'wo_number' => '16420',
            'location_name' => 'Doral', 'in_workspace' => true, 'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA, 'blocking_reason_other' => 'Faltan medidas',
        ]);
        $camilaOrder = Order::create([
            'company_name' => 'Estudio Pilates', 'task_name' => 'Tarjetas', 'wo_number' => '16405',
            'in_workspace' => true, 'core_status' => CoreStatus::ENVIADO_A_CAMILA,
        ]);
        RelatedTask::create(['order_id' => $camilaOrder->id, 'title' => 'Revisar arte', 'status' => 'todo', 'type' => RelatedTaskType::FOLLOW_UP_CAMILA]);
        $clientOrder = Order::create([
            'company_name' => 'Taller Ruiz', 'task_name' => 'Logo', 'wo_number' => '16377',
            'in_workspace' => true, 'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);
        RelatedTask::create(['order_id' => $clientOrder->id, 'title' => 'Llamar al cliente', 'status' => 'todo', 'type' => RelatedTaskType::FOLLOW_UP_CLIENTE]);

        return [$overdue, $blocked];
    }

    public function test_hoy_shows_summary_reason_tags_wo_and_location_without_detalle_buttons(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->seedAttention();

        $component = Livewire::actingAs($user)->test(Index::class)
            ->assertSee(__('Needs attention'))
            ->assertSee(__('Scheduled today'))
            ->assertSee(__('in progress'))
            ->assertSee(trans_choice('{1} overdue|[2,*] overdue', 1))
            ->assertSee(trans_choice('{1} blocked|[2,*] blocked', 1))
            ->assertSee(__('waiting on the client'))
            ->assertSee(__('Overdue :count d', ['count' => 2]))
            ->assertSee(__('Blocked').': Faltan medidas')
            ->assertSee(__('With Camila'))
            ->assertSee(__('Client not responding'))
            ->assertSee('16398')
            ->assertSee('Centro')
            ->assertSee(__('Unblock'))
            ->assertSee(__('Review now'))
            ->assertSee(__('Done'))
            ->assertSee('id="tour-dashboard-stats"', false)
            ->assertSee('id="tour-designer-pills"', false)
            ->assertSee(__('Overdue :count d', ['count' => 2]))
            ->assertSee('bg-wo', false)
            ->assertDontSee('>'.__('Detail').'<', false);

        $this->assertDoesNotMatchRegularExpression('/animate-(pulse|ping|bounce)/', $component->html());
    }

    public function test_every_tab_stays_reachable_and_renders_without_detalle(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->seedAttention();

        foreach (['today', 'overdue', 'camila', 'resolver', 'alta', 'pronostico', 'new_orders', 'client'] as $tab) {
            $html = Livewire::actingAs($user)->test(Index::class)
                ->call('setActiveTab', $tab)
                ->assertSet('activeTab', $tab)
                ->assertDontSee('>'.__('Detail').'<', false)
                ->html();
            $this->assertDoesNotMatchRegularExpression('/animate-(pulse|ping|bounce)/', $html, $tab);
        }
    }

    public function test_page_header_title_and_date(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('<title>'.__('Today'), false)
            ->assertSee(now()->locale(app()->getLocale())->translatedFormat(app()->getLocale() === 'es' ? 'l j \d\e F' : 'l, F j'));
    }

    public function test_designers_do_not_get_the_resolver_segment(): void
    {
        $user = User::factory()->create(['role' => 'designer']);
        Designer::create(['user_id' => $user->id, 'name' => 'Ana', 'slug' => 'ana', 'active' => true]);

        Livewire::actingAs($user)->test(Index::class)
            ->assertDontSee('tour-stats-resolver', false)
            ->call('setActiveTab', 'resolver')
            ->assertSet('activeTab', 'today');
    }
}
