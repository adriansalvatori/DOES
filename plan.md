# Kudos Design Ops --- Trello Workflow Manager

## Product & Technical Build Plan

## 1. Product Goal

Build a web application connected to Trello that acts as the **workflow
and automation layer for the Kudos design team**.

Trello remains the operational source for the team's orders/cards. The
web app mirrors and interprets Trello activity, calculates deadlines,
creates related tasks, manages workflow conditions, exposes dashboards,
and keeps a complete operational history.

The team has three designers:

-   Euralíz
-   Adrián
-   César

The app must prioritize operational clarity over visual complexity. Its
main question should be:

> **What needs attention today, why, and who is responsible?**

------------------------------------------------------------------------

# 2. Core Concepts

The system must keep these concepts separate.

## 2.1 Order

An **Order** is the main work item.

**One Trello card = one Order.**

An Order has:

-   Company Name
-   Task Name
-   Trello Card ID
-   Designer
-   Core Status / Trello List
-   Substatus / Condition
-   Blocking Reason
-   Start Date
-   Last Updated
-   Last Meaningful Update
-   Current Due Date
-   Original Due Date
-   Client Last Response
-   Client Revision Count
-   Internal Revision Count
-   Overdue information
-   Approved flag
-   Measures Confirmed
-   Estimate Approved

------------------------------------------------------------------------

## 2.2 Core Status

Core Status represents **where the Order is in the workflow**.

Core statuses:

1.  `ENTRANTE`
2.  `EURALIZ ORDERS RECEIVED`
3.  `ADRIAN ORDERS RECEIVED`
4.  `CESAR ORDERS RECEIVED`
5.  `TO DO TODAY`
6.  `ENVIADO A CAMILA`
7.  `ENVIADO AL CLIENTE`
8.  `ON HOLD`
9.  `EN PRODUCCIÓN`

Important:

`EURALIZ ORDERS RECEIVED`, `ADRIAN ORDERS RECEIVED`, and
`CESAR ORDERS RECEIVED` are operationally equivalent to **PENDIENTE /
READY TO SCHEDULE**, but they must remain separate lists because they
correspond to separate Trello columns.

Do NOT create a generic `PENDIENTE` Trello/app list.

------------------------------------------------------------------------

## 2.3 Substatus / Condition

Substatuses are **cross-workflow conditions**.

They are NOT tied to one particular Core Status.

An Order may be:

-   `TO DO TODAY + OVERDUE`
-   `EURALIZ ORDERS RECEIVED + ALMOST OVERDUE`
-   `ENVIADO AL CLIENTE + WAITING FOR CLIENT`
-   `ENTRANTE + BLOQUEADA`
-   `TO DO TODAY + CAMBIOS CAMILA`
-   `TO DO TODAY + CAMBIOS CLIENTE`
-   `ORDERS RECEIVED + PONER EN ALTA`

Known conditions/substatuses:

-   `BLOQUEADA`
-   `OVERDUE`
-   `ALMOST OVERDUE`
-   `CAMBIOS CAMILA`
-   `CAMBIOS CLIENTE`
-   `PONER EN ALTA`
-   `FALTA APROBACIÓN DE ESTIMADO`
-   `NO RESPUESTA`
-   `PAUSADO`
-   `AJUSTES DE PRODUCCIÓN`
-   `WAITING FOR CLIENT`
-   `CUSTOMER SERVICE REQUIRED`

The system should support additional conditions without requiring a
database redesign.

------------------------------------------------------------------------

## 2.4 Blocking Reason

Blocking Reason is intentionally simple.

Allowed values:

-   `FALTAN MEDIDAS`
-   `FALTA LOGO`
-   `OTROS`

If `OTROS` is selected, require:

-   `Other Blocking Reason` text field

Do not create a large taxonomy of blocking reasons.

------------------------------------------------------------------------

## 2.5 Fields as Automation Inputs

Fields are not passive information only.

A field change may trigger an automation.

Example:

``` text
Measures Confirmed = NO
        ↓
Core Status = ENTRANTE
Substatus = BLOQUEADA
Blocking Reason = FALTAN MEDIDAS
Due Date = +2 days
Related Task = RESOLVER
```

The automation engine must support:

`TRIGGER → CONDITION → ACTION`

------------------------------------------------------------------------

# 3. Related Tasks

Do NOT call these Child Tasks.

Use the term:

> **Related Task**

A Related Task is an action associated with an Order.

