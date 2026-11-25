<?php

namespace App\Livewire\Resolver;

use App\Application\Delivery\Actions\LogBlockFollowUp;
use App\Application\Delivery\Actions\ReassignBlockOwner;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\Resume;
use App\Application\Delivery\Data\ResolverGroup;
use App\Application\Delivery\Queries\ResolverQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\Roles;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where customer service clears blocks and resumes holds. Admin, coordinator and customer service only.
 */
class ResolverPage extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'blocked';

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && (Roles::isManager($user) || Roles::isCustomerService($user)), 403);
    }

    /**
     * @return list<ResolverGroup>
     */
    #[Computed]
    public function groups(): array
    {
        $user = Auth::user();

        return (new ResolverQuery)->run(Roles::isCustomerService($user) && ! Roles::isManager($user) ? $user->getKey() : null);
    }

    /**
     * @return array{blocked: int, hold: int}
     */
    #[Computed]
    public function counts(): array
    {
        $items = collect($this->groups())->flatMap(fn (ResolverGroup $group): array => $group->items);

        return [
            'blocked' => $items->where('type', 'block')->count(),
            'hold' => $items->where('type', 'hold')->count(),
        ];
    }

    /**
     * The groups of the open tab, keeping only the items of that tab.
     *
     * @return list<ResolverGroup>
     */
    #[Computed]
    public function visibleGroups(): array
    {
        $type = $this->tab === 'hold' ? 'hold' : 'block';
        $visible = [];

        foreach ($this->groups() as $group) {
            $items = array_values(array_filter($group->items, fn ($item): bool => $item->type === $type));
            if ($items !== []) {
                $visible[] = new ResolverGroup($group->workOrder, $items, $items[0]->ageInDays);
            }
        }

        return $visible;
    }

    /**
     * People a block can be handed to.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function owners(): array
    {
        return User::query()
            ->where('active', true)
            ->withAnyRole(UserRole::CUSTOMER_SERVICE, UserRole::COORDINATOR, UserRole::ADMIN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'hold' ? 'hold' : 'blocked';
    }

    public function resolveBlock(int $blockId, string $note = ''): void
    {
        $block = Block::query()->with('deliverable')->findOrFail($blockId);

        $this->attempt(function () use ($block, $note): void {
            app(ResolveBlock::class)($block, filled($note) ? trim($note) : null, Auth::user());
            $this->dispatch('toast', message: __('Block resolved.'));
        });
    }

    public function logFollowUp(int $blockId, string $note = ''): void
    {
        $block = Block::query()->with('deliverable')->findOrFail($blockId);

        $this->attempt(function () use ($block, $note): void {
            app(LogBlockFollowUp::class)($block, $note, Auth::user());
            $this->dispatch('toast', message: __('Follow-up logged.'));
        });
    }

    public function reassignOwner(int $blockId, ?int $ownerId): void
    {
        $block = Block::query()->with('deliverable')->findOrFail($blockId);
        $owner = $ownerId !== null ? User::query()->where('active', true)->find($ownerId) : null;

        $this->attempt(function () use ($block, $owner): void {
            app(ReassignBlockOwner::class)($block, $owner, Auth::user());
            $this->dispatch('toast', message: __('Owner changed.'));
        });
    }

    public function resume(int $deliverableId): void
    {
        $deliverable = Deliverable::query()->findOrFail($deliverableId);

        $this->attempt(function () use ($deliverable): void {
            app(Resume::class)($deliverable, Auth::user());
            $this->dispatch('toast', message: __('Deliverable resumed.'));
        });
    }

    /**
     * Runs a use case and shows a refusal as a toast instead of an error page.
     */
    private function attempt(callable $useCase): void
    {
        try {
            $useCase();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
        }

        unset($this->groups, $this->counts, $this->visibleGroups);
    }

    public function render()
    {
        return view('livewire.resolver-page.resolver-page')
            ->layout('components.layouts.app', ['title' => __('Resolver').' - '.config('app.name')]);
    }
}
