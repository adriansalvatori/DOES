<?php

namespace App\Livewire\CreateWorkOrder;

use App\Application\Delivery\Actions\CreateWorkOrder;
use App\Application\Delivery\Actions\CreateWorkOrderData;
use App\Contracts\WorkOrderNumberGenerator;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\User;
use App\Services\ClientMatchingService;
use App\Support\TitleCase;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Create work order form. Every field of the legacy form is kept, plus Phase and Fulfillment method; the
 * work order and its deliverables are created by the CreateWorkOrder action and land in Intake.
 */
class CreateWorkOrderModal extends Component
{
    public bool $isOpen = false;

    public string $woNumber = '';

    public ?int $contactId = null;

    public string $contactQuery = '';

    public ?int $clientId = null;

    public string $companyQuery = '';

    public string $locationName = '';

    public string $description = '';

    /** @var list<array{name: string, size: string, material: string, quantity: int}> */
    public array $deliverables = [];

    public string $newName = '';

    public string $newSize = '';

    public string $newMaterial = '';

    public int|string $newQuantity = 1;

    public string $phaseName = '';

    public string $fulfillmentMethod = '';

    /** @var list<string> */
    public array $flags = [];

    public string $assigneeId = '';

    public string $promisedDate = '';

    public string $trelloLink = '';

    public ?string $submitError = null;

    #[On('open-create-work-order')]
    public function open(?int $clientId = null, ?string $phaseName = null): void
    {
        if (! auth()->user()?->can('createWorkOrder', Deliverable::class)) {
            return;
        }

        $this->resetForm();
        $this->promisedDate = now()->addWeekdays(2)->toDateString();

        $client = $clientId !== null ? Client::query()->find($clientId) : null;
        if ($client !== null) {
            $this->selectCompany($client->getKey());
        }

        $this->phaseName = trim((string) $phaseName);
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetForm();
    }

    public function generateWoNumber(WorkOrderNumberGenerator $generator): void
    {
        $this->woNumber = $generator->generateNextDigits([
            'client_id' => $this->clientId,
            'company_name' => mb_strtoupper(trim($this->companyQuery), 'UTF-8'),
            'task_name' => mb_strtoupper(trim($this->description), 'UTF-8'),
        ]);
    }

    public function updatedCompanyQuery(): void
    {
        $this->clientId = null;
    }

    public function updatedContactQuery(): void
    {
        $this->contactId = null;
    }

    public function selectCompany(int $clientId): void
    {
        $client = Client::query()->with('primaryContact')->find($clientId);

        if ($client === null) {
            return;
        }

        $this->clientId = $client->getKey();
        $this->companyQuery = TitleCase::from($client->name);
        $this->locationName = '';

        $primary = $client->primary_contact;
        if ($this->contactId === null && trim($this->contactQuery) === '' && $primary !== null) {
            $this->selectContact($primary->getKey());
        }
    }

    public function selectContact(int $contactId): void
    {
        $contact = Contact::query()->find($contactId);

        if ($contact !== null) {
            $this->contactId = $contact->getKey();
            $this->contactQuery = TitleCase::from($contact->name);
        }
    }

    public function setPromisedPreset(string $preset): void
    {
        $this->promisedDate = match ($preset) {
            'today' => now()->toDateString(),
            'tomorrow' => now()->addDay()->toDateString(),
            default => $this->promisedDate,
        };
    }

    public function toggleFlag(string $flag): void
    {
        if (DeliverableFlag::tryFrom($flag) === null) {
            return;
        }

        $this->flags = in_array($flag, $this->flags, true)
            ? array_values(array_diff($this->flags, [$flag]))
            : [...$this->flags, $flag];
    }

    public function selectAssignee(string $userId): void
    {
        $this->assigneeId = $this->assigneeId === $userId ? '' : $userId;
    }

    public function addDeliverableRow(): void
    {
        $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newSize' => ['nullable', 'string', 'max:255'],
            'newMaterial' => ['nullable', 'string', 'max:255'],
            'newQuantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], $this->messages());

        $this->deliverables[] = [
            'name' => trim($this->newName),
            'size' => trim($this->newSize),
            'material' => trim($this->newMaterial),
            'quantity' => max(1, (int) $this->newQuantity),
        ];

        $this->reset('newName', 'newSize', 'newMaterial');
        $this->newQuantity = 1;
    }

    public function removeDeliverableRow(int $index): void
    {
        unset($this->deliverables[$index]);
        $this->deliverables = array_values($this->deliverables);
    }