Examples:

-   Enviar correo de bienvenida
-   Solicitar información
-   Follow Up Cliente
-   Follow Up Camila
-   Enviar correo de atraso
-   Resolver
-   Poner en ALTA
-   Follow Up ALTA
-   Ajustes de producción

Related Tasks:

-   appear independently in the dashboard
-   have their own status
-   have their own due date
-   may have an assignee
-   can be marked Done
-   can appear in `TO DO TODAY`
-   remain linked to their parent Order
-   do NOT move the parent Order unless a specific automation says so

Example:

``` text
ORDER
ENVIADO AL CLIENTE

Related Tasks
├── Follow Up #1       DONE
├── Follow Up #2       DONE
└── Follow Up #3       TODO
```

The Order remains `ENVIADO AL CLIENTE`.

------------------------------------------------------------------------

# 4. Core Workflow Rules

## 4.1 ENTRANTE

Meaning:

> Order is not ready to start because information is missing or the
> order is otherwise blocked.

Typical condition:

`BLOQUEADA`

Possible Blocking Reasons:

-   Faltan medidas
-   Falta logo
-   Otros

### On Order Creation

Immediately create:

> `ENVIAR CORREO DE BIENVENIDA`

This is mandatory for customer experience.

If information is missing, create:

> `SOLICITAR INFORMACIÓN`

### After 3 days

If the Order has NOT been resolved:

### If NOT blocked:

Move Order to:

`TO DO TODAY`

### If blocked:

Keep the Order in:

`ENTRANTE + BLOQUEADA`

and create a Related Task:

> `FOLLOW UP CLIENTE`

That Related Task should appear in `TO DO TODAY`.

### Following day

If the blocked follow-up task was not resolved, the Order
remains/returns as:

`ENTRANTE + BLOQUEADA`

The Related Task remains trackable.

------------------------------------------------------------------------

# 5. ORDERS RECEIVED / PENDING

The three `ORDERS RECEIVED` lists represent:

> Order is ready to be scheduled during the week.

They are:

-   Euraliz Orders Received
-   Adrian Orders Received
-   Cesar Orders Received

They correspond to the designer responsible for the Order.

### Base Design SLA

Once an Order enters its designer's Orders Received list:

> **3 days maximum to deliver**

The deadline continues counting while the Order is in this stage.

### Approaching deadline

At the configured threshold, the Order can receive:

`ALMOST OVERDUE`

If required by the current rules, create:

> `ENVIAR CORREO DE ATRASO`

The exact distinction between `ALMOST OVERDUE` and `OVERDUE` should
remain configurable in the automation settings.

`ALMOST OVERDUE` is cleared once the Order leaves the relevant
working/scheduling cycle according to the final automation rule.

------------------------------------------------------------------------

# 6. TO DO TODAY

Meaning:

> The Order is scheduled to receive actual work today.

Orders enter `TO DO TODAY` either:

1.  manually, because the designer/team scheduled it for today
2.  automatically because an overdue/blocked/follow-up rule requires
    action

### Daily completion rule

Every Order placed in `TO DO TODAY` must be explicitly marked:

> `DONE`

If it is not marked Done by the end of the working day:

-   it remains in `TO DO TODAY`
-   it rolls into the next day
-   its original deadline continues counting
-   it may become `OVERDUE`

This is why every Order must have an explicit Done/check mechanism.

The system must NOT silently consider a task completed because it spent
a day in `TO DO TODAY`.

------------------------------------------------------------------------

# 7. ENVIADO A CAMILA

Meaning:

> Design work has been sent to Camila for review.

Important:

> **This does NOT stop the design deadline.**

The Order continues to consume its SLA.

### Next-day follow-up

At the beginning of the following day, automatically create a Related
Task:

> `FOLLOW UP CAMILA`

The Related Task appears in:

`TO DO TODAY`

The main Order remains:

`ENVIADO A CAMILA`

The team can use these Orders/Related Tasks as a dedicated Camila
follow-up queue.

### If Camila sends changes

When the Order moves:

`ENVIADO A CAMILA → TO DO TODAY`

automatically assign:

`Substatus = CAMBIOS CAMILA`

The change cycle must then be handled according to the applicable design
deadline.

------------------------------------------------------------------------

# 8. ENVIADO AL CLIENTE

Meaning:

> Design has been delivered to the client and the ball is now on the
> client's side.

When an Order enters:

