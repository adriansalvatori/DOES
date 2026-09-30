<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Services\DemoEnvironmentService;
use App\Services\TrelloSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DemoDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_database_file_exists_and_contains_seeded_data(): void
    {
        $this->assertFileExists(database_path('demo.database.sqlite'));

        // Check 4 demo users exist in demo connection
        $demoUsers = DB::connection('demo')->table('users')->get();
        $this->assertCount(4, $demoUsers);

        $emails = $demoUsers->pluck('email')->all();
        $this->assertContains('admin@kudos.com', $emails);
        $this->assertContains('camila@kudos.com', $emails);
        $this->assertContains('adrian@kudos.com', $emails);
        $this->assertContains('ventas@kudos.com', $emails);

        // Check roles
        $roles = $demoUsers->pluck('role')->all();
        $this->assertContains(UserRole::ADMIN->value, $roles);
        $this->assertContains(UserRole::COORDINATOR->value, $roles);
        $this->assertContains(UserRole::DESIGNER->value, $roles);
        $this->assertContains(UserRole::SALES->value, $roles);

        // Check orders in demo database are disconnected from Trello
        $demoOrders = DB::connection('demo')->table('orders')->get();
        $this->assertGreaterThanOrEqual(2, $demoOrders->count());

        foreach ($demoOrders as $order) {
            $this->assertNull($order->trello_card_id, "Order {$order->wo_number} should not have a trello_card_id");
        }
    }

    public function test_trello_is_disconnected_and_paused_in_demo_mode(): void
    {
        $demoService = app(DemoEnvironmentService::class);
        $trelloService = app(TrelloSyncService::class);

        session(['is_demo' => true]);

        $this->assertTrue($demoService->isDemo());
        $this->assertTrue($trelloService->isDemoMode());
        $this->assertTrue($trelloService->isPaused());

        // API calls return error and don't contact Trello
        $lists = $trelloService->getBoardLists('dummy_board', 'dummy_key');
        $this->assertFalse($lists['success']);
        $this->assertStringContainsString('desconectado', $lists['error']);

        $cards = $trelloService->getBoardCards('dummy_board', 'dummy_key');
        $this->assertFalse($cards['success']);
        $this->assertStringContainsString('desconectado', $cards['error']);
    }

    public function test_clicking_demo_buttons_activates_demo_mode(): void
    {
        Livewire::test(Login::class)
            ->call('fillDemoCredentials', 'admin@kudos.com')
            ->assertSet('isDemo', true)
            ->assertSet('email', 'admin@kudos.com')
            ->assertSet('password', 'password')
            ->assertSessionHas('is_demo', true);
    }

    public function test_regular_login_does_not_activate_demo_mode(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'regular.user@example.com')
            ->set('password', 'secret')
            ->assertSet('isDemo', false);

        $this->assertFalse(session()->get('is_demo', false));
    }

    public function test_login_as_demo_authenticates_and_redirects_to_dashboard(): void
    {
        $demoAccounts = [
            'admin@kudos.com' => UserRole::ADMIN,
            'camila@kudos.com' => UserRole::COORDINATOR,
            'adrian@kudos.com' => UserRole::DESIGNER,
            'ventas@kudos.com' => UserRole::SALES,
        ];

        foreach ($demoAccounts as $email => $role) {
            auth()->logout();

            Livewire::test(Login::class)
                ->call('loginAsDemo', $email)
                ->assertSessionHas('is_demo', true)
                ->assertRedirect(route('dashboard'));

            $this->assertTrue(auth()->check());
            $this->assertEquals($email, auth()->user()->email);
            $this->assertEquals($role, auth()->user()->role);
        }
    }

    public function test_direct_demo_login_route_authenticates_each_role(): void
    {
        $roles = [
            'admin' => ['email' => 'admin@kudos.com', 'role' => UserRole::ADMIN],
            'manager' => ['email' => 'camila@kudos.com', 'role' => UserRole::COORDINATOR],
            'designer' => ['email' => 'adrian@kudos.com', 'role' => UserRole::DESIGNER],
            'comercial' => ['email' => 'ventas@kudos.com', 'role' => UserRole::SALES],
        ];

        foreach ($roles as $roleKey => $expected) {
            auth()->logout();

            $response = $this->get(route('demo.direct-login', ['role' => $roleKey]));
            $response->assertRedirect(route('dashboard'));
            $response->assertSessionHas('is_demo', true);
            $response->assertCookie('kudos_demo_mode', '1');

            $this->assertTrue(auth()->check());
            $this->assertEquals($expected['email'], auth()->user()->email);
            $this->assertEquals($expected['role'], auth()->user()->role);
        }
    }
}
