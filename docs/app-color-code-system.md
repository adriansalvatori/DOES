# Kudos Design Ops — App-Wide Color Code System

## 1. Executive Summary & Core Color Rules

This document establishes the official, simplified **Color Code System** for Kudos Design Ops. The palette is designed for maximum clarity and consistency across all application views (Kanban Board, Weekly Planner, Backlog Inbox, Resolver List, and Order Detail Modals).

### Official Color Code Palette

```
┌────────────────────────────────────────────────────────────────────────┐
│                        OFFICIAL COLOR CODE PALETTE                     │
├───────────────────┬───────────────────┬────────────────────────────────┤
│ Color             │ Workflow Stage    │ Designer / Entity              │
├───────────────────┼───────────────────┼────────────────────────────────┤
│ 🟡 Yellow         │ TO DO TODAY       │ External Designer              │
│ 🟧 Orange         │ BLOCKED / CS      │ Customer Service (CS)          │
│ 🩷 Bright Pink    │ ALTA / Production │ Euralíz                        │
│ 🔵 Sky Blue       │ Client            │ César                          │
│ 🟣 Purple         │ Camila            │ Camila QA                      │
│ 🟢 Green          │ Adrián Queue      │ Adrián                         │
│ ⚪ No Color       │ Done              │ Archived / Completed           │
└───────────────────┴───────────────────┴────────────────────────────────┘
```

---

## 2. Color Assignments by Category

### 1. 🟡 Yellow (`yellow` / `amber`)
* **Workflow Stages**: `TO DO TODAY` (Today's active execution queue).
* **Designers**: **External / Freelance Designers**.
* **Visual Intent**: High-visibility focus for today's work and external assignments.

### 2. 🟧 Orange (`orange`)
* **Workflow Stages**: 
  - **BLOCKED**: `ENTRANTE`, `substatus = BLOQUEADA`, missing measures, missing info.
  - **CUSTOMER SERVICE (CS)**: `ON HOLD` (due to 9 days without client response), `substatus = CUSTOMER SERVICE REQUIRED`, `substatus = NO RESPUESTA`.
* **Visual Intent**: Operational blockers and customer service intervention required.

### 3. 🩷 Bright Pink (`pink` / `fuchsia`)
* **Workflow Stages**: 
  - **ALTA & Production**: `PONER EN ALTA`, `EN PRODUCCIÓN`, `ENVIADO EN ALTA`, `AJUSTES DE PRODUCCIÓN`.
* **Designers**: **Euralíz** (`EURALIZ ORDERS RECEIVED` & Euralíz designer theme).
* **Visual Intent**: Final design approval, ready for ALTA, print production, and Euralíz's queue.

### 4. 🔵 Sky Blue (`sky`)
* **Workflow Stages**: 
  - **Client Related**: `ENVIADO AL CLIENTE` (delivered to client, SLA clock paused), `WAITING FOR CLIENT`, `CAMBIOS CLIENTE`.
* **Designers**: **César** (`CESAR ORDERS RECEIVED` & César designer theme).
* **Visual Intent**: Client feedback window and César's queue.

### 5. 🟣 Purple (`purple`)
* **Workflow Stages**: 
  - **Camila Related**: `ENVIADO A CAMILA` (internal QA review), `CAMBIOS CAMILA`, Camila follow-ups.
* **Visual Intent**: Internal quality assurance with Camila.

### 6. 🟢 Green (`emerald` / `green`)
* **Workflow Stages**: 
  - **Adrián Related**: `ADRIAN ORDERS RECEIVED`.
* **Designers**: **Adrián** (`ADRIAN ORDERS RECEIVED` & Adrián designer theme).
* **Visual Intent**: Adrián's queue and work ownership.

### 7. ⚪ No Color / Neutral (`white` / `stone-100` / `muted`)
* **Workflow Stages**: 
  - **Done / Completed**: Items marked `done_today = true` in daily planner or Kanban board.
  - **Archived**: `ARCHIVED` orders closed in history.
* **Visual Intent**: Uncluttered, transparent background to de-emphasize completed items and focus attention on pending work.

---

## 3. Component Style Matrix

| Entity / State | Dominant Color | Tailwind Badge Classes | Card Background / Border Style |
| :--- | :--- | :--- | :--- |
| **`TO DO TODAY`** | 🟡 Yellow | `bg-yellow-100 text-yellow-900 border-yellow-300` | `bg-yellow-50/60 border-yellow-300` |
| **External Designer** | 🟡 Yellow | `bg-yellow-100 text-yellow-800 border-yellow-300` | Dot: `bg-yellow-400` |
| **`ENTRANTE` / Blocked** | 🟧 Orange | `bg-orange-100 text-orange-900 border-orange-300` | `bg-orange-50/70 border-orange-400` |
| **Customer Service (CS)** | 🟧 Orange | `bg-orange-100 text-orange-900 border-orange-300` | `bg-orange-50/70 border-orange-400` |
| **`PONER EN ALTA` / Production** | 🩷 Bright Pink | `bg-pink-100 text-pink-900 border-pink-300` | `bg-pink-50/70 border-pink-300` |
| **Euralíz** | 🩷 Bright Pink | `bg-pink-100 text-pink-800 border-pink-300` | Dot: `bg-pink-500` |
| **Client / `ENVIADO AL CLIENTE`** | 🔵 Sky Blue | `bg-sky-100 text-sky-800 border-sky-300` | `bg-sky-50/60 border-sky-200` |
| **César** | 🔵 Sky Blue | `bg-sky-100 text-sky-800 border-sky-300` | Dot: `bg-sky-500` |
| **Camila / `ENVIADO A CAMILA`** | 🟣 Purple | `bg-purple-100 text-purple-800 border-purple-300` | `bg-purple-50/60 border-purple-200` |
| **Adrián** | 🟢 Green | `bg-emerald-100 text-emerald-800 border-emerald-300` | Dot: `bg-emerald-500` |
| **Done Today / Completed** | ⚪ No Color | `bg-stone-100 text-stone-500 border-stone-200` | `bg-white/80 border-stone-200 opacity-60` |
| **Archived** | ⚪ No Color | `bg-slate-100 text-slate-500 border-slate-200` | `bg-slate-50/50 border-slate-200` |

---

*Documentation updated according to simplified Kudos Design Ops color specifications.*
