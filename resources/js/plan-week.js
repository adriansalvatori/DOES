/**
 * Plan week interactions: drag cards between days and people (with a drop line and in-cell reordering), drag work
 * from the backlog, box-select or ⌘/Shift-click several cards for the bulk bar, ⌘Z to undo, "+" to add a task in
 * place. The server does every change; this only works out what the user meant.
 */
function registerPlanWeek() {
    window.Alpine.data('planWeek', () => ({
        dragging: null, // { kind: 'tasks', ids: [] } | { kind: 'deliverable', id }
        overCell: null,
        dropBefore: null, // task id the drop line sits above, or null for the end of the cell
        selected: [],
        box: null, // { x0, y0, x1, y1 } while box-selecting
        undoMessage: '',
        undoTimer: null,
        quick: null, // { cell, assigneeId, column, deliverableId, deliverableLabel, query, results, title, active, opened }
        timer: null,
        scrollTimer: null,
        trayOpen: true,

        init() {
            this.timer = setInterval(() => this.refresh(), 30000);
            let saved = null;
            try { saved = window.localStorage.getItem('plan-week.tray'); } catch (error) {}
            this.trayOpen = saved === null ? window.innerWidth >= 1536 : saved === '1';
        },

        toggleTray() {
            this.trayOpen = ! this.trayOpen;
            try { window.localStorage.setItem('plan-week.tray', this.trayOpen ? '1' : '0'); } catch (error) {}
        },

        destroy() {
            clearInterval(this.timer);
            clearInterval(this.scrollTimer);
        },

        get busy() {
            return this.dragging !== null || this.quick !== null || this.box !== null || this.selected.length > 0;
        },

        refresh() {
            if (document.visibilityState === 'visible' && ! this.busy) {
                this.$wire.$refresh();
            }
        },

        // Cards ---------------------------------------------------------------------------------------------------

        cardClick(event, taskId, deliverableId) {
            if (event.metaKey || event.ctrlKey || event.shiftKey) {
                this.toggleSelected(taskId);
                return;
            }
            this.$dispatch('open-deliverable', { deliverableId });
        },

        toggleSelected(taskId) {
            this.selected = this.selected.includes(taskId)
                ? this.selected.filter((id) => id !== taskId)
                : [...this.selected, taskId];
        },

        isSelected(taskId) {
            return this.selected.includes(taskId);
        },

        clearSelection() {
            this.selected = [];
        },

        // Dragging ------------------------------------------------------------------------------------------------

        startTasks(event, taskId) {
            const ids = this.selected.includes(taskId) ? [...this.selected] : [taskId];
            this.dragging = { kind: 'tasks', ids };
            event.dataTransfer.setData('text/plain', 'tasks:' + ids.join(','));
            event.dataTransfer.effectAllowed = 'move';
        },

        startDeliverable(event, deliverableId) {
            this.dragging = { kind: 'deliverable', id: deliverableId };
            event.dataTransfer.setData('text/plain', 'deliverable:' + deliverableId);
            event.dataTransfer.effectAllowed = 'move';
        },

        end() {
            this.dragging = null;
            this.overCell = null;
            this.dropBefore = null;
            clearInterval(this.scrollTimer);
            this.scrollTimer = null;
        },

        over(event, cellKey) {
            if (! this.dragging) {
                return;
            }
            event.preventDefault();
            this.overCell = cellKey;
            this.dropBefore = this.dragging.kind === 'tasks' ? this.cardBelow(event.currentTarget, event.clientY) : null;
            this.autoScroll(event);
        },

        /**
         * The first card (not being dragged) whose middle is below the pointer.
         */
        cardBelow(cell, pointerY) {
            const moving = this.dragging?.ids ?? [];
            const cards = [...cell.querySelectorAll('[data-task-id]')].filter((card) => ! moving.includes(Number(card.dataset.taskId)));
            const below = cards.find((card) => {
                const rect = card.getBoundingClientRect();
                return pointerY < rect.top + rect.height / 2;
            });
            return below ? Number(below.dataset.taskId) : null;
        },

        autoScroll(event) {
            const pane = this.$refs.grid;
            if (! pane) {
                return;
            }
            const rect = pane.getBoundingClientRect();
            const edge = 60;
            const dy = event.clientY < rect.top + edge ? -14 : (event.clientY > rect.bottom - edge ? 14 : 0);
            const dx = event.clientX < rect.left + edge ? -14 : (event.clientX > rect.right - edge ? 14 : 0);
            clearInterval(this.scrollTimer);
            this.scrollTimer = (dx || dy) ? setInterval(() => pane.scrollBy(dx, dy), 16) : null;
        },

        drop(event, assigneeId, column) {
            event.preventDefault();
            const dragging = this.dragging;
            const cell = event.currentTarget;
            const before = this.dropBefore;
            this.end();

            if (! dragging) {
                return;
            }

            if (dragging.kind === 'deliverable') {
                this.$wire.planDeliverable(dragging.id, assigneeId, column).then((result) => {
                    if (result && result.ok && result.needsTask) {
                        this.openQuick(cell.dataset.cell, assigneeId, column, dragging.id, result.label || '');
                    }
                });
                return;
            }

            const remaining = [...cell.querySelectorAll('[data-task-id]')]
                .map((card) => Number(card.dataset.taskId))
                .filter((id) => ! dragging.ids.includes(id));
            const at = before === null ? remaining.length : Math.max(0, remaining.indexOf(before));
            remaining.splice(at, 0, ...dragging.ids);
            this.selected = [];
            this.$wire.placeTasks(remaining, assigneeId, column);
        },

        dropOnBacklog(event) {
            event.preventDefault();
            const dragging = this.dragging;
            this.end();
            if (! dragging) {
                return;
            }
            if (dragging.kind === 'deliverable') {
                this.$wire.unplanDeliverable(dragging.id);
                return;
            }
            dragging.ids.forEach((id) => this.$wire.unscheduleTask(id));
            this.selected = [];
        },

        // Box selection -------------------------------------------------------------------------------------------

        boxStart(event) {
            if (event.button !== 0 || event.target.closest('[data-task-id], [data-deliverable-id], button, input, a, label, [data-no-box]')) {
                return;
            }
            if (! event.metaKey && ! event.shiftKey) {
                this.selected = [];
            }
            this.box = { x0: event.clientX, y0: event.clientY, x1: event.clientX, y1: event.clientY, base: [...this.selected] };
            const move = (moveEvent) => {
                this.box.x1 = moveEvent.clientX;
                this.box.y1 = moveEvent.clientY;
                this.selectInBox();
            };
            const up = () => {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                this.box = null;
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        },

        selectInBox() {
            const left = Math.min(this.box.x0, this.box.x1);
            const right = Math.max(this.box.x0, this.box.x1);
            const top = Math.min(this.box.y0, this.box.y1);
            const bottom = Math.max(this.box.y0, this.box.y1);
            if (right - left < 4 && bottom - top < 4) {
                return;
            }
            const inside = [...this.$root.querySelectorAll('[data-task-id][data-editable]')]
                .filter((card) => {
                    const rect = card.getBoundingClientRect();
                    return rect.left < right && rect.right > left && rect.top < bottom && rect.bottom > top;
                })
                .map((card) => Number(card.dataset.taskId));
            this.selected = [...new Set([...this.box.base, ...inside])];
        },

        get boxStyle() {
            if (! this.box) {
                return 'display: none';
            }
            const left = Math.min(this.box.x0, this.box.x1);
            const top = Math.min(this.box.y0, this.box.y1);
            return `left:${left}px;top:${top}px;width:${Math.abs(this.box.x1 - this.box.x0)}px;height:${Math.abs(this.box.y1 - this.box.y0)}px`;
        },

        // Bulk ----------------------------------------------------------------------------------------------------

        bulkMove(target) {
            const ids = [...this.selected];
            this.selected = [];
            this.$wire.bulkMove(ids, target);
        },

        bulkAssign(assigneeId) {
            const ids = [...this.selected];
            this.selected = [];
            this.$wire.bulkAssign(ids, Number(assigneeId));
        },

        bulkComplete() {
            const ids = [...this.selected];
            this.selected = [];
            this.$wire.bulkComplete(ids);
        },

        // Undo ----------------------------------------------------------------------------------------------------

        showUndo(message) {
            this.undoMessage = message;
            clearTimeout(this.undoTimer);
            this.undoTimer = setTimeout(() => { this.undoMessage = ''; }, 6000);
        },

        undo() {
            this.undoMessage = '';
            this.$wire.undo();
        },

        keydown(event) {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z' && ! event.shiftKey && ! typing) {
                event.preventDefault();
                this.undo();
            }
            if (event.key === 'Escape' && ! typing) {
                this.selected = [];
            }
        },

        // Quick add -----------------------------------------------------------------------------------------------

        openQuick(cell, assigneeId, column, deliverableId = null, deliverableLabel = '') {
            this.quick = { cell, assigneeId, column, deliverableId, deliverableLabel, query: '', results: [], title: '', active: 0, opened: Date.now() };
            this.$nextTick(() => this.$root.querySelector(`[data-quick="${cell}"] input`)?.focus());
        },

        closeQuick() {
            this.quick = null;
        },

        async searchDeliverables() {
            const term = this.quick?.query ?? '';
            const results = await this.$wire.searchDeliverables(term);
            if (this.quick && this.quick.query === term) {
                this.quick.results = results;
                this.quick.active = 0;
            }
        },

        pickDeliverable(result) {
            if (! result) {
                return;
            }
            this.quick.deliverableId = result.id;
            this.quick.deliverableLabel = result.label;
            this.$nextTick(() => this.$root.querySelector(`[data-quick="${this.quick.cell}"] input`)?.focus());
        },

        moveActive(step) {
            const count = this.quick?.results.length ?? 0;
            if (count) {
                this.quick.active = (this.quick.active + step + count) % count;
            }
        },

        async submitQuick() {
            const quick = this.quick;
            if (! quick?.deliverableId || ! quick.title.trim()) {
                return;
            }
            const result = await this.$wire.quickAdd(quick.deliverableId, quick.title, quick.assigneeId, quick.column);
            if (result && result.ok) {
                // Stay open on the same cell and deliverable for the next task.
                this.quick = { ...quick, title: '' };
                this.$nextTick(() => this.$root.querySelector(`[data-quick="${quick.cell}"] input`)?.focus());
            }
        },
    }));
}

if (window.Alpine) {
    registerPlanWeek();
} else {
    document.addEventListener('alpine:init', registerPlanWeek);
}
