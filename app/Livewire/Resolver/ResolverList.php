<?php

namespace App\Livewire\Resolver;

use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use Livewire\Component;

class ResolverList extends Component
{
    public function render()
    {
        $blockedOrders = Order::inWorkspace()->with(['designer', 'relatedTasks'])
            ->where(function ($q) {
                $q->where('substatus', Substatus::BLOQUEADA)
                  ->orWhere('substatus', Substatus::FALTA_APROBACION_ESTIMADO)
                  ->orWhere('customer_service_required', true)
                  ->orWhere(function ($m) {
                      $m->where('approved', true)->where('measures_confirmed', false);
                  });
            })->get();

        $resolverTasks = RelatedTask::with(['order', 'assignee'])
            ->where('status', 'todo')
            ->whereIn('type', [
                \App\Enums\RelatedTaskType::RESOLVER,
                \App\Enums\RelatedTaskType::SOLICITAR_INFO,
                \App\Enums\RelatedTaskType::CORREO_ATRASO,
            ])->get();

        return view('livewire.resolver.resolver-list', [
            'blockedOrders' => $blockedOrders,
            'resolverTasks' => $resolverTasks,
        ])->layout('components.layouts.app', ['title' => 'Vista Resolver - Kudos Design Ops']);
    }
}