`ENVIADO AL CLIENTE`

the active design deadline stops.

Start tracking:

> `CLIENT WAITING TIME`

### Client follow-up cycle

If the client does not respond:

After 3 days:

> Create Related Task: `FOLLOW UP CLIENTE #1`

After another 3 days:

> `FOLLOW UP CLIENTE #2`

After another 3 days:

> `FOLLOW UP CLIENTE #3`

The main Order remains:

`ENVIADO AL CLIENTE`

while the Related Tasks appear independently in the dashboard.

### After 9 days

If there is still no client response:

Move Order automatically to:

`ON HOLD`

Set:

-   `Substatus = NO RESPUESTA`
-   `CUSTOMER SERVICE REQUIRED = TRUE`

------------------------------------------------------------------------

# 9. Client Changes

If an Order moves:

`ENVIADO AL CLIENTE → TO DO TODAY`

or

`ENVIADO AL CLIENTE → designer Orders Received`

interpret the event as:

> `Substatus = CAMBIOS CLIENTE`

The client has responded and changes are now required.

### Changes SLA

Client changes must be resolved within:

> **2 days**

When the client response is detected:

-   stop client waiting timer
-   increment Client Revision Count
-   calculate a new design deadline
-   start the 2-day change SLA
-   record the event in the timeline

If the changes are not resolved within 2 days:

-   mark Order `OVERDUE`
-   create Related Task: `ENVIAR CORREO DE ATRASO`

------------------------------------------------------------------------

# 10. OVERDUE

`OVERDUE` is a cross-workflow condition, not a Core Status.

An Order can therefore be:

``` text
TO DO TODAY + OVERDUE
```

or

``` text
ORDERS RECEIVED + OVERDUE
```

etc.

### Important business rule

An overdue condition cannot be removed simply by manually changing the
Due Date.

To resolve an overdue caused by a missed client-facing commitment:

1.  Related Task `ENVIAR CORREO DE ATRASO` must be completed
2.  The system must request the new date communicated to the client
3.  User enters the new promised date
4.  System updates Current Due Date
5.  Old Due Date remains in the historical record
6.  Timeline records the delay communication and new promised date
7.  Overdue condition is resolved

Example:

``` text
Old Due Date: Aug 23
↓
Delay email sent
↓
Client was told Aug 26
↓
New Due Date: Aug 26
↓
OVERDUE resolved
```

The system must preserve the overdue history.

------------------------------------------------------------------------

# 11. ON HOLD

There are two distinct paths.

## Automatic

If an Order remains in `ENVIADO AL CLIENTE` for more than 9 days without
response:

``` text
Core Status = ON HOLD
Substatus = NO RESPUESTA
Label = CUSTOMER SERVICE REQUIRED
```

## Manual

If a user manually moves an Order to `ON HOLD`:

``` text
Substatus = PAUSADO
```

Require:

> `Pause Reason`

The pause reason is a free-text field.

Manual pause must NOT be interpreted as customer non-response.

------------------------------------------------------------------------

# 12. EN PRODUCCIÓN

This is the final stage for Design.

Design does not progress beyond:

`EN PRODUCCIÓN`

### Automatic transition

If an Order:

-   is in `TO DO TODAY`
-   has `PONER EN ALTA`
-   is marked Done

then:

> At the beginning of the next day, automatically move it to
> `EN PRODUCCIÓN`.

This should not happen immediately at the moment the checkbox is
clicked.

### Production adjustments

Production may return an Order for corrections.

Use:

`AJUSTES DE PRODUCCIÓN`

as the substatus/condition.

Create Related Tasks for the required production adjustments.

------------------------------------------------------------------------

# 13. APPROVED BUTTON

Every Order must have:

> `APPROVED`

button.

Approval is independent from Core Status.

When clicked, require:

1.  `MEDIDAS CONFIRMADAS`
2.  `ESTIMADO APROBADO`

Approval means:

> The client approved the design.

------------------------------------------------------------------------

## 13.1 Fully Approved

If:

``` text
Approved = YES
Measures Confirmed = YES
Estimate Approved = YES
```

then:

``` text
Move to designer Orders Received
Substatus = PONER EN ALTA
```

This makes the Order ready to be scheduled for ALTA.

------------------------------------------------------------------------

## 13.2 Approved but Missing Measures

If:

``` text
Approved = YES
Measures Confirmed = NO
```

then:

