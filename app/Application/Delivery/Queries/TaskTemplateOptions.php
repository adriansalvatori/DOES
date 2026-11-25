<?php

namespace App\Application\Delivery\Queries;

use App\Domain\Delivery\Models\TaskTemplate;
use Illuminate\Support\Collection;

/**
 * Active task templates for the "add a task" menu of the deliverable panel, named in the reader's locale.
 * Pass a template to AddTask as `title: $template->nameFor()`, `kind: $template->taskKind()`, `advancesTo: $template->advances_to`.
 */
class TaskTemplateOptions
{
    /**
     * @return Collection<int, TaskTemplate>
     */
    public function run(): Collection
    {
        return TaskTemplate::query()->active()->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * @return array<int, array{id: int, name: string, kind: string, advances_to: ?string, required: bool}>
     */
    public function asOptions(?string $locale = null): array
    {
        return $this->run()->map(fn (TaskTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->nameFor($locale),
            'kind' => $template->kind,
            'advances_to' => $template->advances_to?->value,
            'required' => (bool) $template->required,
        ])->all();
    }
}
