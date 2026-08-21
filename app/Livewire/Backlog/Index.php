<?php

namespace App\Livewire\Backlog;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $designerFilter = 'all';
    public $sortBy = 'trello_created_at_desc';
    public $perPage = 25;
    public $selectedOrders = [];
    public $selectAll = false;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingDesignerFilter() { $this->resetPage(); }
    public function updatingSortBy() { $this->resetPage(); }
    public function updatingPerPage() { $this->resetPage(); }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedOrders = $this->getFilteredQuery()->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }

    public function addToWorkspace($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['in_workspace' => true]);

        session()->flash('message', "Orden {$order->company_name} añadida al Workspace activo.");
    }

    public function addSelectedToWorkspace()
    {
        if (empty($this->selectedOrders)) {
            session()->flash('warning', "Selecciona al menos una orden para añadir al Workspace.");
            return;
        }

        $count = Order::whereIn('id', $this->selectedOrders)->update(['in_workspace' => true]);
        $this->selectedOrders = [];
        $this->selectAll = false;

        session()->flash('message', "Se añadieron {$count} órdenes al Workspace activo correctamente.");
    }

    public function addAllFilteredToWorkspace()
    {
        $count = $this->getFilteredQuery()->update(['in_workspace' => true]);
        $this->selectedOrders = [];
        $this->selectAll = false;

        session()->flash('message', "Se añadieron las {$count} órdenes filtradas al Workspace activo.");
    }

    public function removeFromWorkspace($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['in_workspace' => false]);

        session()->flash('message', "Orden {$order->company_name} movida de regreso al Backlog.");
    }

    protected function getFilteredQuery()
    {
        $query = Order::inBacklog()->with(['designer']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                  ->orWhere('task_name', 'like', "%{$this->search}%")
                  ->orWhere('wo_number', 'like', "%{$this->search}%")
                  ->orWhere('responsible_person', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('core_status', $this->statusFilter);
        }

        if ($this->designerFilter !== 'all') {
            $query->where('designer_id', $this->designerFilter);
        }

        match ($this->sortBy) {
            'trello_created_at_asc' => $query->orderBy('trello_created_at', 'asc'),
            'due_date_asc' => $query->orderByRaw('current_due_date IS NULL, current_due_date ASC'),
            'company_asc' => $query->orderBy('company_name', 'asc'),
            default => $query->orderBy('trello_created_at', 'desc'),
        };

        return $query;
    }

    public function render()
    {
        $orders = $this->getFilteredQuery()->paginate($this->perPage);
        $backlogTotalCount = Order::inBacklog()->count();
        $activeWorkspaceCount = Order::inWorkspace()->count();

        return view('livewire.backlog.index', [
            'orders' => $orders,
            'backlogTotalCount' => $backlogTotalCount,
            'activeWorkspaceCount' => $activeWorkspaceCount,
            'designers' => Designer::where('active', true)->get(),
            'coreStatuses' => CoreStatus::cases(),
        ])->layout('components.layouts.app', ['title' => 'Backlog de Órdenes - Kudos Design Ops']);
    }
}