``` text
Core Status = ENTRANTE
Substatus = BLOQUEADA
Blocking Reason = FALTAN MEDIDAS
Due Date = maximum 2 days
Related Task = RESOLVER
```

This is a high-priority resolver situation.

Future feature:

> `RESOLVER` view, visible primarily to the manager/admin.

------------------------------------------------------------------------

## 13.3 Approved but Estimate Not Approved

If:

``` text
Approved = YES
Measures Confirmed = YES
Estimate Approved = NO
```

then the Order may proceed to:

``` text
designer Orders Received
Substatus = PONER EN ALTA
Condition = FALTA APROBACIÓN DE ESTIMADO
```

This does not block the design work itself but must remain visible as an
operational warning.

------------------------------------------------------------------------

# 14. Due Date Engine

Due dates must be dynamic.

Do NOT overwrite historical deadlines.

Store:

-   Original Due Date
-   Current Due Date
-   Due Date History
-   Reason for Change
-   Event that triggered the new deadline
-   Person/system that changed it
-   Client-promised date, when applicable

Example:

``` text
Aug 20
Order received
Due = Aug 23

Aug 23
Client changes received
Due = Aug 25

Aug 25
Client changes received
Due = Aug 27
```

The current Due Date is the active commitment.

The historical Due Dates remain immutable.

------------------------------------------------------------------------

# 15. Timeline / History

Every Order must have a complete event timeline.

Record events such as:

-   Order created
-   Imported from Trello
-   Designer assigned
-   Core Status changed
-   Substatus changed
-   Blocking Reason changed
-   Due Date created
-   Due Date changed
-   Client response
-   Client revision
-   Internal revision
-   Sent to Camila
-   Camila follow-up created
-   Client follow-up created
-   Delay email sent
-   New client-promised date entered
-   Approved
-   Measures confirmed
-   Estimate approved
-   Sent to production
-   Production adjustment
-   On Hold
-   Pause reason
-   Related Task created
-   Related Task completed

The timeline must allow the system to distinguish:

### OUR TIME

Time the Order was actively under the team's responsibility.

### CLIENT TIME

Time waiting for client response.

### OVERDUE TIME

Time past the applicable deadline.

This will later power analytics.

------------------------------------------------------------------------

# 16. Dashboard / Views

## 16.1 Dashboard Home

The dashboard should prioritize action.

Sections:

-   Overdue
-   Due Today
-   To Do Today
-   Client Follow-ups
-   Camila Follow-ups
-   Resolver items
-   Ready for ALTA
-   Production adjustments

Each item should clearly show:

-   Order
-   Designer
-   Action
-   Due Date
-   Why it requires attention
-   Related Order

------------------------------------------------------------------------

# 17. Kanban View

Columns must map directly to the operational workflow/Trello lists:

``` text
ENTRANTE
EURALIZ ORDERS RECEIVED
ADRIAN ORDERS RECEIVED
CESAR ORDERS RECEIVED
TO DO TODAY
ENVIADO A CAMILA
ENVIADO AL CLIENTE
ON HOLD
EN PRODUCCIÓN
```

Do not create separate Kanban columns for substatuses.

Instead, cards should display visual badges for conditions such as:

-   BLOQUEADA
-   OVERDUE
-   ALMOST OVERDUE
-   CAMBIOS CAMILA
-   CAMBIOS CLIENTE
-   PONER EN ALTA
-   FALTA APROBACIÓN DE ESTIMADO
-   NO RESPUESTA
-   PAUSADO
-   AJUSTES DE PRODUCCIÓN

Related Tasks should be visible from the Order card/detail view and also
appear independently in the dashboard/task views.

------------------------------------------------------------------------

# 18. Weekly View

Purpose:

> Plan which Orders each designer will actually work on each day.

Designers:

-   Euralíz
-   Adrián
-   César

Columns:

-   Monday
-   Tuesday
-   Wednesday
-   Thursday
-   Friday

The view should show:

-   number of Orders
-   Related Tasks due
-   overdue count
-   due dates
-   priority
-   designer workload

The user should be able to schedule an Order for a day.

If scheduling it for a future day would cause it to miss its deadline,
the system should clearly warn:

> This schedule will make the Order overdue.

The app should then allow the user to either schedule it earlier or
explicitly handle the client-delay workflow.

------------------------------------------------------------------------

# 19. Resolver View

Future feature, but design the data model for it now.

The Resolver view should surface Orders/Related Tasks requiring
intervention because they cannot progress normally.

