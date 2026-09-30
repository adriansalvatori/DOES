<?php

namespace App\Livewire\Portal;

use App\Enums\CoreStatus;
use App\Models\Client;
use App\Models\Setting;
use App\Services\ClientTimelineService;
use Livewire\Component;

class ClientPortal extends Component
{
    public string $token = '';

    public ?int $selectedOrderId = null;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $clientNotFound = false;

    public function mount(string $token): void
    {
        $this->token = trim($token);
        $client = Client::where('portal_token', $this->token)->first();

        if (! $client) {
            $this->clientNotFound = true;
        }
    }

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
    }

    public function closeOrder(): void
    {
        $this->selectedOrderId = null;
    }

    public function setFilter(string $filter): void
    {
        $this->statusFilter = $filter;
    }

    public function render()
    {
        if ($this->clientNotFound) {
            return view('livewire.portal.client-portal-not-found')
                ->layout('components.layouts.portal', ['title' => __('Enlace no encontrado · Kudos')]);
        }

        $client = Client::where('portal_token', $this->token)
            ->with(['locations', 'contacts'])
            ->first();

        if (! $client) {
            return view('livewire.portal.client-portal-not-found')
                ->layout('components.layouts.portal', ['title' => __('Enlace no encontrado · Kudos')]);
        }

        $timelineService = app(ClientTimelineService::class);
        $csPhone = Setting::get('cs_whatsapp_phone', '+16783580594');

        $query = $client->activeOrders()
            ->with(['clientLocation', 'designer.user', 'designers.user', 'events'])
            ->orderByDesc('updated_at');

        if (! empty(trim($this->search))) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('wo_number', 'like', "%{$s}%")
                    ->orWhere('task_name', 'like', "%{$s}%")
                    ->orWhere('trello_title', 'like', "%{$s}%")
                    ->orWhere('location_name', 'like', "%{$s}%");
            });
        }

        if ($this->statusFilter === 'review') {
            $query->where('core_status', CoreStatus::ENVIADO_AL_CLIENTE);
        } elseif ($this->statusFilter === 'production') {
            $query->where('core_status', CoreStatus::EN_PRODUCCION);
        } elseif ($this->statusFilter === 'hold') {
            $query->where('core_status', CoreStatus::ON_HOLD);
        } elseif ($this->statusFilter === 'design') {
            $query->whereNotIn('core_status', [CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION, CoreStatus::ON_HOLD]);
        }

        $orders = $query->get();

        $selectedOrder = null;
        $selectedTimeline = collect();
        $selectedStatus = null;

        if ($this->selectedOrderId) {
            $selectedOrder = $client->activeOrders()
                ->with(['clientLocation', 'designer.user', 'designers.user', 'events'])
                ->find($this->selectedOrderId);

            if ($selectedOrder) {
                $selectedTimeline = $timelineService->getClientTimeline($selectedOrder);
                $selectedStatus = $timelineService->getCustomerStatus($selectedOrder);
            }
        }

        $allCount = $client->activeOrders()->count();
        $reviewCount = $client->activeOrders()->where('core_status', CoreStatus::ENVIADO_AL_CLIENTE)->count();
        $productionCount = $client->activeOrders()->where('core_status', CoreStatus::EN_PRODUCCION)->count();
        $holdCount = $client->activeOrders()->where('core_status', CoreStatus::ON_HOLD)->count();

        return view('livewire.portal.client-portal', [
            'client' => $client,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'selectedTimeline' => $selectedTimeline,
            'selectedStatus' => $selectedStatus,
            'timelineService' => $timelineService,
            'csPhone' => $csPhone,
            'allCount' => $allCount,
            'reviewCount' => $reviewCount,
            'productionCount' => $productionCount,
            'holdCount' => $holdCount,
        ])->layout('components.layouts.portal', ['title' => 'Mis Órdenes · '.$client->name]);
    }

    public static function formatDisplayPhone(?string $phone): string
    {
        if (empty(trim($phone ?? ''))) {
            return '';
        }

        $trimmed = trim($phone);
        $extension = '';
        if (preg_match('/^(.*?)\s*(ext\.?.*)$/i', $trimmed, $matches)) {
            $trimmed = $matches[1];
            $extension = ' '.$matches[2];
        }

        $digits = preg_replace('/\D/', '', $trimmed);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf('+1 (%s) %s-%s%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6), $extension);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '57')) {
            return sprintf('+57 (%s) %s-%s%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8), $extension);
        }

        return $phone;
    }
}
