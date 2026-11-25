@assets
<script>
    window.plannerBoard = (presets) => ({
        activeDragCardId: null,
        activeDragPosition: null,
        clearDragState() {
            this.activeDragCardId = null;
            this.activeDragPosition = null;
        },
        cardDragStart(e) {
            const id = parseInt(e.currentTarget.dataset.subtaskId, 10);
            if (this.selectedTaskIds.includes(id)) {
                const allCards = Array.from(document.querySelectorAll('[data-subtask-id]'));
                const orderedSelected = allCards.map(c => parseInt(c.getAttribute('data-subtask-id'), 10)).filter(cardId => this.selectedTaskIds.includes(cardId));
                const finalIds = orderedSelected.length ? [...new Set(orderedSelected)] : this.selectedTaskIds;
                e.dataTransfer.setData('text/plain', 'subtasks:' + finalIds.join(','));
            } else {
                this.selectedTaskIds = [id];
                e.dataTransfer.setData('text/plain', 'subtask:' + id);
            }
            e.dataTransfer.effectAllowed = 'move';
        },
        cardDragOver(e) {
            const el = e.currentTarget;
            const rect = el.getBoundingClientRect();
            this.activeDragCardId = parseInt(el.dataset.subtaskId, 10);
            this.activeDragPosition = (e.clientY < rect.top + rect.height / 2) ? 'above' : 'below';
            e.dataTransfer.dropEffect = 'move';
        },
        dragIndicatorStyle(id) {
            if (this.activeDragCardId !== id) return '';
            return this.activeDragPosition === 'above' ? 'box-shadow: 0 -2px 0 0 var(--color-ink)' : 'box-shadow: 0 2px 0 0 var(--color-ink)';
        },
        cardDragLeave(e) {
            const el = e.currentTarget;
            if (!el.contains(e.relatedTarget) && this.activeDragCardId === parseInt(el.dataset.subtaskId, 10)) {
                this.clearDragState();
            }
        },
        cardDrop(e) {
            const el = e.currentTarget;
            const column = el.closest('[data-day-container]');
            this.handleDayDrop(e, column.dataset.dayDate, parseInt(column.dataset.designerId, 10), parseInt(el.dataset.subtaskId, 10));
        },
        chromeLoaded: false,
        loadChrome() {
            if (this.chromeLoaded) return;
            this.chromeLoaded = true;
            this.$wire.plannerChrome().then(html => {
                this.$refs.chromeHost.innerHTML = html;
                window.Alpine.initTree(this.$refs.chromeHost);
            });
        },
        modalCalMonth: null,
        modalCalBase() {
            if (!this.modalCalMonth) {
                const ref = this.subtaskDate ? new Date(this.subtaskDate + 'T00:00:00') : new Date();
                this.modalCalMonth = new Date(ref.getFullYear(), ref.getMonth(), 1);
            }
            return this.modalCalMonth;
        },
        modalCalShift(step) {
            const base = this.modalCalBase();
            this.modalCalMonth = new Date(base.getFullYear(), base.getMonth() + step, 1);
        },
        modalCalLabel() {
            return this.modalCalBase().toLocaleDateString(document.documentElement.lang || undefined, { month: 'long', year: 'numeric' });
        },
        modalCalDays() {
            const base = this.modalCalBase();
            const pad = (n) => String(n).padStart(2, '0');
            const iso = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            const mondayOf = (d) => { const c = new Date(d.getFullYear(), d.getMonth(), d.getDate()); c.setDate(c.getDate() - ((c.getDay() + 6) % 7)); return c; };
            const start = mondayOf(base);
            const end = new Date(base.getFullYear(), base.getMonth() + 1, 0);
            const endGrid = mondayOf(end); endGrid.setDate(endGrid.getDate() + 6);
            const todayIso = iso(new Date());
            const currentMonday = iso(mondayOf(new Date()));
            const days = [];
            for (let c = new Date(start); c <= endGrid; c.setDate(c.getDate() + 1)) {
                days.push({ date: iso(c), day: c.getDate(), currentMonth: c.getMonth() === base.getMonth(), today: iso(c) === todayIso, currentWeek: iso(mondayOf(c)) === currentMonday });
            }
            return days;
        },
        taskMenu: { open: false, id: null, designerId: null, date: '', hasOrder: false, category: '', work: '', top: 0, left: 0, section: '' },
        openTaskMenu(e) {
            const btn = e.currentTarget;
            const wrap = btn.closest('[data-planner-task-menu]');
            const rect = btn.getBoundingClientRect();
            const width = 224;
            this.taskMenu = {
                open: true,
                id: parseInt(wrap.dataset.taskId, 10),
                designerId: wrap.dataset.designerId ? parseInt(wrap.dataset.designerId, 10) : null,
                date: wrap.dataset.date || '',
                hasOrder: wrap.dataset.hasOrder === '1',
                category: wrap.dataset.category || '',
                work: wrap.dataset.work || '',
                top: rect.bottom + 4,
                left: Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)),
                section: '',
            };
            this.$nextTick(() => {
                const menu = this.$refs.taskMenu;
                if (menu && this.taskMenu.top + menu.offsetHeight > window.innerHeight - 8) {
                    this.taskMenu.top = Math.max(8, rect.top - menu.offsetHeight - 4);
                }
            });
        },
        closeTaskMenu() {
            if (this.taskMenu.open) {
                this.taskMenu.open = false;
            }
        },
        taskMenuDays() {
            try { return JSON.parse(this.$refs.taskMenuData.dataset.days).filter(d => d.date !== this.taskMenu.date); } catch (e) { return []; }
        },
        taskMenuCategories() {
            try { return JSON.parse(this.$refs.taskMenuData.dataset.categories); } catch (e) { return []; }
        },
        moveTaskTo(date) {
            const m = this.taskMenu;
            this.closeTaskMenu();
            this.$wire.reorderSubtasks([m.id], date, m.designerId);
        },
        setTaskCategory(value) {
            const id = this.taskMenu.id;
            this.closeTaskMenu();
            this.$wire.updateTaskCategory(id, value);
        },
        setTaskType(isWork) {
            const id = this.taskMenu.id;
            this.closeTaskMenu();
            this.$wire.updateTaskType(id, isWork);
        },
        removeTask() {
            const id = this.taskMenu.id;
            this.closeTaskMenu();
            this.$wire.deleteSubtask(id);
        },
        handleDayDrop(e, dayDateStr, designerId, targetCardId = null) {
            this.clearDragState();
            let rawData = e.dataTransfer ? e.dataTransfer.getData('text/plain') : '';
            if (!rawData) return;

            if (rawData.startsWith('order:')) {
                let orderId = rawData.replace('order:', '');
                this.$wire.scheduleOrder(orderId, dayDateStr);
                return;
            }

            let movingIds = [];
            if (rawData.startsWith('subtasks:')) {
                movingIds = rawData.replace('subtasks:', '').split(',').map(id => parseInt(id, 10)).filter(Boolean);
            } else if (rawData.startsWith('subtask:')) {
                let singleId = parseInt(rawData.replace('subtask:', ''), 10);
                if (!isNaN(singleId)) movingIds = [singleId];
            }

            if (movingIds.length === 0) return;

            const dayEl = e.currentTarget.closest('[data-day-container]') || e.currentTarget;
            const allCardsInDay = Array.from(dayEl.querySelectorAll('[data-subtask-id]'));
            
            const remainingCards = allCardsInDay.filter(c => !movingIds.includes(parseInt(c.getAttribute('data-subtask-id'), 10)));
            const remainingIds = remainingCards.map(c => parseInt(c.getAttribute('data-subtask-id'), 10));

            let insertIdx = remainingCards.length;
            let targetEl = targetCardId ? allCardsInDay.find(c => parseInt(c.getAttribute('data-subtask-id'), 10) === parseInt(targetCardId, 10)) : (e.target ? e.target.closest('[data-subtask-id]') : null);

            if (targetEl && remainingCards.includes(targetEl)) {
                const rect = targetEl.getBoundingClientRect();
                const targetIdx = remainingCards.indexOf(targetEl);
                const isAbove = e.clientY < (rect.top + rect.height / 2);
                insertIdx = isAbove ? targetIdx : targetIdx + 1;
            } else if (remainingCards.length > 0) {
                const mouseY = e.clientY;
                let found = false;
                for (let i = 0; i < remainingCards.length; i++) {
                    const rect = remainingCards[i].getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (mouseY < midY) {
                        insertIdx = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    insertIdx = remainingCards.length;
                }
            } else {
                insertIdx = 0;
            }

            remainingIds.splice(insertIdx, 0, ...movingIds);
            this.$wire.reorderSubtasks(remainingIds, dayDateStr, designerId);
            this.selectedTaskIds = [];
        },
        calendarOpen: false,
        subtaskModalOpen: false,
        subtaskOrderId: '',
        subtaskDate: '',
        subtaskDesignerId: '',
        subtaskTitle: '',
        subtaskIsWorkTask: true,
        orderDropdownOpen: false,
        orderSearchQuery: '',
        workspaceOrdersList: [],
        workspaceOrdersPromise: null,
        workspaceOrdersFetchedAt: 0,
        ensureWorkspaceOrders() {
            if (this.workspaceOrdersPromise && Date.now() - this.workspaceOrdersFetchedAt < 20000) {
                return this.workspaceOrdersPromise;
            }
            this.workspaceOrdersFetchedAt = Date.now();
            this.workspaceOrdersPromise = this.$wire.workspaceOrdersForPicker().then(list => {
                this.workspaceOrdersList = list || [];
                return this.workspaceOrdersList;
            });
            return this.workspaceOrdersPromise;
        },
        subtaskPresetsList: presets,
        modalOrderHighlightedIndex: -1,
        linkNoteModalOpen: false,
        linkNoteTaskId: null,
        linkNoteTitle: '',
        linkNoteOrderId: '',
        linkNoteSearchQuery: '',
        linkNoteDropdownOpen: false,
        linkNoteHighlightedIndex: -1,
        openLinkNoteModal(taskId, noteTitle = '') {
            this.ensureWorkspaceOrders();
            this.linkNoteTaskId = taskId;
            this.linkNoteTitle = noteTitle;
            this.linkNoteOrderId = '';
            this.linkNoteSearchQuery = '';
            this.linkNoteDropdownOpen = true;
            this.linkNoteHighlightedIndex = -1;
            this.linkNoteModalOpen = true;
            this.$nextTick(() => {
                if (this.$refs.linkNoteSearchInput) {
                    this.$refs.linkNoteSearchInput.focus();
                }
            });
        },
        filterWorkspaceOrders(query) {
            if (typeof window.filterWorkspaceOrders === 'function') {
                return window.filterWorkspaceOrders(this.workspaceOrdersList, query);
            }
            if (!query) return this.workspaceOrdersList || [];
            const clean = (query || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            const tokens = clean.replace(/[^\p{L}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean);
            if (!tokens.length) return this.workspaceOrdersList || [];
            return (this.workspaceOrdersList || []).filter(o => {
                const norm = (str) => (str || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                const h = `${norm(o.company)} ${norm(o.location)} ${norm(o.task)} ${norm(o.wo_number)} ${norm(o.trello_card_title)} ${norm(o.responsible_person)} ${norm(o.text)}`;
                return tokens.every(t => h.includes(t));
            });
        },
        getFilteredLinkNoteOrders() {
            return this.filterWorkspaceOrders(this.linkNoteSearchQuery);
        },
        navigateLinkNoteOrder(step) {
            const list = this.getFilteredLinkNoteOrders();
            if (list.length === 0) return;
            if (this.linkNoteHighlightedIndex === -1) {
                this.linkNoteHighlightedIndex = step > 0 ? 0 : list.length - 1;
            } else {
                this.linkNoteHighlightedIndex = (this.linkNoteHighlightedIndex + step + list.length) % list.length;
            }
            this.$nextTick(() => {
                const container = this.$refs.linkNoteDropdownPanel;
                if (!container) return;
                const items = container.querySelectorAll('.link-note-order-item-btn');
                if (items[this.linkNoteHighlightedIndex]) {
                    items[this.linkNoteHighlightedIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        },
        selectHighlightedLinkNoteOrder() {
            const list = this.getFilteredLinkNoteOrders();
            if (this.linkNoteHighlightedIndex >= 0 && list[this.linkNoteHighlightedIndex]) {
                const item = list[this.linkNoteHighlightedIndex];
                this.linkNoteOrderId = item.id;
                this.linkNoteSearchQuery = item.text;
                this.linkNoteDropdownOpen = false;
                this.linkNoteHighlightedIndex = -1;
                this.submitLinkNoteModal();
            }
        },
        submitLinkNoteModal() {
            if (!this.linkNoteOrderId) {
                alert(@js(__('Debes seleccionar una orden del workspace para vincular la nota.')));
                return;
            }
            this.$wire.linkSubtaskToOrder(this.linkNoteTaskId, this.linkNoteOrderId);
            this.linkNoteModalOpen = false;
        },
        getFilteredOrders() {
            return this.filterWorkspaceOrders(this.orderSearchQuery);
        },
        navigateModalOrder(step) {
            const list = this.getFilteredOrders();
            if (list.length === 0) return;
            if (this.modalOrderHighlightedIndex === -1) {
                this.modalOrderHighlightedIndex = step > 0 ? 0 : list.length - 1;
            } else {
                this.modalOrderHighlightedIndex = (this.modalOrderHighlightedIndex + step + list.length) % list.length;
            }
            this.$nextTick(() => {
                const container = this.$refs.modalOrderDropdownPanel;
                if (!container) return;
                const items = container.querySelectorAll('.modal-order-item-btn');
                if (items[this.modalOrderHighlightedIndex]) {
                    items[this.modalOrderHighlightedIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        },
        selectHighlightedModalOrder() {
            const list = this.getFilteredOrders();
            if (this.modalOrderHighlightedIndex >= 0 && list[this.modalOrderHighlightedIndex]) {
                const item = list[this.modalOrderHighlightedIndex];
                this.subtaskOrderId = item.id;
                this.orderSearchQuery = item.text;
                if (item.designer_id) this.subtaskDesignerId = String(item.designer_id);
                this.orderDropdownOpen = false;
                this.modalOrderHighlightedIndex = -1;
            }
        },
        openCreateSubtaskModal(orderId = '', dateStr = '', designerId = '') {
            this.subtaskOrderId = orderId ? String(orderId) : '';
            this.subtaskDate = dateStr || '';
            this.subtaskTitle = '';
            this.subtaskIsWorkTask = true;
            this.orderDropdownOpen = false;
            
            this.orderSearchQuery = '';
            this.subtaskDesignerId = designerId ? String(designerId) : '';
            this.ensureWorkspaceOrders().then(() => {
                const match = this.workspaceOrdersList.find(o => String(o.id) === String(orderId));
                if (!match) return;
                this.orderSearchQuery = match.text;
                if (match.designer_id) this.subtaskDesignerId = String(match.designer_id);
            });

            this.subtaskModalOpen = true;
            this.$nextTick(() => {
                if (!this.subtaskOrderId && this.$refs.orderSearchInput) {
                    this.$refs.orderSearchInput.focus();
                } else if (this.$refs.subtaskTitleInput) {
                    this.$refs.subtaskTitleInput.focus();
                }
            });
        },
        submitSubtaskModal() {
            if (!this.subtaskTitle.trim()) {
                alert(@js(__('Por favor ingresa el nombre de la subtarea o nota.')));
                return;
            }
            this.$wire.scheduleSubtask(this.subtaskOrderId || null, this.subtaskTitle.trim(), this.subtaskDate, this.subtaskDesignerId, this.subtaskIsWorkTask);
            this.subtaskModalOpen = false;
        },
        undoToastOpen: false,
        undoToastMessage: '',
        undoToastTimer: null,
        triggerUndoToast(msg) {
            this.undoToastMessage = msg || @js(__('Subtarea eliminada'));
            this.undoToastOpen = true;
            if (this.undoToastTimer) clearTimeout(this.undoToastTimer);
            this.undoToastTimer = setTimeout(() => {
                this.undoToastOpen = false;
            }, 5000);
        },
        hideUndoToast() {
            this.undoToastOpen = false;
            if (this.undoToastTimer) clearTimeout(this.undoToastTimer);
        },
        headerHeight: 148,
        updateHeaderHeight() {
            const wrapper = document.getElementById('tour-planner-sticky-wrapper');
            if (wrapper) {
                const h = wrapper.offsetHeight;
                if (h > 40) {
                    this.headerHeight = h;
                    document.documentElement.style.setProperty('--planner-header-h', h + 'px');
                    if (this.$refs.plannerBoard) {
                        this.$refs.plannerBoard.style.setProperty('--planner-header-h', h + 'px');
                    }
                }
            }
        },
        scrollToFirstPending(smooth = false) {
            const board = this.$refs.plannerBoard;
            if (!board) return;

            const column = board.querySelector('.designer-subtasks-column');
            if (!column) return;

            const firstPending = column.querySelector('[data-subtask-pending=1]');
            const target = firstPending || column.querySelector('[data-is-today=1]');

            if (target) {
                const headerWrapper = document.getElementById('tour-planner-sticky-wrapper');
                const headerH = headerWrapper ? headerWrapper.offsetHeight : (this.headerHeight || 148);
                const dayHeaderH = 34;
                const targetRect = target.getBoundingClientRect();
                const boardRect = board.getBoundingClientRect();

                const targetScrollTop = board.scrollTop + (targetRect.top - boardRect.top) - headerH - dayHeaderH - 12;

                board.scrollTo({
                    top: Math.max(0, targetScrollTop),
                    behavior: smooth ? 'smooth' : 'auto'
                });
            }
        },
        initDragAutoScroll() {
            let scrollInterval = null;
            let currentX = 0;
            let currentY = 0;
            let isDragging = false;

            const EDGE_THRESHOLD = 90;
            const MAX_SPEED = 24;

            const handleDragOver = (e) => {
                if (e.clientX === 0 && e.clientY === 0) return;
                currentX = e.clientX;
                currentY = e.clientY;
                isDragging = true;

                if (!scrollInterval) {
                    scrollInterval = requestAnimationFrame(scrollLoop);
                }
            };

            const handleDragEnd = () => {
                isDragging = false;
                this.clearDragState();
                if (scrollInterval) {
                    cancelAnimationFrame(scrollInterval);
                    scrollInterval = null;
                }
            };

            const scrollLoop = () => {
                if (!isDragging) {
                    scrollInterval = null;
                    return;
                }

                const vw = window.innerWidth;
                const vh = window.innerHeight;

                // 1. Window vertical scrolling
                if (currentY < EDGE_THRESHOLD && window.scrollY > 0) {
                    const intensity = (EDGE_THRESHOLD - Math.max(0, currentY)) / EDGE_THRESHOLD;
                    window.scrollBy(0, -Math.ceil(intensity * MAX_SPEED));
                } else if (currentY > vh - EDGE_THRESHOLD) {
                    const intensity = (Math.min(vh, currentY) - (vh - EDGE_THRESHOLD)) / EDGE_THRESHOLD;
                    window.scrollBy(0, Math.ceil(intensity * MAX_SPEED));
                }

                // 2. Window horizontal scrolling
                if (currentX < EDGE_THRESHOLD && window.scrollX > 0) {
                    const intensity = (EDGE_THRESHOLD - Math.max(0, currentX)) / EDGE_THRESHOLD;
                    window.scrollBy(-Math.ceil(intensity * MAX_SPEED), 0);
                } else if (currentX > vw - EDGE_THRESHOLD) {
                    const intensity = (Math.min(vw, currentX) - (vw - EDGE_THRESHOLD)) / EDGE_THRESHOLD;
                    window.scrollBy(Math.ceil(intensity * MAX_SPEED), 0);
                }

                // 3. Overflow scroll containers
                const containers = document.querySelectorAll('.overflow-x-auto, .overflow-y-auto, .custom-vertical-scrollbar, .custom-horizontal-scrollbar');
                containers.forEach(container => {
                    const rect = container.getBoundingClientRect();
                    const isWithinY = currentY >= rect.top - 30 && currentY <= rect.bottom + 30;
                    const isWithinX = currentX >= rect.left - 30 && currentX <= rect.right + 30;

                    if (isWithinY && isWithinX) {
                        // Container horizontal scroll
                        if (container.scrollWidth > container.clientWidth) {
                            if (currentX > rect.right - EDGE_THRESHOLD) {
                                const intensity = Math.min(1, (currentX - (rect.right - EDGE_THRESHOLD)) / EDGE_THRESHOLD);
                                container.scrollLeft += Math.ceil(intensity * MAX_SPEED);
                            } else if (currentX < rect.left + EDGE_THRESHOLD) {
                                const intensity = Math.min(1, ((rect.left + EDGE_THRESHOLD) - currentX) / EDGE_THRESHOLD);
                                container.scrollLeft -= Math.ceil(intensity * MAX_SPEED);
                            }
                        }

                        // Container vertical scroll
                        if (container.scrollHeight > container.clientHeight) {
                            if (currentY > rect.bottom - EDGE_THRESHOLD) {
                                const intensity = Math.min(1, (currentY - (rect.bottom - EDGE_THRESHOLD)) / EDGE_THRESHOLD);
                                container.scrollTop += Math.ceil(intensity * MAX_SPEED);
                            } else if (currentY < rect.top + EDGE_THRESHOLD) {
                                const intensity = Math.min(1, ((rect.top + EDGE_THRESHOLD) - currentY) / EDGE_THRESHOLD);
                                container.scrollTop -= Math.ceil(intensity * MAX_SPEED);
                            }
                        }
                    }
                });

                scrollInterval = requestAnimationFrame(scrollLoop);
            };

            window.addEventListener('dragover', handleDragOver, { passive: true });
            window.addEventListener('dragend', handleDragEnd, { passive: true });
            window.addEventListener('drop', handleDragEnd, { passive: true });
        },
        selectedTaskIds: [],
        isSelecting: false,
        selectionStart: { x: 0, y: 0 },
        selectionBox: { left: 0, top: 0, width: 0, height: 0 },
        toggleTaskSelection(id) {
            id = parseInt(id, 10);
            if (this.selectedTaskIds.includes(id)) {
                this.selectedTaskIds = this.selectedTaskIds.filter(x => x !== id);
            } else {
                this.selectedTaskIds.push(id);
            }
        },
        clearSelection() {
            this.selectedTaskIds = [];
        },
        initMarqueeSelection() {
            const board = this.$refs.plannerBoard;
            if (!board) return;

            const onMouseDown = (e) => {
                if (e.button !== 0) return;

                if (e.target.closest('input, textarea, button, select, a, [role=dialog], .no-marquee')) {
                    return;
                }

                const clickedCard = e.target.closest('[data-subtask-id]');
                if (clickedCard && !e.shiftKey && !e.metaKey && !e.ctrlKey) {
                    return;
                }

                if (clickedCard && (e.shiftKey || e.metaKey || e.ctrlKey)) {
                    return;
                }

                this.isSelecting = true;
                this.selectionStart = { x: e.clientX, y: e.clientY };
                this.selectionBox = { left: e.clientX, top: e.clientY, width: 0, height: 0 };

                if (!e.shiftKey && !e.metaKey && !e.ctrlKey) {
                    this.selectedTaskIds = [];
                }
            };

            const onMouseMove = (e) => {
                if (!this.isSelecting) return;

                const currentX = e.clientX;
                const currentY = e.clientY;

                const left = Math.min(this.selectionStart.x, currentX);
                const top = Math.min(this.selectionStart.y, currentY);
                const width = Math.abs(currentX - this.selectionStart.x);
                const height = Math.abs(currentY - this.selectionStart.y);

                this.selectionBox = { left, top, width, height };

                if (width > 6 || height > 6) {
                    window.getSelection()?.removeAllRanges();

                    const boxRect = {
                        left: left,
                        right: left + width,
                        top: top,
                        bottom: top + height
                    };

                    const cards = board.querySelectorAll('[data-subtask-id]');
                    const hitIds = new Set(e.shiftKey || e.metaKey || e.ctrlKey ? this.selectedTaskIds : []);

                    cards.forEach(card => {
                        const r = card.getBoundingClientRect();
                        const intersects = !(r.right < boxRect.left || r.left > boxRect.right || r.bottom < boxRect.top || r.top > boxRect.bottom);
                        const id = parseInt(card.getAttribute('data-subtask-id'), 10);
                        if (intersects && !isNaN(id)) {
                            hitIds.add(id);
                        }
                    });

                    this.selectedTaskIds = Array.from(hitIds);
                }
            };

            const onMouseUp = () => {
                if (this.isSelecting) {
                    this.isSelecting = false;
                    this.selectionBox = { left: 0, top: 0, width: 0, height: 0 };
                }
            };

            board.addEventListener('mousedown', onMouseDown);
            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        }
    });
</script>
@endassets

@assets
<script>
    window.plannerDayCell = (date, designerId) => ({
        date,
        designerId,
        creatorMounted: false,
        mountCreator() {
            if (this.creatorMounted) return;
            const template = document.getElementById('planner-inline-creator-template');
            if (!template || !this.$refs.creatorHost) return;
            const node = template.content.firstElementChild.cloneNode(true);
            this.$refs.creatorHost.appendChild(node);
            window.Alpine.initTree(node);
            this.creatorMounted = true;
        },
                draggingOver: false,
                inlineActive: false,
                orderSearch: '',
                selectedOrderId: '',
                selectedOrderCompany: '',
                selectedOrderTask: '',
                selectedOrderLocation: '',
                subtaskTitle: '',
                isWorkTask: true,
                dropdownOpen: false,
                presetDropdownOpen: false,
                presetHighlightedIndex: -1,
                orderHighlightedIndex: -1,
                titleFocused: false,
                getFilteredOrders() {
                    if (typeof window.filterWorkspaceOrders === 'function') {
                        return window.filterWorkspaceOrders(this.workspaceOrdersList, this.orderSearch);
                    }
                    return this.filterWorkspaceOrders ? this.filterWorkspaceOrders(this.orderSearch) : (this.workspaceOrdersList || []);
                },
                navigateOrder(step) {
                    const list = this.getFilteredOrders();
                    if (list.length === 0) return;
                    if (this.orderHighlightedIndex === -1) {
                        this.orderHighlightedIndex = step > 0 ? 0 : list.length - 1;
                    } else {
                        this.orderHighlightedIndex = (this.orderHighlightedIndex + step + list.length) % list.length;
                    }
                    this.$nextTick(() => {
                        const container = this.$refs.orderDropdownPanel;
                        if (!container) return;
                        const items = container.querySelectorAll('.order-item-btn');
                        if (items[this.orderHighlightedIndex]) {
                            items[this.orderHighlightedIndex].scrollIntoView({ block: 'nearest' });
                        }
                    });
                },
                selectHighlightedOrder() {
                    const list = this.getFilteredOrders();
                    if (this.orderHighlightedIndex >= 0 && list[this.orderHighlightedIndex]) {
                        this.selectOrder(list[this.orderHighlightedIndex]);
                    }
                },
                handleOrderEnter() {
                    const list = this.getFilteredOrders();
                    if (this.dropdownOpen && this.orderHighlightedIndex >= 0 && list[this.orderHighlightedIndex]) {
                        this.selectHighlightedOrder();
                    } else if (this.orderSearch.trim()) {
                        this.$wire.scheduleSubtask(null, this.orderSearch.trim(), this.date, this.designerId, this.isWorkTask);
                        this.resetInline();
                    }
                },
                getFilteredPresets() {
                    const list = this.subtaskPresetsList || [];
                    if (!this.subtaskTitle) return list;
                    const q = (this.subtaskTitle || '').toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    return list.filter(p => {
                        const titleNorm = (p.title || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                        return titleNorm.includes(q);
                    });
                },
                selectPreset(preset) {
                    this.subtaskTitle = preset.title;
                    if (preset.is_work_task !== undefined && preset.is_work_task !== null) {
                        this.isWorkTask = Boolean(preset.is_work_task);
                    }
                    this.presetDropdownOpen = false;
                    this.presetHighlightedIndex = -1;
                    this.$nextTick(() => {
                        if (this.$refs.titleInput) this.$refs.titleInput.focus();
                    });
                },
                navigatePreset(step) {
                    const list = this.getFilteredPresets();
                    if (list.length === 0) return;
                    if (this.presetHighlightedIndex === -1) {
                        this.presetHighlightedIndex = step > 0 ? 0 : list.length - 1;
                    } else {
                        this.presetHighlightedIndex = (this.presetHighlightedIndex + step + list.length) % list.length;
                    }
                    this.$nextTick(() => {
                        const container = this.$refs.presetDropdownPanel;
                        if (!container) return;
                        const items = container.querySelectorAll('.preset-item-btn');
                        if (items[this.presetHighlightedIndex]) {
                            items[this.presetHighlightedIndex].scrollIntoView({ block: 'nearest' });
                        }
                    });
                },
                openInline() {
                    this.mountCreator();
                    this.ensureWorkspaceOrders();
                    this.inlineActive = true;
                    this.isWorkTask = true;
                    this.orderHighlightedIndex = -1;
                    this.$nextTick(() => {
                        if (this.$refs.orderSearchInput) this.$refs.orderSearchInput.focus();
                    });
                },
                selectOrder(order) {
                    this.selectedOrderId = String(order.id);
                    this.selectedOrderCompany = order.company;
                    this.selectedOrderTask = order.task || '';
                    this.selectedOrderLocation = order.location || '';
                    this.orderSearch = order.text;
                    this.dropdownOpen = false;
                    this.orderHighlightedIndex = -1;
                    this.presetDropdownOpen = true;
                    this.presetHighlightedIndex = -1;
                    this.$nextTick(() => {
                        if (this.$refs.titleInput) this.$refs.titleInput.focus();
                    });
                },
                submitSubtask() {
                    if (!this.selectedOrderId) {
                        if (this.orderSearch.trim()) {
                            this.$wire.scheduleSubtask(null, this.orderSearch.trim(), this.date, this.designerId, this.isWorkTask);
                            this.resetInline();
                            return;
                        }
                        alert(@js(__('Por favor ingresa el nombre de la subtarea o nota.')));
                        return;
                    }
                    if (!this.subtaskTitle.trim()) {
                        alert(@js(__('Por favor ingresa el nombre de la subtarea.')));
                        return;
                    }
                    this.$wire.scheduleSubtask(this.selectedOrderId, this.subtaskTitle.trim(), this.date, this.designerId, this.isWorkTask);
                    this.resetInline();
                },
                resetInline() {
                    this.orderSearch = '';
                    this.selectedOrderId = '';
                    this.selectedOrderCompany = '';
                    this.selectedOrderTask = '';
                    this.selectedOrderLocation = '';
                    this.subtaskTitle = '';
                    this.isWorkTask = true;
                    this.dropdownOpen = false;
                    this.orderHighlightedIndex = -1;
                    this.presetDropdownOpen = false;
                    this.presetHighlightedIndex = -1;
                    this.$nextTick(() => {
                        if (this.$refs.orderSearchInput) this.$refs.orderSearchInput.focus();
                    });
                },
                cancelInline() {
                    this.inlineActive = false;
                    this.orderSearch = '';
                    this.selectedOrderId = '';
                    this.selectedOrderCompany = '';
                    this.selectedOrderTask = '';
                    this.selectedOrderLocation = '';
                    this.subtaskTitle = '';
                    this.isWorkTask = true;
                    this.dropdownOpen = false;
                    this.orderHighlightedIndex = -1;
                    this.presetDropdownOpen = false;
                    this.presetHighlightedIndex = -1;
                }
    });
</script>
@endassets

@php
    ob_start();
    $presetDataList = $subtaskPresets->map(function($p) {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'badge_style' => $p->badgeStyle(),
            'category' => $p->category?->value ?? 'new_design',
            'is_work_task' => (bool) $p->is_work_task,
        ];
    })->values()->toArray();
    $taskMenuDays = collect($weekDaysOnly)->map(fn ($d) => [
        'date' => $d['date_string'],
        'label' => ucfirst($d['date']->translatedFormat('l j M')),
    ])->push(['date' => $nextWeekItem['date_string'], 'label' => __('Próxima Semana')])->values()->all();
    $taskMenuCategories = collect(\App\Enums\SubtaskCategory::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->all();
@endphp
<div 
    wire:poll.visible.30s="pollPlanner"
    @open-subtask-modal.window="openCreateSubtaskModal($event.detail.orderId || '', $event.detail.dateStr || '', $event.detail.designerId || '')"
    @open-link-note-modal.window="openLinkNoteModal($event.detail.taskId || null, $event.detail.noteTitle || '')"
    @dragend.window="clearDragState()"
    @drop.window="clearDragState()"
    x-data="plannerBoard(@js($presetDataList))" 
    x-init="initDragAutoScroll(); initMarqueeSelection(); updateHeaderHeight(); $nextTick(() => updateHeaderHeight()); window.addEventListener('resize', () => updateHeaderHeight(), { passive: true }); if (document.fonts && document.fonts.ready) { document.fonts.ready.then(() => updateHeaderHeight()); } if (window.Livewire) { Livewire.hook('morph.updated', () => updateHeaderHeight()); } setTimeout(() => scrollToFirstPending(false), 120);"
    @planner-filter-changed.window="$nextTick(() => { updateHeaderHeight(); setTimeout(() => scrollToFirstPending(true), 60); })"
    @subtask-deleted.window="triggerUndoToast($event.detail.message)"
    @subtask-restored.window="hideUndoToast()"
    @action-recorded.window="triggerUndoToast($event.detail.message)"
    @keydown.window="(e) => {
        if (e.key === 'Escape') {
            clearSelection();
        }
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            const active = document.activeElement;
            const isInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
            const hasModal = document.querySelector('[role=dialog]');
            if (!isInput && !hasModal && ($wire.recentlyDeletedSubtaskIds.length > 0 || $wire.actionHistory.length > 0)) {
                e.preventDefault();
                $wire.undoLastAction();
            }
        }
    }"
    x-ref="plannerBoard"
    style="--planner-header-h: 148px;"
    :style="{ '--planner-header-h': headerHeight + 'px' }"
    class="h-full flex flex-col space-y-4 text-xs font-sans bg-canvas min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-4 text-zinc-800">

    <!-- Static chrome (modals, toasts, bulk bar, inline creator) is fetched once after load so it never travels with grid updates -->
    <div x-ref="chromeHost" wire:ignore x-init="loadChrome()"></div>


    <div x-ref="taskMenuData" hidden data-days="{{ json_encode($taskMenuDays) }}" data-categories="{{ json_encode($taskMenuCategories) }}"></div>

    <!-- One shared "⋯" menu for every planner card -->
    <div
        x-show="taskMenu.open"
        x-cloak
        x-ref="taskMenu"
        style="display: none;"
        :style="`top: ${taskMenu.top}px; left: ${taskMenu.left}px`"
        @click.outside="closeTaskMenu()"
        @keydown.escape.window="closeTaskMenu()"
        @scroll.window.capture="closeTaskMenu()"
        data-planner-shared-menu
        class="fixed z-[60] w-56 p-1 rounded-lg border border-line bg-surface shadow-menu text-body text-ink">
        <button type="button" @click="taskMenu.section = taskMenu.section === 'move' ? '' : 'move'" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
            <x-lucide-arrow-left-right class="w-4 h-4 text-ink-3" />
            <span class="flex-1">{{ __('Mover a...') }}</span>
            <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition" ::class="taskMenu.section === 'move' ? 'rotate-180' : ''" />
        </button>
        <div x-show="taskMenu.section === 'move'" class="pl-6 pb-1">
            <template x-for="d in taskMenuDays()" :key="d.date">
                <button type="button" @click="moveTaskTo(d.date)" x-text="d.label" class="block w-full rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer"></button>
            </template>
        </div>

        <template x-if="taskMenu.hasOrder">
            <div>
                <button type="button" @click="taskMenu.section = taskMenu.section === 'category' ? '' : 'category'" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
                    <x-lucide-tag class="w-4 h-4 text-ink-3" />
                    <span class="flex-1">{{ __('Al completar enviar a...') }}</span>
                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition" ::class="taskMenu.section === 'category' ? 'rotate-180' : ''" />
                </button>
                <div x-show="taskMenu.section === 'category'" class="pl-6 pb-1">
                    <template x-for="c in taskMenuCategories()" :key="c.value">
                        <button type="button" @click="setTaskCategory(c.value)" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
                            <span class="flex-1" x-text="c.label"></span>
                            <x-lucide-check x-show="taskMenu.category === c.value" class="w-3.5 h-3.5" />
                        </button>
                    </template>
                </div>

                <button type="button" @click="taskMenu.section = taskMenu.section === 'type' ? '' : 'type'" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
                    <x-lucide-wrench class="w-4 h-4 text-ink-3" />
                    <span class="flex-1">{{ __('Tipo de Subtarea') }}</span>
                    <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition" ::class="taskMenu.section === 'type' ? 'rotate-180' : ''" />
                </button>
                <div x-show="taskMenu.section === 'type'" class="pl-6 pb-1">
                    <button type="button" @click="setTaskType(true)" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
                        <span class="flex-1">{{ __('Trabajo') }}</span>
                        <x-lucide-check x-show="taskMenu.work !== '0'" class="w-3.5 h-3.5" />
                    </button>
                    <button type="button" @click="setTaskType(false)" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left hover:bg-sunken cursor-pointer">
                        <span class="flex-1">{{ __('Gestión') }}</span>
                        <x-lucide-check x-show="taskMenu.work === '0'" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </template>

        <div class="my-1 h-px bg-line"></div>
        <button type="button" @click="removeTask()" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-left text-danger hover:bg-sunken cursor-pointer">
            <x-lucide-trash-2 class="w-4 h-4" />
            <span>{{ __('Eliminar subtarea') }}</span>
        </button>
    </div>


    <!-- Top Sticky / Dynamic Toolbar Header -->
    <div 
        id="tour-planner-sticky-wrapper"
        x-init="
            $nextTick(() => updateHeaderHeight());
            if (window.ResizeObserver) {
                new ResizeObserver(() => updateHeaderHeight()).observe($el);
            }
        "
        class="sticky top-0 z-20 bg-canvas pt-0.5 pb-2">
        <div id="tour-planner-header" class="space-y-3 border-b border-line pb-3">
            @php
                $startVal = Carbon\Carbon::parse($selectedWeekStart);
                $endVal = $startVal->copy()->addDays(4);
                $currentMonday = now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                $isThisWeek = $startVal->toDateString() === $currentMonday;
                $nextWeekMonday = now()->addWeek()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                $isNextWeek = $startVal->toDateString() === $nextWeekMonday;
                $segmentBase = 'px-2.5 h-7 rounded-control text-body transition cursor-pointer flex items-center gap-1.5 shrink-0';
                $segmentOn = 'bg-surface text-ink font-medium border border-line';
                $segmentOff = 'text-ink-2 hover:text-ink border border-transparent';
            @endphp

            <x-ui.page-header :title="__('Planificación Semanal')">
                <x-slot:actions>
                    @if(isset($slaBreachedList) && $slaBreachedList->isNotEmpty())
                        <button
                            wire:click="openAllSlaWarningsModal"
                            type="button"
                            class="cursor-pointer"
                            title="{{ __('Ver tareas que superan SLA') }}">
                            <x-ui.tag tone="warn" icon="triangle-alert">{{ trans_choice('{1} :count SLA alert|[2,*] :count SLA alerts', $slaBreachedList->count()) }}</x-ui.tag>
                        </button>
                    @endif
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <div class="flex items-center gap-1">
                        <flux:button wire:click="previousWeek" size="sm" variant="ghost" icon="chevron-left" aria-label="{{ __('Semana anterior') }}" :tooltip="__('Semana anterior')" />
                        <span class="px-1 text-body font-medium text-ink tabular-nums select-none">{{ $startVal->format('d M') }} - {{ $endVal->format('d M') }}</span>
                        <flux:button wire:click="nextWeek" size="sm" variant="ghost" icon="chevron-right" aria-label="{{ __('Semana siguiente') }}" :tooltip="__('Semana siguiente')" />
                    </div>

                    <flux:button wire:click="thisWeek" size="sm" variant="{{ $isThisWeek ? 'filled' : 'outline' }}">{{ __('Esta semana') }}</flux:button>

                    <!-- Calendar popover (single picker) -->
                    <div class="relative" @click.outside="calendarOpen = false">
                        <flux:button @click="calendarOpen = !calendarOpen" size="sm" variant="outline" icon="calendar" aria-label="{{ __('Calendario') }}">{{ __('Calendario') }}</flux:button>

                        <div
                            x-show="calendarOpen"
                            x-cloak
                            class="absolute right-0 top-full mt-2 w-72 z-50 bg-surface border border-line rounded-card shadow-menu p-3 text-body space-y-2"
                            style="display: none;">
                            <div class="flex items-center justify-between pb-2 border-b border-line">
                                <flux:button wire:click="previousMonth" size="xs" variant="ghost" icon="chevron-left" aria-label="{{ __('Mes anterior') }}" />
                                <span class="font-medium text-ink capitalize">{{ Carbon\Carbon::parse($viewMonth . '-01')->translatedFormat('F Y') }}</span>
                                <flux:button wire:click="nextMonth" size="xs" variant="ghost" icon="chevron-right" aria-label="{{ __('Mes siguiente') }}" />
                            </div>

                            <div class="grid grid-cols-7 text-center text-meta text-ink-3">
                                <span>{{ __('Lun') }}</span><span>{{ __('Mar') }}</span><span>{{ __('Mié') }}</span><span>{{ __('Jue') }}</span><span>{{ __('Vie') }}</span><span>{{ __('Sáb') }}</span><span>{{ __('Dom') }}</span>
                            </div>

                            <div class="grid grid-cols-7 gap-1 text-center">
                                @foreach($this->miniCalendarDays as $calDay)
                                    <button
                                        type="button"
                                        wire:key="mini-cal-day-{{ $calDay['date_string'] }}"
                                        wire:click="selectWeekFromDate('{{ $calDay['date_string'] }}')"
                                        @click="calendarOpen = false"
                                        class="p-1.5 rounded-control text-body transition cursor-pointer
                                            {{ $calDay['is_selected_week'] ? 'bg-ink text-white font-medium' : ($calDay['is_current_month'] ? 'text-ink hover:bg-sunken' : 'text-ink-3 hover:bg-sunken') }}
                                            {{ $calDay['is_today'] && !$calDay['is_selected_week'] ? 'border border-ink font-medium' : '' }}">
                                        {{ $calDay['day_number'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                </x-slot:actions>

                <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Vista') }}">
                    <button type="button" wire:click="changeViewMode('by_day')" aria-pressed="{{ $viewMode === 'by_day' ? 'true' : 'false' }}" class="ui-chip">{{ __('Por Días') }}</button>
                    <button type="button" wire:click="changeViewMode('by_designer')" aria-pressed="{{ $viewMode === 'by_designer' ? 'true' : 'false' }}" class="ui-chip">{{ __('Por Diseñador') }}</button>
                </div>

                <span class="h-4 w-px bg-line" aria-hidden="true"></span>

                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Orden:') }}">
                    <button type="button" wire:click="changePlannerSortBy('custom')" aria-pressed="{{ $plannerSortBy === 'custom' ? 'true' : 'false' }}" class="ui-chip" title="{{ __('Manual / Arrastrar') }}">{{ __('Manual') }}</button>
                    <button type="button" wire:click="changePlannerSortBy('priority')" aria-pressed="{{ $plannerSortBy === 'priority' ? 'true' : 'false' }}" class="ui-chip" title="{{ __('Ordenar por Prioridad') }}">{{ __('Prioridad') }}</button>
                </div>

                <span class="h-4 w-px bg-line" aria-hidden="true"></span>

                <div class="flex flex-wrap items-center gap-2 min-w-0" role="group" aria-label="{{ __('Diseñador') }}">
                    <button type="button" wire:click="changeDesignerFilter('all')" aria-pressed="{{ $selectedDesignerFilter === 'all' ? 'true' : 'false' }}" class="ui-chip">{{ __('Todos los diseñadores') }}</button>
                    @foreach($allDesigners as $des)
                        <button type="button" wire:key="planner-designer-tab-{{ $des->id }}" wire:click="changeDesignerFilter('{{ $des->id }}')" aria-pressed="{{ $selectedDesignerFilter == $des->id ? 'true' : 'false' }}" class="ui-chip">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $des->hex_color ?? '#a8a29e' }}"></span>
                            {{ $des->name }}
                        </button>
                    @endforeach
                </div>

                <!-- Right: Search Inputs (Workspace & Backlog Compact) -->
                <div class="flex items-center gap-2 shrink-0 w-full lg:w-auto lg:ml-auto">
                    <!-- Workspace Search -->
                    <div class="relative flex-1 lg:w-52" x-data="{ open: true }" @click.outside="open = false">
                        <div class="relative w-full">
                            <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-2.5 top-1/2 -translate-y-1/2" />
                            <input 
                                type="text" 
                                wire:model.live.debounce.150ms="unscheduledSearch"
                                @focus="open = true"
                                placeholder="{{ __('Buscar en Workspace...') }}" 
                                class="ui-input pl-8 pr-7" />
                            @if($unscheduledSearch)
                                <button 
                                    wire:click="$set('unscheduledSearch', '')" 
                                    type="button" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700">
                                    <x-lucide-x class="w-3 h-3" />
                                </button>
                            @endif
                        </div>

                        <!-- Workspace Search Results Popover -->
                        @if(!empty($unscheduledSearch))
                            <div 
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-0 top-full mt-1.5 w-80 sm:w-96 z-50 bg-white border border-line rounded-xl shadow-panel overflow-hidden divide-y divide-stone-100 text-xs"
                                style="display: none;">
                                
                                <div class="px-3 py-2 bg-stone-50 border-b border-stone-100 font-semibold text-meta  text-zinc-500 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-search class="w-3.5 h-3.5 text-zinc-500" />
                                        {{ __('Órdenes en Workspace') }} ({{ $workspaceSearchResults->count() }})
                                    </span>
                                    <span class="text-meta font-normal text-zinc-400">{{ __('Workspace Activo') }}</span>
                                </div>

                                <div class="max-h-72 overflow-y-auto custom-vertical-scrollbar divide-y divide-stone-100">
                                    @forelse($workspaceSearchResults as $wOrder)
                                        <div 
                                            wire:key="workspace-search-order-{{ $wOrder->id }}"
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $wOrder->id }} })"
                                            @click="open = false"
                                            class="p-2.5 hover:bg-stone-100 cursor-pointer flex items-center justify-between gap-2 transition group">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1 mb-0.5">
                                                    <span class="px-1.5 py-0.2 rounded text-meta font-semibold bg-stone-100 text-zinc-700 border border-stone-200 shrink-0">
                                                        Workspace
                                                    </span>
                                                    @if($wOrder->wo_number)
                                                        <x-wo-badge :number="$wOrder->wo_number" variant="outline" prefix="#" class="text-meta" />
                                                    @endif
                                                    @if($wOrder->designer)
                                                        <span class="text-meta text-zinc-500 font-medium truncate">• {{ $wOrder->designer->name }}</span>
                                                    @endif
                                                </div>
                                                <h4 class="font-semibold text-xs text-zinc-900 truncate group-hover:text-stone-900 transition ">{{ $wOrder->company_name }}</h4>
                                                <p class="text-meta text-zinc-500 truncate mt-0.2 ">{{ $wOrder->task_name }}</p>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-stone-700 transition" />
                                            </div>
                                        </div>
                                    @empty
                                        <div class="p-4 text-center text-zinc-400 italic text-meta">
                                            {{ __('No se encontraron órdenes activas para ":query".', ['query' => $unscheduledSearch]) }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Backlog Search -->
                    <div class="relative flex-1 lg:w-52" x-data="{ open: true }" @click.outside="open = false">
                        <div class="relative w-full">
                            <x-lucide-archive class="w-4 h-4 text-ink-3 absolute left-2.5 top-1/2 -translate-y-1/2" />
                            <input 
                                type="text" 
                                wire:model.live.debounce.150ms="backlogSearch"
                                @focus="open = true"
                                placeholder="{{ __('Buscar en Backlog...') }}" 
                                class="ui-input pl-8 pr-7" />
                            @if($backlogSearch)
                                <button 
                                    wire:click="$set('backlogSearch', '')" 
                                    type="button" 
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700">
                                    <x-lucide-x class="w-3 h-3" />
                                </button>
                            @endif
                        </div>

                        <!-- Backlog Search Results Popover -->
                        @if(!empty($backlogSearch))
                            <div 
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-0 top-full mt-1.5 w-80 sm:w-96 z-50 bg-white border border-line rounded-xl shadow-panel overflow-hidden divide-y divide-stone-100 text-xs"
                                style="display: none;">
                                
                                <div class="px-3 py-2 bg-stone-50 border-b border-stone-100 font-semibold text-meta  text-zinc-500 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-archive class="w-3.5 h-3.5 text-zinc-500" />
                                        {{ __('Órdenes en Backlog') }} ({{ $backlogOrders->count() }})
                                    </span>
                                    <span class="text-meta font-normal text-zinc-400">{{ __('Sin agendar') }}</span>
                                </div>

                                <div class="max-h-72 overflow-y-auto custom-vertical-scrollbar divide-y divide-stone-100">
                                    @forelse($backlogOrders as $bOrder)
                                        <div 
                                            wire:key="backlog-search-order-{{ $bOrder->id }}"
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $bOrder->id }} })"
                                            @click="open = false"
                                            class="p-2.5 hover:bg-stone-100 cursor-pointer flex items-center justify-between gap-2 transition group">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1 mb-0.5">
                                                    <span class="px-1.5 py-0.2 rounded text-meta font-semibold bg-sunken text-ink-2 border border-line shrink-0">
                                                        Backlog
                                                    </span>
                                                    @if($bOrder->wo_number)
                                                        <x-wo-badge :number="$bOrder->wo_number" variant="outline" prefix="#" class="text-meta" />
                                                    @endif
                                                    @if($bOrder->designer)
                                                        <span class="text-meta text-zinc-500 font-medium truncate">• {{ $bOrder->designer->name }}</span>
                                                    @endif
                                                </div>
                                                <h4 class="font-semibold text-xs text-zinc-900 truncate group-hover:text-stone-900 transition ">{{ $bOrder->company_name }}</h4>
                                                <p class="text-meta text-zinc-500 truncate mt-0.2 ">{{ $bOrder->task_name }}</p>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <x-lucide-panel-right class="w-4 h-4 text-zinc-400 group-hover:text-stone-700 transition" />
                                            </div>
                                        </div>
                                    @empty
                                        <div class="p-4 text-center text-zinc-400 italic text-meta">
                                            {{ __('No se encontraron órdenes en backlog para ":query".', ['query' => $backlogSearch]) }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                </x-slot:toolbar>
            </x-ui.page-header>

        </div>
    </div>

    <!-- MAIN GRID CONTAINER: TOGGLED BY VIEW MODE -->
    @if($viewMode === 'by_day')
        <!-- Weekly Grid by Day -->
        <div class="space-y-6 flex-1 min-h-0 w-full">
            @foreach($designers as $designer)
                @php
                    $designerSubtasks = $subtasks->filter(function ($st) use ($designer) {
                        if ($st->assignee_id) {
                            return (int) $st->assignee_id === (int) $designer->id;
                        }
                        if ($st->order) {
                            return (int) $st->order->designer_id === (int) $designer->id || $st->order->designers->contains('id', $designer->id);
                        }
                        return false;
                    });
                @endphp
                <div wire:key="planner-byday-designer-{{ $designer->id }}" class="space-y-2">
                    
                    <div class="flex items-center justify-between pb-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $designer->dot_color_class }}" style="{{ $designer->dot_inline_style }}"></span>
                            <h3 class="font-semibold text-base text-zinc-900">Diseñador/a: {{ $designer->name }}</h3>
                        </div>
                    </div>

                    <!-- 6 Columns Grid (Monday-Friday + Next Week) with horizontal overflow safety -->
                    <div class="overflow-x-auto custom-horizontal-scrollbar pb-1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 min-w-[700px] lg:min-w-full">
                            @foreach($days as $day)
                                @php
                                    $isNextWeek = $day['is_next_week'] ?? false;
                                    $isToday = !$isNextWeek && $day['date']->isToday();
                                    $isFirstDayOfWeek = !$isNextWeek && $loop->first;
                                    
                                    $daySubtasks = $isNextWeek
                                        ? $designerSubtasks->filter(fn($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                        : $designerSubtasks->filter(function ($st) use ($day, $isFirstDayOfWeek) {
                                            if ($st->scheduled_date?->toDateString() === $day['date_string']) {
                                                return true;
                                            }
                                            if ($isFirstDayOfWeek && $st->scheduled_date && $st->scheduled_date->lt(Carbon\Carbon::parse($day['date_string'])) && $st->status !== 'done') {
                                                if ($st->order && $st->order->isArchived() && $st->isSystemTask()) {
                                                    return false;
                                                }
                                                return true;
                                            }
                                            return false;
                                        });

                                    if (! $showSystemTasks) {
                                        $daySubtasks = $daySubtasks->filter(fn($st) => $st->isWorkTask());
                                    }

                                    $daySubtasks = $this->sortSubtaskCollection($daySubtasks);

                                    $dayColorClass = match($day['day_name'] ?? '') {
                                        'Lunes' => '',
                                        'Martes' => '',
                                        'Miércoles' => '',
                                        'Jueves' => '',
                                        'Viernes' => '',
                                        'Next Week' => '',
                                        default => 'bg-stone-50 text-stone-700 border-stone-200',
                                    };
                                @endphp
                                <div 
                                    data-day-container
                                    data-day-date="{{ $day['date_string'] }}"
                                    data-designer-id="{{ $designer->id }}"
                                    wire:key="planner-byday-col-{{ $designer->id }}-{{ $day['date_string'] }}"
                                    x-data="{ 
                                        draggingOver: false,
                                        dragOverCardId: null,
                                        dragPosition: 'above'
                                    }"
                                    @dragover.prevent="draggingOver = true; $event.dataTransfer.dropEffect = 'move'"
                                    @dragenter.prevent="draggingOver = true"
                                    @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                                    @drop.prevent="draggingOver = false; handleDayDrop($event, '{{ $day['date_string'] }}', {{ $designer->id }})"
                                    :class="{ 'border-stone-400 bg-stone-100': draggingOver }"
                                    class="bg-sunken border border-line rounded-card flex flex-col min-h-[140px] overflow-hidden transition">
                                    
                                    <!-- Kanban Column Header -->
                                    <div class="p-2.5 bg-sunken border-b border-line rounded-t-card flex items-center justify-between sticky top-0 z-10 shrink-0">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="text-body font-medium text-ink">
                                                {{ $day['day_name'] }}
                                            </span>
                                            @if($isToday)
                                                <span class="px-1 py-0.2 rounded bg-ink text-white text-meta font-semibold ">
                                                    HOY
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0 ml-1">
                                            <span class="text-meta font-mono font-medium {{ $isToday ? 'text-ink-2 font-semibold' : 'text-zinc-400' }}">
                                                {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                            </span>
                                            <span class="px-1.5 py-0.5 rounded bg-white text-meta font-mono text-zinc-600 border border-stone-200 font-semibold shrink-0">
                                                {{ $daySubtasks->count() }}
                                            </span>
                                            <button 
                                                type="button"
                                                @click.stop="$dispatch('open-subtask-modal', { dateStr: '{{ $day['date_string'] }}', designerId: '{{ $designer->id }}' })" 
                                                class="p-0.5 rounded hover:bg-stone-200/80 text-zinc-500 hover:text-zinc-900 transition cursor-pointer"
                                                title="{{ __('Agregar subtarea a') }} {{ $day['day_name'] }}">
                                                <x-lucide-plus class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Kanban Cards Body -->
                                    <div class="p-2 overflow-y-auto flex-1 space-y-2 min-h-0">
                                        @if($daySubtasks->isEmpty())
                                            <div class="py-6 text-center border border-dashed border-stone-200/80 rounded-lg my-1 bg-stone-50/40">
                                                <span class="text-meta text-zinc-400 font-normal select-none">{{ __('Sin trabajo agendado') }}</span>
                                            </div>
                                        @else
                                            <div class="space-y-2">

                                                <!-- Day Subtask Kanban Cards -->
                                                @foreach($daySubtasks as $stask)
                                                    @php
                                                        $staskIsNote = ! $stask->order;
                                                        $staskDone = $stask->isDone();
                                                        $isUrgentTask = $stask->isUrgent();
                                                        $isOrderUrgent = $stask->isOrderUrgent();
                                                    @endphp
                                                    <div 
                                                        wire:key="planner-byday-stask-{{ $stask->id }}-{{ $stask->status }}-{{ $stask->updated_at?->timestamp ?? '' }}"
                                                        draggable="true"
                                                        data-subtask-id="{{ $stask->id }}"
                                                        @dragstart="cardDragStart($event)"
                                                        @dragover.prevent.stop="cardDragOver($event)"
                                                        @dragleave.stop="cardDragLeave($event)"
                                                        @drop.prevent.stop="cardDrop($event)"
                                                        @click.stop="
                                                            if ($event.metaKey || $event.ctrlKey || $event.shiftKey) {
                                                                $event.preventDefault();
                                                                toggleTaskSelection({{ $stask->id }});
                                                                return;
                                                            }
                                                            @if($staskIsNote)
                                                                $dispatch('open-link-note-modal', { taskId: {{ $stask->id }}, noteTitle: '{{ addslashes($stask->title) }}' });
                                                            @else
                                                                $dispatch('open-order-detail', { orderId: {{ $stask->order?->id ?? 0 }} });
                                                            @endif
                                                        "
                                                        :class="{ 
                                                            'bg-sunken': activeDragCardId === {{ $stask->id }},
                                                            'border-ink bg-sunken': selectedTaskIds.includes({{ $stask->id }})
                                                        }"
 :style="dragIndicatorStyle({{ $stask->id }})"
                                                        class="border rounded-card p-2.5 flex items-start justify-between gap-2 transition group relative select-none cursor-grab active:cursor-grabbing {{ $staskIsNote ? 'bg-sunken border-line cursor-pointer' : 'bg-surface border-line hover:border-line-strong' }}">
                                                        <x-orders.planner-card-body :task="$stask" :designer="$designer" :days="$weekDaysOnly" :next-week="$nextWeekItem" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Footer Action to Add Subtask from Column -->
                                        <div class="pt-1.5 mt-auto">
                                            <button 
                                                type="button"
                                                @click.stop="$dispatch('open-subtask-modal', { dateStr: '{{ $day['date_string'] }}', designerId: '{{ $designer->id }}' })"
                                                class="w-full py-1 px-1.5 rounded-lg border border-dashed border-stone-200 hover:border-stone-300 hover:bg-stone-100/80 text-zinc-400 hover:text-zinc-700 text-meta font-medium transition flex items-center justify-center gap-1 cursor-pointer select-none">
                                                <x-lucide-plus class="w-3 h-3" />
                                                <span>{{ __('Agregar subtarea') }}</span>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @else
        <!-- Weekly Grid by Designer (Columnas por Diseñador subdivididas por Días) -->
        @php
            $designerGridList = $designers->reject(fn($d) => str_contains(mb_strtolower($d->name), 'externo'));
            $colCount = max(1, $designerGridList->count());
            $isSingleDesignerFiltered = ($selectedDesignerFilter !== 'all' || $colCount === 1);
            $gridColsClass = $isSingleDesignerFiltered
                ? 'grid-cols-1 lg:grid-cols-3'
                : match($colCount) {
                    1 => 'grid-cols-1',
                    2 => 'grid-cols-1 md:grid-cols-2',
                    3 => 'grid-cols-1 md:grid-cols-3',
                    4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                    default => 'grid-cols-1 md:grid-cols-3 lg:grid-cols-5',
                };
        @endphp
        <div class="{{ $isSingleDesignerFiltered ? '' : 'overflow-x-auto custom-horizontal-scrollbar' }} pb-2 w-full min-h-0 flex-1">
            <div class="grid {{ $gridColsClass }} {{ $isSingleDesignerFiltered ? 'gap-6' : 'gap-6 divide-x divide-stone-200/60' }} items-start w-full">
                @foreach($designerGridList as $designer)
                    @php
                        $designerSubtasks = $subtasks->filter(function ($st) use ($designer) {
                            if ($st->assignee_id) {
                                return (int) $st->assignee_id === (int) $designer->id;
                            }
                            if ($st->order) {
                                return (int) $st->order->designer_id === (int) $designer->id || $st->order->designers->contains('id', $designer->id);
                            }
                            return false;
                        });
                        $designerTotalCount = $designerSubtasks->count();
                        $visibleDesignerSubtasks = $showSystemTasks ? $designerSubtasks : $designerSubtasks->filter(fn($st) => $st->isWorkTask());
                        $designerVisibleCount = $visibleDesignerSubtasks->count();
                    @endphp
                    <div wire:key="planner-grid-designer-{{ $designer->id }}" class="flex flex-col min-w-0 {{ $isSingleDesignerFiltered ? 'lg:col-span-2 designer-subtasks-column' : 'pr-4' }} space-y-3">
                        
                        <!-- Designer Header -->
                        <div class="flex items-center justify-between border-b border-stone-200/60 pb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $designer->dot_color_class }}" style="{{ $designer->dot_inline_style }}"></span>
                                <h3 class="font-semibold text-base text-zinc-900 truncate" title="{{ $designer->name }}">{{ $designer->name }}</h3>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="px-2 py-0.5 rounded-full bg-stone-100 text-zinc-600 font-mono text-meta font-semibold" title="{{ __('Subtareas semanales') }}">
                                    @if(!$showSystemTasks && $designerVisibleCount !== $designerTotalCount)
                                        {{ $designerVisibleCount }} ({{ $designerTotalCount }})
                                    @else
                                        {{ $designerVisibleCount }}
                                    @endif
                                </span>
                                <button 
                                    type="button"
                                    @click.stop="$dispatch('open-subtask-modal', { designerId: '{{ $designer->id }}' })" 
                                    class="p-1 rounded hover:bg-stone-100 text-zinc-500 hover:text-zinc-900 transition cursor-pointer"
                                    title="{{ __('Agregar subtarea a') }} {{ $designer->name }}">
                                    <x-lucide-plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Day Subdivisions Inside Designer Column -->
                        <div class="space-y-3">
                            @foreach($days as $day)
                                @php
                                    $isNextWeek = $day['is_next_week'] ?? false;
                                    $isToday = !$isNextWeek && $day['date']->isToday();
                                    $isFirstDayOfWeek = !$isNextWeek && $loop->first;

                                    $daySubtasks = $isNextWeek
                                        ? $designerSubtasks->filter(fn($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon\Carbon::parse($day['date_string'])))
                                        : $designerSubtasks->filter(function ($st) use ($day, $isFirstDayOfWeek) {
                                            if ($st->scheduled_date?->toDateString() === $day['date_string']) {
                                                return true;
                                            }
                                            if ($isFirstDayOfWeek && $st->scheduled_date && $st->scheduled_date->lt(Carbon\Carbon::parse($day['date_string'])) && $st->status !== 'done') {
                                                if ($st->order && $st->order->isArchived() && $st->isSystemTask()) {
                                                    return false;
                                                }
                                                return true;
                                            }
                                            return false;
                                        });
                                    if (! $showSystemTasks) {
                                        $daySubtasks = $daySubtasks->filter(fn($st) => $st->isWorkTask());
                                    }

                                    $daySubtasks = $this->sortSubtaskCollection($daySubtasks);

                                    $dayColorClass = match($day['day_name'] ?? '') {
                                        'Lunes', 'Monday' => '',
                                        'Martes', 'Tuesday' => '',
                                        'Miércoles', 'Wednesday' => '',
                                        'Jueves', 'Thursday' => '',
                                        'Viernes', 'Friday' => '',
                                        'Próxima Semana', 'Next Week' => '',
                                        default => 'bg-stone-50 text-stone-700 border-stone-200',
                                    };
                                @endphp

                                <div 
                                    data-day-container
                                    wire:key="planner-grid-day-{{ $designer->id }}-{{ $day['date_string'] }}"
                                    data-day-date="{{ $day['date_string'] }}"
                                    data-designer-id="{{ $designer->id }}"
                                    data-is-today="{{ $isToday ? '1' : '0' }}"
                                    x-data="plannerDayCell('{{ $day['date_string'] }}', {{ $designer->id }})"
                                    @dragover.prevent="draggingOver = true; $event.dataTransfer.dropEffect = 'move'"
                                    @dragenter.prevent="draggingOver = true"
                                    @dragleave="if (!$el.contains($event.relatedTarget)) draggingOver = false"
                                    @drop.prevent="draggingOver = false; handleDayDrop($event, '{{ $day['date_string'] }}', {{ $designer->id }})"
                                    :class="{ 
                                        'bg-sunken rounded-md': draggingOver, 
                                        '{{ $isToday ? 'bg-sunken rounded-md px-1.5' : '' }}': !draggingOver 
                                    }"
                                    class="pt-3 pb-2 border-t border-stone-200/40 first:border-t-0 first:pt-0 space-y-1.5 min-h-[50px] flex flex-col justify-between min-w-0 transition-colors">
                                    
                                    <div class="min-w-0 space-y-1">
                                        <!-- Day Subdivision Header (Sticky in Single Designer Filtered View) -->
                                        <div 
                                            @if($isSingleDesignerFiltered)
                                                class="sticky z-10 -mx-1.5 px-2 py-1.5 bg-canvas/95 border-b border-stone-200/60 flex items-center justify-between transition-all"
                                                style="top: var(--planner-header-h, 148px);"
                                            @else
                                                class="flex items-center justify-between pb-0.5"
                                            @endif
                                        >
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="text-body font-medium text-ink">
                                                    {{ $day['day_name'] }}
                                                </span>
                                                @if($isToday)
                                                    <x-ui.tag tone="neutral">{{ __('Hoy') }}</x-ui.tag>
                                                @endif
                                                @if($isSingleDesignerFiltered && $daySubtasks->isNotEmpty())
                                                    @php
                                                        $dayPendingCount = $daySubtasks->filter(fn($st) => ! $st->isDone())->count();
                                                    @endphp
                                                    <span class="text-meta font-mono font-medium {{ $dayPendingCount === 0 ? 'text-ok' : 'text-stone-400' }}">
                                                        @if($dayPendingCount === 0)
                                                            <span class="flex items-center gap-0.5">
                                                                <x-lucide-check class="w-3 h-3 text-ok inline" />
                                                                <span>{{ __('al día') }}</span>
                                                            </span>
                                                        @else
                                                            {{ $dayPendingCount }} {{ __('pend.') }}
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-meta font-mono text-zinc-400">
                                                {{ $isNextWeek ? $day['range_label'] : $day['date']->format('d M') }}
                                            </span>
                                        </div>

                                        <!-- Subtasks List for this Day & Designer -->
                                        @if($daySubtasks->isNotEmpty())
                                            <div data-subtask-container class="space-y-0.5">
                                                @foreach($daySubtasks as $stask)
                                                    @php
                                                        $staskIsNote = ! $stask->order;
                                                        $staskDone = $stask->isDone();
                                                        $isUrgentTask = $stask->isUrgent();
                                                        $isOrderUrgent = $stask->isOrderUrgent();
                                                    @endphp
                                                    <div 
                                                         wire:key="planner-grid-stask-{{ $stask->id }}-{{ $stask->status }}-{{ $stask->updated_at?->timestamp ?? '' }}"
                                                         draggable="true"
                                                         data-subtask-id="{{ $stask->id }}"
                                                         data-subtask-pending="{{ $staskDone ? '0' : '1' }}"
                                                         @dragstart="cardDragStart($event)"
                                                         @dragover.prevent.stop="cardDragOver($event)"
                                                         @dragleave.stop="cardDragLeave($event)"
                                                         @drop.prevent.stop="cardDrop($event)"
                                                         @click.stop="
                                                             if ($event.metaKey || $event.ctrlKey || $event.shiftKey) {
                                                                 $event.preventDefault();
                                                                 toggleTaskSelection({{ $stask->id }});
                                                                 return;
                                                             }
                                                             @if($staskIsNote)
                                                                 $dispatch('open-link-note-modal', { taskId: {{ $stask->id }}, noteTitle: '{{ addslashes($stask->title) }}' });
                                                             @else
                                                                 $dispatch('open-order-detail', { orderId: {{ $stask->order?->id ?? 0 }} });
                                                             @endif
                                                         "
                                                         :class="{ 
                                                             'bg-sunken': activeDragCardId === {{ $stask->id }}, 
                                                             'border-ink bg-sunken': selectedTaskIds.includes({{ $stask->id }})
                                                         }"
 :style="dragIndicatorStyle({{ $stask->id }})"
                                                         class="relative py-2 px-2.5 flex items-start justify-between gap-2 min-w-0 border rounded-card transition group select-none cursor-grab active:cursor-grabbing {{ $staskIsNote ? 'bg-sunken border-line cursor-pointer' : 'bg-surface border-line hover:border-line-strong' }}">
                                                        <x-orders.planner-card-body :task="$stask" :designer="$designer" :days="$weekDaysOnly" :next-week="$nextWeekItem" :show-meta="$isSingleDesignerFiltered" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Placeholder when empty & not active -->
                                        @if($daySubtasks->isEmpty())
                                            <div x-show="!inlineActive" @click="openInline()" class="py-1 px-1 text-left cursor-pointer hover:bg-stone-100/60 rounded transition flex items-center gap-2">
                                                <div class="w-3.5 h-3.5 rounded-full border border-dashed border-stone-300 shrink-0"></div>
                                                <p class="text-meta text-zinc-400 select-none">+ {{ __('Escribir subtarea') }}</p>
                                            </div>
                                        @endif

                                        <!-- Quick Add Link when subtasks exist & not active -->
                                        @if($daySubtasks->isNotEmpty())
                                            <div x-show="!inlineActive" class="pt-0.5 px-1">
                                                <button type="button" @click="openInline()" class="text-meta text-zinc-400 hover:text-ink-2 font-medium transition cursor-pointer select-none">
                                                    + {{ __('Escribir subtarea...') }}
                                                </button>
                                            </div>
                                        @endif

                                        <!-- Inline creator row is cloned from #planner-inline-creator-template on first use -->
                                        <div x-ref="creatorHost" wire:ignore></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($isSingleDesignerFiltered && $designerGridList->isNotEmpty())
                    @php
                        $singleDesigner = $designerGridList->first();
                        $todayDateStr = now()->toDateString();
                        
                        $singleDesignerSubtasks = $subtasks->filter(function ($st) use ($singleDesigner) {
                            if ($st->assignee_id) {
                                return (int) $st->assignee_id === (int) $singleDesigner->id;
                            }
                            if ($st->order) {
                                return (int) $st->order->designer_id === (int) $singleDesigner->id || $st->order->designers->contains('id', $singleDesigner->id);
                            }
                            return false;
                        });

                        $todayTasks = $singleDesignerSubtasks->filter(function ($st) use ($todayDateStr) {
                            return $st->scheduled_date?->toDateString() === $todayDateStr;
                        });

                        if (! $showSystemTasks) {
                            $todayTasks = $todayTasks->filter(fn($st) => $st->isWorkTask());
                        }

                        $todayTasks = $this->sortSubtaskCollection($todayTasks);
                        $todayTotal = $todayTasks->count();
                        $todayCompleted = $todayTasks->filter(fn($st) => $st->isDone())->count();
                        $todayPending = $todayTotal - $todayCompleted;
                        $todayPercentage = $todayTotal > 0 ? (int) round(($todayCompleted / $todayTotal) * 100) : 0;
                        $designerColor = $singleDesigner->hex_color ?: match($singleDesigner->color_type) {
                            'magenta' => '#F3A8FF',
                            'cyan' => '#52EAFD',
                            'emerald', 'green' => '#5FE9B5',
                            'amber', 'yellow' => '#fbbf24',
                            default => '#10b981',
                        };

                        $weekMondayStr = $days[0]['date_string'] ?? now()->startOfWeek(Carbon\Carbon::MONDAY)->toDateString();
                        $weekFridayStr = ($days[4]['date_string'] ?? null) ?: Carbon\Carbon::parse($weekMondayStr)->addDays(4)->toDateString();

                        $weekAltaTasks = $singleDesignerSubtasks->filter(function ($st) use ($weekMondayStr, $weekFridayStr) {
                            $dateStr = $st->scheduled_date?->toDateString();
                            if (! $dateStr) {
                                return false;
                            }
                            if ($dateStr >= $weekMondayStr && $dateStr <= $weekFridayStr) {
                                return true;
                            }
                            if ($dateStr < $weekMondayStr && ! $st->isDone()) {
                                return true;
                            }
                            return false;
                        });

                        $altaMirrorTasks = $weekAltaTasks->filter(function ($st) {
                            if (! $st->order) {
                                return false;
                            }

                            $titleUpper = mb_strtoupper($st->title ?? '', 'UTF-8');
                            if (str_contains($titleUpper, 'ALTA') || $st->type === \App\Enums\RelatedTaskType::PONER_ALTA) {
                                return true;
                            }

                            if ($st->order->substatus === \App\Enums\Substatus::PONER_EN_ALTA && ! str_contains($titleUpper, 'FOLLOW UP')) {
                                return $st->isPonerEnAlta();
                            }

                            return false;
                        })->unique('id')->sortBy([
                            fn($a, $b) => ($a->isDone() ? 1 : 0) <=> ($b->isDone() ? 1 : 0),
                            fn($a, $b) => ($a->scheduled_date?->timestamp ?? 0) <=> ($b->scheduled_date?->timestamp ?? 0),
                        ]);

                        $altaTotalCount = $altaMirrorTasks->count();
                        $altaCompletedCount = $altaMirrorTasks->filter(fn($st) => $st->isDone())->count();
                        $altaPendingCount = $altaTotalCount - $altaCompletedCount;
                    @endphp

                    <!-- Right Column (33%): Today's Progress & ALTA Mirror -->
                    <div 
                        class="lg:col-span-1 space-y-4 lg:sticky transition-all w-full min-w-0"
                        style="top: calc(var(--planner-header-h, 148px) + 8px);">

                        <!-- Minimalist Dynamic Today's Progress Widget -->
                        <div 
                            x-data="{ draggingOverToday: false }"
                            @dragover.prevent="draggingOverToday = true"
                            @dragenter.prevent="draggingOverToday = true"
                            @dragleave="if (!$el.contains($event.relatedTarget)) draggingOverToday = false"
                            @drop.prevent="
                                draggingOverToday = false;
                                let rawData = $event.dataTransfer.getData('text/plain');
                                if (rawData) {
                                    if (rawData.startsWith('subtasks:')) {
                                        let taskIds = rawData.replace('subtasks:', '').split(',').map(id => parseInt(id, 10)).filter(Boolean);
                                        $wire.moveMultipleSubtasks(taskIds, '{{ $todayDateStr }}', {{ $singleDesigner->id }});
                                        selectedTaskIds = [];
                                    } else if (rawData.startsWith('subtask:')) {
                                        let taskId = parseInt(rawData.replace('subtask:', ''), 10);
                                        $wire.rescheduleSubtask(taskId, '{{ $todayDateStr }}', {{ $singleDesigner->id }});
                                        selectedTaskIds = [];
                                    } else {
                                        let orderId = rawData.replace('order:', '');
                                        $wire.scheduleOrder(orderId, '{{ $todayDateStr }}');
                                    }
                                }
                            "
                            :class="{ 'border-ink bg-sunken': draggingOverToday }"
                            class="bg-surface border border-line rounded-card p-4 space-y-4 w-full min-w-0">
                            
                            <!-- Header -->
                            <div class="flex items-start justify-between gap-4 border-b border-stone-100 pb-4">
                                <div>
                                    <span class="text-meta text-stone-400 font-medium capitalize block">
                                        {{ now()->translatedFormat('l, d \d\e F') }}
                                    </span>
                                    <h3 class="text-base font-semibold text-zinc-900 mt-0.5 flex items-center gap-2">
                                        <span>{{ __('Progreso de hoy') }}</span>
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $singleDesigner->dot_color_class }}" style="{{ $singleDesigner->dot_inline_style }}" title="{{ $singleDesigner->name }}"></span>
                                    </h3>
                                </div>

                                <!-- Quick Status Pill -->
                                <div class="shrink-0">
                                    @if($todayTotal === 0)
                                        <x-ui.tag>{{ __('Sin tareas') }}</x-ui.tag>
                                    @elseif($todayPending === 0)
                                        <x-ui.tag tone="ok" icon="circle-check">{{ __('¡Al día!') }}</x-ui.tag>
                                    @else
                                        <x-ui.tag>{{ $todayPending }} {{ __('pendientes') }}</x-ui.tag>
                                    @endif
                                </div>
                            </div>

                            <!-- Progress Bar Section -->
                            <div class="space-y-2.5">
                                <div class="flex items-baseline justify-between">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl font-semibold font-mono text-zinc-900">
                                            {{ $todayPercentage }}%
                                        </span>
                                        <span class="text-xs font-medium text-stone-500">
                                            {{ __('completado') }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-medium text-stone-600">
                                        <span class="font-semibold text-zinc-900">{{ $todayCompleted }}</span> / {{ $todayTotal }} {{ __('completadas') }}
                                    </div>
                                </div>

                                <!-- The Progress Bar Track -->
                                <div class="w-full bg-sunken rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full bg-ok transition-all" style="width: {{ $todayPercentage }}%;"></div>
                                </div>
                            </div>

                            <!-- Simple Inline Stats (sin cajas visibles, mucho más pequeñas) -->
                            <div class="flex items-center justify-between text-meta text-stone-500 pt-2 border-t border-stone-100">
                                <div class="flex items-center gap-1">
                                    <span class="text-ink-3 font-medium">{{ __('Completadas:') }}</span>
                                    <span class="font-semibold tabular-nums text-ink">{{ $todayCompleted }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-ink-3 font-medium">{{ __('Pendientes:') }}</span>
                                    <span class="font-semibold tabular-nums text-ink">{{ $todayPending }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Minimalist Mirror de Órdenes para ALTA -->
                        <div class="bg-surface border border-line rounded-card p-4 space-y-3 w-full min-w-0">
                            <!-- Header -->
                            <div class="flex items-center justify-between gap-3 border-b border-stone-100 pb-3">
                                <div class="flex items-center gap-2 min-w-0">
                                                                        <h3 class="text-xs font-semibold text-zinc-900  truncate">
                                        {{ __('Poner en ALTA') }}
                                    </h3>
                                </div>
                                @if($altaTotalCount > 0)
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        @if($altaPendingCount === 0)
                                            <x-ui.tag tone="ok" icon="circle-check">{{ __('Al día') }}</x-ui.tag>
                                        @else
                                            <x-ui.tag>{{ $altaPendingCount }} {{ __('pendientes') }}</x-ui.tag>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Checklist Items (sin cajas dentro de cajas, estilo lista minimalista) -->
                            @if($altaMirrorTasks->isNotEmpty())
                                <div class="divide-y divide-stone-100/70 -mx-1">
                                    @foreach($altaMirrorTasks as $stask)
                                        @php
                                            $stDone = $stask->isDone();
                                        @endphp
                                        <div 
                                            wire:key="alta-mirror-stask-{{ $stask->id }}-{{ $stask->status }}"
                                            @click="$dispatch('open-order-detail', { orderId: {{ $stask->order->id }} })"
                                            class="group flex items-start gap-2.5 py-2 px-1.5 rounded-lg hover:bg-stone-50/80 transition-colors cursor-pointer select-none">
                                            
                                            <!-- Checkbox -->
                                            <button 
                                                type="button"
                                                @click.stop="$wire.toggleSubtaskComplete({{ $stask->id }})" 
                                                class="w-3.5 h-3.5 mt-0.5 rounded border transition flex items-center justify-center shrink-0 cursor-pointer {{ $stDone ? 'bg-ok border-ok text-white' : 'border-stone-300 hover:border-ink bg-white text-transparent hover:text-ok/40' }}"
                                                title="{{ $stDone ? __('Marcar como pendiente') : __('Marcar como completada') }}">
                                                <x-lucide-check class="w-2.5 h-2.5 stroke-[3]" />
                                            </button>

                                            <!-- Content: WO, company, location & task -->
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <x-wo-badge :number="$stask->order->wo_number" />
                                                    <span class="text-body font-medium truncate {{ $stDone ? 'ui-done' : 'text-ink' }}" title="{{ $stask->order->company_name }}">{{ $stask->order->company_name }}</span>
                                                    <x-orders.location :location="$stask->order->location_text" class="shrink-0 max-w-[45%]" />
                                                    @if($stask->isUrgent() && ! $stDone)
                                                        <x-ui.tag tone="danger" icon="zap">{{ __('Urgente') }}</x-ui.tag>
                                                    @endif
                                                </div>
                                                <p class="text-meta truncate mt-0.5 {{ $stDone ? 'ui-done' : 'text-ink-2' }}" title="{{ $stask->order->task_name }}">{{ $stask->order->task_name }}</p>
                                            </div>

                                            <!-- Scheduled Day Indicator -->
                                            @if($stask->scheduled_date)
                                                <span class="text-meta font-mono shrink-0 self-start mt-0.5 {{ $stDone ? 'text-ink-3' : ($stask->scheduled_date->isToday() ? 'text-ink font-semibold' : 'text-ink-3 font-medium') }}">
                                                    {{ $stask->scheduled_date->translatedFormat('D j') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-5 text-center">
                                    <x-lucide-check-circle-2 class="w-4 h-4 text-stone-300 mx-auto mb-1" />
                                    <p class="text-meta text-stone-400 font-medium">
                                        {{ __('Sin órdenes para ALTA esta semana') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                    </div>
                @endif
            </div>
        </div>
    @endif


    <!-- SUBTASK IMPACT & BLOCKING WARNING MODAL -->
    @if($showSubtaskWarningModal)
        <div 
            class="fixed inset-0 z-[120] bg-black/50 flex items-center justify-center p-4"
            @keydown.window.escape.prevent="$wire.cancelSubtaskWarning()">
            <div class="bg-surface border border-line rounded-panel w-full max-w-lg p-5 sm:p-6 space-y-4 shadow-panel max-h-[92vh] flex flex-col">
                <div class="shrink-0 flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-sunken text-ink-2 flex items-center justify-center shrink-0">
                            <x-lucide-alert-triangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-zinc-900">
                                {{ __('Verificación de Subtarea') }}
                            </h3>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                {{ __('Nueva tarea: ":title"', ['title' => $pendingSubtaskData['title'] ?? '']) }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="cancelSubtaskWarning" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-stone-100 transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="overflow-y-auto pr-1 space-y-4 text-xs scrollbar-thin flex-1">
                    @if(!empty($pendingSubtaskData['isOrderBlocked']))
                        <div class="p-4 rounded-xl bg-warn-soft border border-line text-warn space-y-3">
                            <div class="flex items-start gap-2">
                                <x-lucide-ban class="w-4 h-4 text-warn shrink-0 mt-0.5" />
                                <div>
                                    <span class="font-semibold text-warn block">{{ __('Orden Bloqueada') }}</span>
                                    <p class="text-warn text-xs mt-0.5">
                                        {{ __('Actualmente bloqueada por:') }} <span class="font-semibold">{{ $pendingSubtaskData['blockingReason'] ?? __('Faltan Medidas') }}</span>.
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 pt-1 border-t border-line">
                                <label class="font-semibold text-warn block text-xs">
                                    {{ __('¿Esta subtarea resuelve el motivo de bloqueo?') }}
                                </label>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer transition {{ $subtaskBlockResolution === 'unblock' ? 'bg-surface border-ink font-medium text-zinc-900' : 'bg-warn-soft border-line text-zinc-700 hover:bg-white' }}">
                                        <input type="radio" wire:model.live="subtaskBlockResolution" value="unblock" class="mt-0.5 accent-ink">
                                        <div>
                                            <span class="block font-semibold">{{ __('Sí, datos/medidas confirmados (Desbloquear orden)') }}</span>
                                            <span class="block text-meta text-zinc-500">{{ __('Marca las medidas como confirmadas y desbloquea la orden para avanzar.') }}</span>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer transition {{ $subtaskBlockResolution === 'keep_blocked' ? 'bg-surface border-ink font-medium text-zinc-900' : 'bg-warn-soft border-line text-zinc-700 hover:bg-white' }}">
                                        <input type="radio" wire:model.live="subtaskBlockResolution" value="keep_blocked" class="mt-0.5 accent-ink">
                                        <div>
                                            <span class="block font-semibold">{{ __('No, es solo seguimiento (Mantener bloqueada)') }}</span>
                                            <span class="block text-meta text-zinc-500">{{ __('Crea la tarea de consulta/llamada sin alterar el estado de bloqueo.') }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(!empty($pendingSubtaskData['isOrderApproved']))
                        <div class="p-4 rounded-xl bg-warn-soft border border-line text-warn space-y-3">
                            <div class="flex items-start gap-2">
                                <x-lucide-check-circle-2 class="w-4 h-4 text-warn shrink-0 mt-0.5" />
                                <div>
                                    <span class="font-semibold text-warn block">{{ __('Orden ya Aprobada') }}</span>
                                    <p class="text-warn text-xs mt-0.5">
                                        {{ __('Aprobada por') }} <span class="font-semibold">{{ $pendingSubtaskData['approvalType'] ?? __('Cliente') }}</span> ({{ $pendingSubtaskData['approvedAt'] ?? '' }}).
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 pt-1 border-t border-line">
                                <label class="font-semibold text-warn block text-xs">
                                    {{ __('¿Esta subtarea altera el arte o requiere re-aprobación?') }}
                                </label>
                                <div class="grid grid-cols-1 gap-2">
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer transition {{ $subtaskApprovalImpact === 'invalidate' ? 'bg-surface border-ink font-medium text-zinc-900' : 'bg-warn-soft border-line text-zinc-700 hover:bg-white' }}">
                                        <input type="radio" wire:model.live="subtaskApprovalImpact" value="invalidate" class="mt-0.5 accent-ink">
                                        <div>
                                            <span class="block font-semibold text-danger">{{ __('Anular aprobación previa (Requiere nuevo visto bueno)') }}</span>
                                            <span class="block text-meta text-zinc-500">{{ __('El diseño cambió. Se anula la aprobación para prevenir que se envíe a producción por error.') }}</span>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer transition {{ $subtaskApprovalImpact === 'keep' ? 'bg-surface border-ink font-medium text-zinc-900' : 'bg-warn-soft border-line text-zinc-700 hover:bg-white' }}">
                                        <input type="radio" wire:model.live="subtaskApprovalImpact" value="keep" class="mt-0.5 accent-ink">
                                        <div>
                                            <span class="block font-semibold text-ok">{{ __('Conservar aprobación (Solo ajuste técnico/interno)') }}</span>
                                            <span class="block text-meta text-zinc-500">{{ __('El arte aprobado no cambia visualmente; no requiere aprobación adicional del cliente.') }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2.5 pt-3 border-t border-stone-200">
                    <button 
                        type="button" 
                        wire:click="cancelSubtaskWarning" 
                        class="px-4 py-2 rounded-xl border border-stone-300 text-zinc-700 hover:bg-stone-50 text-xs font-semibold transition cursor-pointer">
                        {{ __('Cancelar') }}
                    </button>
                    <button 
                        type="button" 
                        wire:click="confirmSubtaskWarning" 
                        class="px-5 py-2 rounded-control bg-ink hover:bg-black text-white text-xs font-semibold transition cursor-pointer">
                        {{ __('Confirmar y Crear Subtarea') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Alerta SLA -->
    @if($showSlaWarningModal)
        <div 
            x-data
            x-init="
                window.KudosModalStack?.register('sla-warning-modal', $el, () => $wire.closeSlaWarningModal());
                $cleanup(() => window.KudosModalStack?.unregister('sla-warning-modal'));
            "
            @pointerdown="window.KudosModalStack?.bringToFront('sla-warning-modal')"
            class="fixed inset-0 overflow-y-auto bg-stone-900/50 flex items-center justify-center p-4"
            style="z-index: 250;"
            @keydown.window.escape.prevent="if (window.KudosModalStack ? window.KudosModalStack.isTop('sla-warning-modal') : !document.querySelector('[data-modal=\'order-detail\']')) $wire.closeSlaWarningModal()"
            @keydown.window.enter.prevent="if ((window.KudosModalStack ? window.KudosModalStack.isTop('sla-warning-modal') : !document.querySelector('[data-modal=\'order-detail\']')) && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) $wire.closeSlaWarningModal()"
            wire:keydown.escape="closeSlaWarningModal">
            
            <div 
                class="bg-white border border-line rounded-xl shadow-panel max-w-lg w-full flex flex-col transition duration-200 overflow-hidden relative">
                
                <div class="px-5 py-4 border-b border-line bg-danger-soft flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="p-2 rounded-lg bg-danger text-white shrink-0">
                            <x-lucide-alert-triangle class="w-5 h-5 stroke-[2.5]" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-sm text-danger leading-snug">{{ __('Alertas de SLA / Exceso de Plazo') }}</h3>
                            <p class="text-xs text-danger truncate">
                                {{ __('Tareas o subtareas programadas fuera del plazo límite oficial') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeSlaWarningModal"
                        class="p-1 rounded-lg text-danger hover:text-danger hover:bg-danger-soft transition cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-3.5 text-xs text-zinc-700 max-h-96 overflow-y-auto custom-vertical-scrollbar">
                    @if(!empty($slaWarningDetails))
                        <div 
                            @if(!empty($slaWarningDetails['order_id']))
                                wire:click="$dispatch('open-order-detail', { orderId: {{ $slaWarningDetails['order_id'] }} })"
                                role="button"
                                tabindex="0"
                                @keydown.enter.stop="$dispatch('open-order-detail', { orderId: {{ $slaWarningDetails['order_id'] }} })"
                                title="{{ __('Abrir orden :company para gestionar SLA', ['company' => $slaWarningDetails['company_name'] ?? '']) }}"
                                class="p-3 bg-sunken border border-line rounded-lg space-y-1 hover:bg-sunken hover:border-line transition cursor-pointer group select-none"
                            @else
                                class="p-3 bg-sunken border border-line rounded-lg space-y-1"
                            @endif
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="font-semibold text-zinc-900 block text-xs  group-hover:text-ink truncate">{{ $slaWarningDetails['company_name'] ?? '' }}</span>
                                    <span class="text-meta text-zinc-600 block  truncate">{{ $slaWarningDetails['task_name'] ?? '' }}</span>
                                </div>
                                @if(!empty($slaWarningDetails['order_id']))
                                    <div class="text-ink-2 group-hover:text-ink-2 transition-colors shrink-0">
                                        <x-lucide-chevron-right class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                                    </div>
                                @endif
                            </div>
                        </div>

                        <p class="leading-relaxed">
                            {!! __('La subtarea fue programada para el :date, lo cual supera la fecha límite del SLA (:due) por :days.', [
                                'date' => '<strong class="text-ink font-semibold">'.e($slaWarningDetails['scheduled_date'] ?? '').'</strong>',
                                'due' => '<strong class="text-danger font-semibold">'.e($slaWarningDetails['current_due_date'] ?? '').'</strong>',
                                'days' => '<strong class="text-danger font-semibold">'.trans_choice('{1} :count día|[2,*] :count días', (int) ($slaWarningDetails['days_overdue'] ?? 0)).'</strong>',
                            ]) !!}
                        </p>
                    @elseif(isset($slaBreachedList) && $slaBreachedList->isNotEmpty())
                        <div class="space-y-2">
                            <p class="font-medium text-zinc-600 flex items-center justify-between">
                                <span>{{ __('Las siguientes :count tareas en la agenda semanal superan su fecha límite de SLA:', ['count' => $slaBreachedList->count()]) }}</span>
                            </p>
                            <div class="divide-y divide-stone-100 border border-stone-200 rounded-lg overflow-hidden">
                                @foreach($slaBreachedList as $item)
                                    <div 
                                        wire:key="sla-breached-{{ $loop->index }}-{{ $item['order_id'] ?? $loop->index }}"
                                        @if(!empty($item['order_id']))
                                            wire:click="$dispatch('open-order-detail', { orderId: {{ $item['order_id'] }} })"
                                            role="button"
                                            tabindex="0"
                                            @keydown.enter.stop="$dispatch('open-order-detail', { orderId: {{ $item['order_id'] }} })"
                                            title="{{ __('Abrir orden :company para gestionar SLA', ['company' => $item['company_name']]) }}"
                                        @endif
                                        class="p-3 bg-danger-soft hover:bg-danger-soft transition-colors flex items-center justify-between gap-3 cursor-pointer group select-none">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="px-1.5 py-0.2 rounded text-meta font-semibold  {{ $item['type'] === 'subtask' ? ((isset($item['is_work_task']) && !$item['is_work_task']) ? 'bg-ink text-white border border-line' : 'bg-sunken text-ink-2 border border-line') : 'bg-stone-200 text-zinc-800 border border-stone-300' }}">
                                                    {{ $item['type'] === 'subtask' ? ((isset($item['is_work_task']) && !$item['is_work_task']) ? __('Sistema') : __('Subtarea')) : __('Orden') }}
                                                </span>
                                                <h4 class="font-semibold text-xs text-zinc-900 truncate  group-hover:text-danger transition-colors">{{ $item['company_name'] }}</h4>
                                            </div>
                                            <p class="text-meta text-zinc-600 truncate  group-hover:text-zinc-800 transition-colors">{{ $item['task_name'] }}</p>
                                        </div>
                                        <div class="flex items-center gap-2.5 shrink-0">
                                            <div class="text-right">
                                                <span class="text-danger font-semibold text-xs block">
                                                    +{{ $item['days_overdue'] }}d {{ __('Atraso') }}
                                                </span>
                                                <span class="text-meta text-zinc-400 block mt-0.5">
                                                    SLA: {{ $item['current_due_date'] }}
                                                </span>
                                            </div>
                                            <div class="text-danger group-hover:text-danger transition-colors">
                                                <x-lucide-chevron-right class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-4 text-center text-zinc-400 italic">
                            {{ __('No hay alertas de SLA activas en esta semana.') }}
                        </div>
                    @endif

                    <div class="p-2.5 bg-stone-50 border border-stone-200 rounded-lg text-meta text-zinc-500 flex items-start gap-2">
                        <x-lucide-info class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5" />
                        <span>{{ __('Haz clic en cualquier tarea para abrir su orden en el panel lateral y gestionar su SLA sin cerrar esta lista.') }}</span>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-line bg-canvas flex items-center justify-end">
                    <button 
                        type="button" 
                        wire:click="closeSlaWarningModal"
                        class="px-4 py-1.5 rounded-lg bg-stone-900 hover:bg-stone-800 text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5">
                        <x-lucide-check class="w-3.5 h-3.5" />
                        <span>{{ __('Entendido') }}</span>
                    </button>
                </div>

            </div>
        </div>
    @endif


    <script>
        (function() {
            function normalizeSearchText(str) {
                return (str || '')
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            window.filterWorkspaceOrders = function(orders, rawQuery) {
                if (!orders || !Array.isArray(orders)) return [];
                if (!rawQuery || !rawQuery.trim()) return orders;

                const cleanQuery = normalizeSearchText(rawQuery);
                const tokens = cleanQuery
                    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
                    .split(/\s+/)
                    .filter(Boolean);

                if (tokens.length === 0) return orders;

                const scored = [];

                for (let i = 0; i < orders.length; i++) {
                    const o = orders[i];
                    const compNorm = normalizeSearchText(o.company);
                    const locNorm = normalizeSearchText(o.location);
                    const taskNorm = normalizeSearchText(o.task);
                    const woNorm = normalizeSearchText(o.wo_number);
                    const trelloNorm = normalizeSearchText(o.trello_card_title);
                    const respNorm = normalizeSearchText(o.responsible_person);
                    const textNorm = normalizeSearchText(o.text);

                    // Combined search haystack across all relevant order fields
                    const haystack = `${compNorm} ${locNorm} ${taskNorm} ${woNorm} ${trelloNorm} ${respNorm} ${textNorm}`;

                    // All search tokens must appear in the order's searchable haystack
                    let matches = true;
                    for (let j = 0; j < tokens.length; j++) {
                        if (!haystack.includes(tokens[j])) {
                            matches = false;
                            break;
                        }
                    }

                    if (!matches) continue;

                    // Calculate relevance score
                    let score = 0;

                    // Exact full query matches
                    if (compNorm === cleanQuery) {
                        score += 2000;
                    } else if (compNorm.startsWith(cleanQuery)) {
                        score += 1000;
                    } else if (textNorm.startsWith(cleanQuery)) {
                        score += 800;
                    } else if (compNorm.includes(cleanQuery)) {
                        score += 500;
                    } else if (textNorm.includes(cleanQuery)) {
                        score += 400;
                    }

                    // Token-level matches
                    for (let j = 0; j < tokens.length; j++) {
                        const tok = tokens[j];
                        if (compNorm === tok) score += 300;
                        else if (compNorm.startsWith(tok)) score += 200;
                        else if (compNorm.includes(tok)) score += 100;

                        if (locNorm === tok) score += 150;
                        else if (locNorm.startsWith(tok)) score += 100;
                        else if (locNorm.includes(tok)) score += 60;

                        if (taskNorm.includes(tok)) score += 40;
                        if (woNorm === tok || woNorm.startsWith(tok)) score += 200;
                    }

                    scored.push({ order: o, score: score, index: i });
                }

                scored.sort((a, b) => b.score - a.score || a.index - b.index);

                return scored.map(item => item.order);
            };
        })();
    </script>
</div>
@php echo \App\Support\CompactHtml::compact(ob_get_clean()); @endphp