Examples:

-   Approved but missing measures
-   Blocked for more than 3 days
-   Missing information
-   Customer Service Required
-   Other blocking conditions

This view can later be restricted to manager/admin users.

------------------------------------------------------------------------

# 20. Related Task Dashboard

Related Tasks must be queryable independently from Orders.

Each Related Task should include:

-   Task Name
-   Parent Order
-   Company
-   Designer / Assignee
-   Created Date
-   Due Date
-   Status
-   Trigger
-   Completed Date
-   Priority

Examples:

``` text
FOLLOW UP CLIENTE
Parent: ABC Restaurant
Due: Today
Trigger: 3 days without client response
```

``` text
ENVIAR CORREO DE ATRASO
Parent: TechSolutions
Due: Today
Trigger: Order exceeded design SLA
```

------------------------------------------------------------------------

# 21. Trello Synchronization

Trello remains the external workflow source.

The application should synchronize:

### Trello → App

-   Card creation
-   Card movement
-   List membership
-   Member assignment
-   Due date changes
-   Relevant card activity
-   Comments where needed
-   Checklists/check state where needed
-   Labels
-   Custom fields

### App → Trello

Where appropriate:

-   Move card
-   Update card/list
-   Update due date
-   Add/remove labels
-   Update custom fields
-   Create/update checklist items
-   Add comments

The sync must be idempotent and prevent automation loops.

------------------------------------------------------------------------

# 22. Trello List Mapping

The application must map Trello lists to Core Status.

Example configuration:

``` text
Trello List
"Euraliz Orders Received"
        ↓
Core Status = ORDERS_RECEIVED
Designer = Euralíz
```

Likewise:

``` text
"Adrian Orders Received"
        ↓
Core Status = ORDERS_RECEIVED
Designer = Adrián
```

``` text
"Cesar Orders Received"
        ↓
Core Status = ORDERS_RECEIVED
Designer = César
```

This allows the application to have a normalized internal model while
preserving Trello's real list structure.

------------------------------------------------------------------------

# 23. Automation Engine

All automation should follow:

``` text
TRIGGER
   ↓
CONDITION
   ↓
ACTION
   ↓
EVENT LOG
```

Examples:

### New Order

``` text
TRIGGER:
Card created

ACTION:
Create Order
Create Related Task: Enviar correo de bienvenida
Calculate initial dates
```

### Blocked Order

``` text
TRIGGER:
Blocking Reason set

ACTION:
Core Status = ENTRANTE
Substatus = BLOQUEADA
```

### Missing Measures

``` text
TRIGGER:
Measures Confirmed = NO

ACTION:
Move to ENTRANTE
Set FALTAN MEDIDAS
Create RESOLVER task
Set 2-day deadline
```

### Client Follow-up

``` text
TRIGGER:
3 days in ENVIADO AL CLIENTE without response

ACTION:
Create Related Task: FOLLOW UP CLIENTE
```

### On Hold

``` text
TRIGGER:
9 days in ENVIADO AL CLIENTE without response

ACTION:
Move to ON HOLD
Set NO RESPUESTA
Set CUSTOMER SERVICE REQUIRED
```

### Delay

``` text
TRIGGER:
Order becomes overdue

ACTION:
Set OVERDUE
Create ENVIAR CORREO DE ATRASO
```

### Delay Resolution

``` text
TRIGGER:
Delay email task completed

ACTION:
Request new promised date
Update Current Due Date
Log communication
Resolve OVERDUE
```

### Production

``` text
TRIGGER:
Order is TO DO TODAY
AND PONER EN ALTA
AND marked DONE

ACTION:
At beginning of next working day:
Move to EN PRODUCCIÓN
```

------------------------------------------------------------------------

# 24. Data Model --- Suggested Entities

At minimum:

## Order

``` text
id
trello_card_id
company_name
task_name
designer_id
core_status
substatus
blocking_reason
blocking_reason_other
start_date
original_due_date
current_due_date
last_updated
last_meaningful_update
client_last_response
approved
measures_confirmed
estimate_approved
client_revision_count
internal_revision_count
created_at
updated_at
```

## RelatedTask

``` text
id
order_id
title
type
status
assignee_id
created_at
due_date
completed_at
trigger_type
priority
```

## OrderEvent

``` text
id
order_id
event_type
timestamp
actor
previous_value
new_value
metadata
```

## DueDateHistory