    public function save(CreateWorkOrder $create, ClientMatchingService $matching): void
    {
        $this->submitError = null;
        $this->absorbPendingRow();

        $this->validate([
            'woNumber' => ['nullable', 'string', 'max:50'],
            'companyQuery' => ['required', 'string', 'max:255'],
            'contactQuery' => ['nullable', 'string', 'max:255'],
            'locationName' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'deliverables' => ['array'],
            'deliverables.*.name' => ['required', 'string', 'max:255'],
            'deliverables.*.size' => ['nullable', 'string', 'max:255'],
            'deliverables.*.material' => ['nullable', 'string', 'max:255'],
            'deliverables.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'phaseName' => ['nullable', 'string', 'max:255'],
            'fulfillmentMethod' => ['nullable', Rule::enum(FulfillmentMethod::class)],
            'flags' => ['array'],
            'flags.*' => [Rule::enum(DeliverableFlag::class)],
            'assigneeId' => ['nullable', Rule::in($this->designers->pluck('user.id')->map(fn (int $id): string => (string) $id)->all())],
            'promisedDate' => ['nullable', 'date'],
            'trelloLink' => ['nullable', 'string', 'max:500'],
        ], $this->messages());

        $number = $this->normalizedNumber();
        if ($number !== null && WorkOrder::withTrashed()->where('number', $number)->exists()) {
            $this->submitError = __('Work order :number already exists.', ['number' => $number]);

            return;
        }

        $actor = auth()->user();
        $parties = $this->resolveParties($matching);
        $items = $this->deliverables === []
            ? [['name' => trim($this->description), 'size' => '', 'material' => '', 'quantity' => 1]]
            : $this->deliverables;

        $data = new CreateWorkOrderData(
            deliverables: array_map(fn (array $item): array => [
                'name' => $item['name'],
                'size' => $this->blankToNull($item['size'] ?? null),
                'material' => $this->blankToNull($item['material'] ?? null),
                'quantity' => $item['quantity'] === '' || $item['quantity'] === null ? null : (int) $item['quantity'],
            ], $items),
            number: $number,
            generateNumber: false,
            clientId: $parties['client']?->getKey(),
            clientLocationId: $parties['location']?->getKey(),
            contactId: $parties['contactId'],
            description: trim($this->description),
            phaseName: $this->blankToNull($this->phaseName),
            fulfillmentMethod: $this->fulfillmentMethod === '' ? null : FulfillmentMethod::from($this->fulfillmentMethod),
            flags: array_values(array_filter(DeliverableFlag::cases(), fn (DeliverableFlag $flag): bool => in_array($flag->value, $this->flags, true))),
            assigneeId: $this->assigneeId === '' ? null : (int) $this->assigneeId,
            promisedDate: $this->promisedDate === '' ? null : CarbonImmutable::parse($this->promisedDate),
            trelloLink: $this->blankToNull($this->trelloLink),
            companyName: $parties['client']?->name ?? $this->companyQuery,
        );

        try {
            $workOrder = ($create)($data, $actor);
        } catch (WorkflowException $exception) {
            $this->submitError = $exception->getMessage();

            return;
        } catch (AuthorizationException) {
            $this->submitError = __('You are not allowed to do that.');

            return;
        }

        $this->dispatch(
            'work-order-created',
            workOrderId: $workOrder->getKey(),
            number: $workOrder->number,
            url: Route::has('work-orders.show') ? route('work-orders.show', $workOrder) : null,
        );
        $this->closeModal();
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function companyResults(): Collection
    {
        $term = trim($this->companyQuery);

        if ($this->clientId !== null || $term === '') {
            return collect();
        }

        return Client::query()
            ->where('name', 'like', '%'.addcslashes(mb_strtoupper($term, 'UTF-8'), '%_\\').'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);
    }

    /**
     * Contacts to offer: the company's own contacts first, then the directory matching the search.
     *
     * @return Collection<int, Contact>
     */
    #[Computed]
    public function contactResults(): Collection
    {
        if ($this->contactId !== null) {
            return collect();
        }

        $term = trim($this->contactQuery);
        $own = $this->clientId !== null
            ? Client::query()->find($this->clientId)?->contacts()
                ->when($term !== '', fn ($query) => $query->where('contacts.name', 'like', '%'.addcslashes(mb_strtoupper($term, 'UTF-8'), '%_\\').'%'))
                ->limit(8)->get(['contacts.id', 'contacts.name']) ?? collect()
            : collect();

        if ($term === '') {
            return $own;
        }

        $others = Contact::query()->search($term)->whereNotIn('id', $own->pluck('id'))->orderBy('name')->limit(8 - $own->count() + 4)->get(['id', 'name']);

        return $own->concat($others)->values();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function clientLocations(): Collection
    {
        if ($this->clientId === null) {
            return collect();
        }

        return Client::query()->find($this->clientId)?->locations()->pluck('name') ?? collect();
    }

    /**
     * Designers with their work in progress, to hint at the most available one.
     *
     * @return Collection<int, array{user: User, load: int}>
     */
    #[Computed]
    public function designers(): Collection
    {
        $users = User::query()
            ->designTeam()
            ->orderBy('name')
            ->get(['id', 'name', 'wip_limit']);

        $loads = Deliverable::query()
            ->inWorkspace()
            ->whereIn('assignee_id', $users->pluck('id'))
            ->inStatus([DeliverableStatus::READY, DeliverableStatus::IN_DESIGN])
            ->notBlocked()
            ->selectRaw('assignee_id, count(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        return $users->map(fn (User $user): array => ['user' => $user, 'load' => (int) ($loads[$user->id] ?? 0)]);
    }

    public function render()
    {
        $designers = $this->isOpen ? $this->designers : collect();

        return view('livewire.create-work-order.create-work-order-modal', [
            'designers' => $designers,
            'mostAvailable' => $designers->sortBy('load')->first(),
            'fulfillmentMethods' => FulfillmentMethod::cases(),
            'flagOptions' => array_values(array_filter(DeliverableFlag::cases(), fn (DeliverableFlag $flag): bool => $flag !== DeliverableFlag::BALANCE_DUE)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'companyQuery.required' => __('The company is required.'),
            'description.required' => __('The job description is required.'),
            'newName.required' => __('The deliverable name is required.'),
            'deliverables.*.name.required' => __('The deliverable name is required.'),
            'newQuantity.integer' => __('The quantity must be a whole number.'),
            'newQuantity.min' => __('The quantity must be at least 1.'),
            'deliverables.*.quantity.integer' => __('The quantity must be a whole number.'),
            'deliverables.*.quantity.min' => __('The quantity must be at least 1.'),
            'promisedDate.date' => __('Enter a valid date.'),
            'fulfillmentMethod.enum' => __('Choose a valid fulfillment method.'),
            'assigneeId.in' => __('Choose one of the designers listed.'),
        ];
    }

    /**
     * What the person typed in the pending deliverable row counts as a deliverable too.
     */
    private function absorbPendingRow(): void
    {
        if (trim($this->newName) !== '') {
            $this->addDeliverableRow();
        }
    }

    /**
     * Finds or creates the company, location and contact the work order points to.
     *
     * @return array{client: ?Client, location: ?ClientLocation, contactId: ?int}
     */
    private function resolveParties(ClientMatchingService $matching): array
    {
        $company = $this->clientId !== null
            ? (string) Client::query()->find($this->clientId)?->name
            : mb_strtoupper(trim($this->companyQuery), 'UTF-8');
        $location = mb_strtoupper(trim($this->locationName), 'UTF-8');
        $typedContact = $this->contactId === null && trim($this->contactQuery) !== '' ? trim($this->contactQuery) : null;

        $needsLookup = $this->clientId === null || $location !== '' || $typedContact !== null;
        $matched = $needsLookup
            ? $matching->matchOrCreate($location !== '' ? $company.' REF '.$location : $company, $typedContact)
            : ['client' => Client::query()->find($this->clientId), 'location' => null];

        $client = $matched['client'];
        $contactId = $this->contactId;

        if ($contactId === null && $typedContact !== null) {
            $contactId = Contact::query()->where('name', mb_strtoupper($typedContact, 'UTF-8'))->value('id');
        }

        if ($client !== null && $contactId !== null && ! $client->contacts()->where('contacts.id', $contactId)->exists()) {
            $client->contacts()->attach($contactId, ['is_primary' => false]);
        }

        return ['client' => $client, 'location' => $matched['location'], 'contactId' => $contactId];
    }

    private function normalizedNumber(): ?string
    {
        $digits = trim((string) preg_replace('/^\s*WO\s*/i', '', $this->woNumber));

        return $digits === '' ? null : 'WO '.$digits;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function resetForm(): void
    {
        $this->reset([
            'woNumber', 'contactId', 'contactQuery', 'clientId', 'companyQuery', 'locationName', 'description', 'deliverables',
            'newName', 'newSize', 'newMaterial', 'newQuantity', 'phaseName', 'fulfillmentMethod', 'flags', 'assigneeId',
            'promisedDate', 'trelloLink', 'submitError',
        ]);
        $this->resetValidation();
        unset($this->companyResults, $this->contactResults, $this->clientLocations, $this->designers);
    }
}
