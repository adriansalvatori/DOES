<?php

namespace App\Livewire\Portal;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public, read-only client portal (/c/{token}): work order, then phase, then deliverables with a client-friendly status,
 * the promised date and the receipt status. Renders in `?lang=` or the visitor's default locale.
 */
class ClientPortal extends Component
{
    public const RECENT_DAYS = 30;

    public const MAX_WORK_ORDERS = 30;

    public string $token = '';

    #[Url(as: 'lang')]
    public string $lang = '';

    public bool $clientNotFound = false;

    public function mount(string $token): void
    {
        $this->token = trim($token);
        $this->clientNotFound = ! Client::where('portal_token', $this->token)->exists();
        $this->applyLocale();
    }

    public function hydrate(): void
    {
        $this->applyLocale();
    }

    /**
     * Client-friendly status of a deliverable. Blocks stay internal: the client sees the stage the work is in.
     */
    public static function customerStatus(Deliverable $deliverable): string
    {
        if ($deliverable->openHold !== null) {
            return __('On hold');
        }

        return match ($deliverable->status) {
            DeliverableStatus::INTAKE, DeliverableStatus::READY => __('Received'),
            DeliverableStatus::IN_DESIGN, DeliverableStatus::INTERNAL_REVIEW => __('In design'),
            DeliverableStatus::CLIENT_REVIEW => __('Waiting for your review'),
            DeliverableStatus::PRODUCTION_FILES,
            DeliverableStatus::READY_FOR_PRODUCTION,
            DeliverableStatus::PRODUCTION_QUEUE => __('Preparing for production'),
            DeliverableStatus::IN_PRODUCTION, DeliverableStatus::PRODUCED => __('In production'),
            DeliverableStatus::FULFILLMENT => __('Ready for delivery or pickup'),
            DeliverableStatus::DONE => $deliverable->close_reason === null || $deliverable->close_reason === CloseReason::COMPLETED
                ? __('Completed')
                : __('Closed'),
            default => __('In progress'),
        };
    }

    /**
     * Whether the status needs an answer from the client.
     */
    public static function needsClientAction(Deliverable $deliverable): bool
    {
        return $deliverable->status === DeliverableStatus::CLIENT_REVIEW && $deliverable->openHold === null;
    }

    public function render(): View
    {
        $layout = 'components.layouts.portal';

        $client = $this->clientNotFound ? null : Client::where('portal_token', $this->token)->with(['locations'])->first();

        if (! $client) {
            return view('livewire.portal.client-portal-not-found')
                ->layout($layout, ['title' => __('Link not found · Kudos')]);
        }

        $recentSince = now()->subDays(self::RECENT_DAYS);

        $workOrders = WorkOrder::query()
            ->where('client_id', $client->id)
            ->whereHas('deliverables', fn ($deliverables) => $deliverables
                ->inWorkspace()
                ->where(fn ($open) => $open
                    ->where('status', '!=', DeliverableStatus::DONE->value)
                    ->orWhere('closed_at', '>=', $recentSince)))
            ->with([
                'clientLocation',
                'phases' => fn ($phases) => $phases->orderBy('position'),
                'deliverables' => fn ($deliverables) => $deliverables
                    ->inWorkspace()
                    ->with(['receipts', 'openHold'])
                    ->orderByRaw('due_target IS NULL')
                    ->orderBy('due_target')
                    ->orderBy('id'),
            ])
            ->latest('id')
            ->limit(self::MAX_WORK_ORDERS)
            ->get();

        return view('livewire.portal.client-portal', [
            'client' => $client,
            'workOrders' => $workOrders,
            'csPhone' => Setting::get('cs_whatsapp_phone', '+16783580594'),
        ])->layout($layout, ['title' => __('My Orders · :name', ['name' => $client->name])]);
    }

    private function applyLocale(): void
    {
        if (in_array($this->lang, ['en', 'es'], true)) {
            App::setLocale($this->lang);
        }
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