``` text
id
order_id
previous_due_date
new_due_date
reason
trigger_event
created_at
created_by
client_promised_date
```

## BlockingReason

Can be represented as a controlled field rather than a separate entity:

``` text
FALTAN MEDIDAS
FALTA LOGO
OTROS
```

## Designer

``` text
id
name
trello_member_id
active
```

## AutomationRule

``` text
id
name
trigger
conditions
actions
enabled
```

------------------------------------------------------------------------

# 25. Permissions

At minimum:

## Admin / Manager

Can:

-   view all designers
-   modify workflow rules
-   modify SLA values
-   see Resolver
-   override fields
-   inspect complete history
-   manage automations

## Designer

Can:

-   see assigned Orders
-   schedule work
-   complete Orders
-   complete Related Tasks
-   update relevant fields
-   mark approval data
-   interact with workflow normally

------------------------------------------------------------------------

# 26. MVP Development Order

Build in this order.

## Phase 1 --- Trello Foundation

-   Trello authentication
-   Board/list/card sync
-   Designer mapping
-   Order database
-   Core Status mapping
-   Basic Kanban
-   Order detail

## Phase 2 --- Workflow Data

-   Substatuses
-   Blocking Reasons
-   Approval fields
-   Related Tasks
-   Timeline/events
-   Done mechanism

## Phase 3 --- Deadline Engine

-   SLA configuration
-   Dynamic Due Dates
-   Due Date History
-   Client waiting time
-   Overdue
-   Almost Overdue
-   Deadline calculations

## Phase 4 --- Automation Engine

-   Welcome email task
-   Information request task
-   Resolver task
-   Camila follow-up
-   Client follow-ups
-   Delay email
-   New promised date workflow
-   On Hold automation
-   Production transition
-   Production adjustments

## Phase 5 --- Planning

-   Today dashboard
-   Weekly planner
-   Workload visualization
-   Related Task dashboard
-   Resolver view

## Phase 6 --- Analytics

-   Design turnaround
-   Client waiting time
-   Overdue time
-   Revision counts
-   Follow-up counts
-   Designer workload
-   Bottleneck analysis
-   Camila turnaround
-   Production adjustment frequency

------------------------------------------------------------------------

# 27. Important Product Principles

1.  **Do not duplicate Trello unnecessarily.** The app should add
    intelligence, not become a second Trello.

2.  **Core Status and Substatus must remain separate.**

3.  **Related Tasks must remain separate from the parent Order.**

4.  **A Related Task does not automatically move the Order.**

5.  **Dates must be event-driven and historically preserved.**

6.  **Overdue cannot be hidden by manually changing a date.**

7.  **Client waiting time must be separated from team working time.**

8.  **Fields can trigger automations.**

9.  **Every automated action must be logged.**

10. **Every Order must have an explicit Done mechanism.**

11. **Trello synchronization must be bidirectional where useful and must
    prevent loops.**

12. **The system should always be able to answer:**

    -   What needs to happen?
    -   Who needs to do it?
    -   When is it due?
    -   Why does it need to happen?
    -   What caused it?
    -   How long has the Order been waiting?
    -   Who currently has the ball?

------------------------------------------------------------------------

# 28. Known Rules Still Requiring Final Specification

Do not invent business logic for these before confirming with the
product owner:

-   Exact threshold for `ALMOST OVERDUE`
-   Exact definition of working days vs calendar days
-   Whether weekends count toward each SLA
-   Exact behavior when a Related Task is not completed
-   Exact email integration/provider
-   Whether the app should send emails directly or only generate email
    tasks
-   Exact Trello board/list IDs
-   Exact Trello labels
-   Exact production adjustment workflow
-   Exact meaning/behavior of the ALTA process beyond the rules already
    specified

When a rule is unclear, **do not silently infer it**. Keep it
configurable or mark it as requiring product confirmation.

------------------------------------------------------------------------

# 29. Definition of Success

The application is successful if a manager can open it each morning and
immediately understand:

> **What is late?**
>
> **What must happen today?**
>
> **Who is responsible?**
>
> **Which clients need follow-up?**
>
> **Which orders are blocked?**
>
> **Which orders are ready for ALTA?**
>
> **Which orders are waiting on Camila?**
>
> **Which orders are waiting on clients?**
>
> **Why is every problematic order stuck?**

The application should turn the existing Trello workflow into a
**measurable, proactive operational system** without forcing the design
team to abandon Trello.
