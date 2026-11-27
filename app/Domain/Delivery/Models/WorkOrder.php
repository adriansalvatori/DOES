<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use Database\Factories\Domain\Delivery\Models\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'client_id',
        'company_name',
        'client_location_id',
        'contact_id',
        'description',
        'estimate_invoice_number',
        'promised_date',
        'fulfillment_method',
        'installation_types',
        'notes',
        'drive_folder_id',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'promised_date' => 'date',
            'fulfillment_method' => FulfillmentMethod::class,
            'installation_types' => 'array',
        ];
    }

    /**
     * Work orders with work outside the backlog: a deliverable in the workspace (open or closed), or no deliverable yet.
     * A work order whose later phases still wait in the backlog matches both this and inBacklog().
     */
    public function scopeInWorkspace(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->inWorkspace())
            ->orWhereDoesntHave('deliverables'));
    }

    /**
     * Work orders with at least one deliverable still waiting in the backlog.
     */
    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->whereHas('deliverables', fn (Builder $deliverables) => $deliverables->inBacklog());
    }

    /**
     * The company shown for this work order, as stored (uppercase): the linked company, else the typed one.
     */
    public function displayName(): string
    {
        return $this->client?->name ?? $this->company_name ?? '';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientLocation(): BelongsTo
    {
        return $this->belongsTo(ClientLocation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('position');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
