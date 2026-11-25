<?php

namespace App\Support;

class AppBehaviorsDocs
{
    /**
     * Devuelve las categorías y artículos de la documentación de comportamientos de la app.
     * Esta estructura modular permite actualizar fácilmente las explicaciones cuando hay cambios en el sistema.
     *
     * @return array<string, mixed>
     */
    public static function getCategories(): array
    {
        return [
            'lifecycle' => [
                'id' => 'lifecycle',
                'title' => __('Card life cycle'),
                'icon' => 'git-commit',
                'description' => __('Learn how an order moves from the moment it enters the system until it is archived.'),
                'badge' => 'Core Flow',
                'articles' => [
                    [
                        'id' => 'card-flow-steps',
                        'title' => __('Order workflow phases (Core Status)'),
                        'summary' => __('The main path each card follows on the Kanban board and in Trello.'),
                        'steps' => [
                            [
                                'name' => __('1. Incoming (BLOCKED)'),
                                'badge' => 'ENTRANTE',
                                'badge_style' => 'bg-orange-50 text-orange-700 border-orange-200',
                                'desc' => __('New cards entering the system. They usually need an initial review before being assigned to a designer\'s queue.'),
                            ],
                            [
                                'name' => __('2. Assigned to designer (Orders Received)'),
                                'badge' => 'RECIBIDAS',
                                'badge_style' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
                                'desc' => __('Lists equivalent to PENDING per designer (Euralíz, Adrián or César). The card has an owner but is still in the queue.'),
                            ],
                            [
                                'name' => __('3. Working today (To Do Today)'),
                                'badge' => 'TO DO TODAY',
                                'badge_style' => 'bg-amber-50 text-amber-800 border-amber-300',
                                'desc' => __('Cards actively selected to be worked on during today\'s workday. They are visually highlighted.'),
                            ],
                            [
                                'name' => __('4. In production'),
                                'badge' => __('In production'),
                                'badge_style' => 'bg-pink-50 text-pink-700 border-pink-200',
                                'desc' => __('The order has moved to the physical/final production phase. The card\'s workspace stays active.'),
                            ],
                            [
                                'name' => __('5. Internal review / Client'),
                                'badge' => __('Review'),
                                'badge_style' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'desc' => __('Sent to Camila (internal supervision) or sent to the client. Can receive a Changes substatus.'),
                            ],
                            [
                                'name' => __('6. Archived'),
                                'badge' => 'ARCHIVED',
                                'badge_style' => 'bg-stone-100 text-stone-600 border-stone-200',
                                'desc' => __('Order completed successfully. It leaves the active operational views and is saved to the historical archive.'),
                            ],
                        ],
                        'tip' => __('Cards in Trello and Kudos stay 100% in sync. Moving a card to another column in Trello updates its Core Status automatically.'),
                    ],
                ],
            ],
            'subtasks' => [
                'id' => 'subtasks',
                'title' => __('Subtasks and Weekly Planner'),
                'icon' => 'list-checks',
                'description' => __('How subtasks behave when scheduled for today and their impact on progress.'),
                'badge' => __('Planning'),
                'articles' => [
                    [
                        'id' => 'subtasks-today',
                        'title' => __('What happens when you set a subtask for "Today"?'),
                        'summary' => __('User action when marking or scheduling a subtask with today\'s date.'),
                        'points' => [
                            __('Weekly Planner visibility: The subtask immediately appears in the TODAY column of the Weekly Planner module.'),
                            __('Card indicator: The order will show the number of subtasks scheduled for today directly on its Kanban and Backlog cards.'),
                            __('Automatic prioritization: Raises the order\'s visual priority so the designer has it on their radar for today\'s work.'),
                            __('Progress calculation: When a subtask is marked as completed, the order\'s overall progress percentage is updated (e.g. 3/5 completed = 60%).'),
                        ],
                        'tip' => __('You can use Subtask Templates from Settings > Subtask Templates to preload standard task lists by type of work.'),
                    ],
                    [
                        'id' => 'subtask-weekly-limits',
                        'title' => __('Limits and weekly organization'),
                        'summary' => __('How subtasks are distributed and tracked across designers.'),
                        'points' => [
                            __('Assignment by designer: Each subtask is linked to the order and to the designer assigned to it.'),
                            __('Subtasks on hold: If an order is paused or in client review, its subtasks are kept but don\'t count as active pending items for today.'),
                        ],
                    ],
                ],
            ],
            'substatuses' => [
                'id' => 'substatuses',
                'title' => __('Substatuses and cross conditions'),
                'icon' => 'tags',
                'description' => __('How alerts, urgency flags and delivery times behave.'),
                'badge' => __('Statuses'),
                'articles' => [
                    [
                        'id' => 'substatus-behavior',
                        'title' => __('Difference between main status and substatus'),
                        'summary' => __('Substatuses are cross-cutting conditions that can be applied regardless of the main status.'),
                        'examples' => [
                            [
                                'substatus' => 'OVERDUE',
                                'badge' => 'OVERDUE',
                                'badge_style' => 'bg-red-50 text-red-700 border-red-200',
                                'desc' => __('Activated automatically when the order\'s due date has passed and it hasn\'t been completed or archived yet.'),
                            ],
                            [
                                'substatus' => 'ALMOST OVERDUE',
                                'badge' => __('Almost Overdue'),
                                'badge_style' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'desc' => __('Activated automatically when less than 24 hours remain until the delivery date.'),
                            ],
                            [
                                'substatus' => 'URGENTE',
                                'badge' => __('Urgent'),
                                'badge_style' => 'bg-red-600 text-white border-red-700 font-extrabold animate-pulse',
                                'desc' => __('High-priority visual indicator. Blinks red to alert the whole team.'),
                            ],
                            [
                                'substatus' => 'BLOQUEADA / NO RESPUESTA',
                                'badge' => __('Blocked'),
                                'badge_style' => 'bg-orange-50 text-orange-700 border-orange-200',
                                'desc' => __('The order can\'t move forward because client information, estimate approval or measurements are missing.'),
                            ],
                            [
                                'substatus' => 'CAMBIOS CLIENTE / CAMBIOS CAMILA',
                                'badge' => __('Client Revisions'),
                                'badge_style' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'desc' => __('Applied when the card was sent back from review with remarks that need to be fixed.'),
                            ],
                        ],
                        'tip' => __('You can manage and create custom substatuses in the Settings > Substatuses menu.'),
                    ],
                ],
            ],
            'trello_sync' => [
                'id' => 'trello_sync',
                'title' => __('Trello sync and mappings'),
                'icon' => 'refresh-cw',
                'description' => __('How two-way sync works and how to pause it.'),
                'badge' => __('Integration'),
                'articles' => [
                    [
                        'id' => 'sync-rules',
                        'title' => __('Trello <-> Kudos sync rules'),
                        'summary' => __('How Trello and the app communicate to keep information up to date.'),
                        'points' => [
                            __('List moves: Moving a card in Trello immediately updates the Core Status in Kudos according to the list mapping.'),
                            __('Data edits: Changes to the title, designer or due date are reflected automatically.'),
                            __('Checklists: Checklist items in Trello are synced as subtasks within the order.'),
                            __('Global sync pause: The switch in the bottom sidebar lets you freeze sync during cleanups or bulk changes.'),
                        ],
                        'tip' => __('If you rename your lists in Trello, make sure to update the mapping in Settings > Trello list mapping.'),
                    ],
                ],
            ],
            'resolver' => [
                'id' => 'resolver',
                'title' => __('Action Required section (Resolve)'),
                'icon' => 'alert-triangle',
                'description' => __('Handling cards that need manual attention or data fixes.'),
                'badge' => __('Alerts'),
                'articles' => [
                    [
                        'id' => 'action-required-trigger',
                        'title' => __('When does an order enter "Action Required"?'),
                        'summary' => __('Exceptional situations where the system asks for human intervention.'),
                        'points' => [
                            __('Unknown Trello list: A card is moved to a list that isn\'t mapped in Settings.'),
                            __('No designer assigned in an active phase: An order enters daily work without a defined owner.'),
                            __('Status mismatch: Sync conflicts between Trello and the local database.'),
                        ],
                        'tip' => __('The number on the orange indicator in the side menu always shows how many orders need attention.'),
                    ],
                ],
            ],
            'trash' => [
                'id' => 'trash',
                'title' => __('Trash and Backups'),
                'icon' => 'database',
                'description' => __('Data security, recovery of deleted orders and backups.'),
                'badge' => __('System'),
                'articles' => [
                    [
                        'id' => 'trash-backup-behavior',
                        'title' => __('Order recovery and backups'),
                        'summary' => __('Protection against accidental data loss.'),
                        'points' => [
                            __('Recycle bin: Deleting an order doesn\'t erase it permanently. It moves to /trash, where it can be restored.'),
                            __('Permanent deletion: Only an explicit action inside the Trash removes the data from the database for good.'),
                            __('Automatic backups: In Settings > Backups you can generate full snapshots of the application database.'),
                        ],
                    ],
                ],
            ],
        ];
    }
}
