import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

// Kudos Design Ops - Global Dirty Guard Manager

window.KudosDirtyGuard = {
    dirtyRegistry: new Map(),
    isConfirmModalOpen: false,

    register(id, isDirtyFn, element = null) {
        this.dirtyRegistry.set(id, { checkFn: isDirtyFn, el: element });
        this.updateGlobalState();
    },

    unregister(id) {
        this.dirtyRegistry.delete(id);
        this.updateGlobalState();
    },

    isDirty() {
        for (const [id, entry] of this.dirtyRegistry.entries()) {
            try {
                const checkFn = typeof entry === 'function' ? entry : entry?.checkFn;
                const el = typeof entry === 'object' ? entry?.el : null;

                // Auto-cleanup if registered DOM element is no longer attached to document
                if (el && !document.body.contains(el)) {
                    this.dirtyRegistry.delete(id);
                    continue;
                }

                const result = typeof checkFn === 'function' ? checkFn() : Boolean(checkFn);
                if (result) return true;
            } catch (e) {
                // Delete stale checks
                this.dirtyRegistry.delete(id);
            }
        }
        return false;
    },

    updateGlobalState() {
        window.__hasUnsavedChanges = this.isDirty();
    },

    confirmIfDirty(actionCallback, options = {}) {
        if (this.isConfirmModalOpen) return;
        if (this.isDirty()) {
            this.openConfirmModal({
                title: options.title || '¿Descartar cambios sin guardar?',
                description: options.description || 'Tienes información o cambios editados sin guardar. Si sales ahora, estos cambios se perderán.',
                confirmText: options.confirmText || 'Sí, descartar cambios',
                cancelText: options.cancelText || 'Continuar editando',
                onConfirm: () => {
                    this.dirtyRegistry.clear();
                    this.updateGlobalState();
                    actionCallback();
                }
            });
        } else {
            actionCallback();
        }
    },

    confirmCheck(checkId, actionCallback, options = {}) {
        if (this.isConfirmModalOpen) return;
        const entry = this.dirtyRegistry.get(checkId);
        const checkFn = typeof entry === 'function' ? entry : entry?.checkFn;
        let isCheckDirty = false;
        if (checkFn) {
            try {
                isCheckDirty = typeof checkFn === 'function' ? checkFn() : Boolean(checkFn);
            } catch (e) {
                isCheckDirty = false;
            }
        }

        if (isCheckDirty) {
            this.openConfirmModal({
                title: options.title || '¿Descartar cambios sin guardar?',
                description: options.description || 'Tienes información editada sin guardar. Si sales ahora, se perderán los datos modificados.',
                confirmText: options.confirmText || 'Sí, descartar cambios',
                cancelText: options.cancelText || 'Continuar editando',
                onConfirm: () => {
                    this.unregister(checkId);
                    actionCallback();
                }
            });
        } else {
            actionCallback();
        }
    },

    openConfirmModal(modalOptions) {
        this.isConfirmModalOpen = true;
        window.dispatchEvent(new CustomEvent('open-dirty-confirm-modal', { detail: modalOptions }));
    },

    closeConfirmModal() {
        this.isConfirmModalOpen = false;
    }
};


// Global beforeunload warning for page refresh or tab close
window.addEventListener('beforeunload', (event) => {
    if (window.KudosDirtyGuard.isDirty()) {
        event.preventDefault();
        event.returnValue = 'Tienes cambios sin guardar.';
        return 'Tienes cambios sin guardar.';
    }
});

// Intercept internal link navigation if unsaved changes exist
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.getAttribute('target') === '_blank') {
        return;
    }

    if (window.KudosDirtyGuard && window.KudosDirtyGuard.isDirty()) {
        event.preventDefault();
        event.stopPropagation();
        window.KudosDirtyGuard.confirmIfDirty(() => {
            window.location.href = href;
        });
    }
}, true);

// Kudos Design Ops - Global Dropdown Navigation Manager
window.KudosDropdownNav = {
    handleKeydown(event, container, openSetter) {
        const key = event.key;
        if (!['ArrowDown', 'ArrowUp', 'Escape', 'Enter'].includes(key)) return;

        // Find the dropdown menu panel inside the container
        const panel = container.querySelector('[data-dropdown-panel]') || 
                      container.querySelector('[x-show]') ||
                      container.querySelector('.absolute');
        if (!panel) return;

        const getItems = () => {
            const elements = Array.from(panel.querySelectorAll('button, a, input[type="text"], [tabindex="0"], [role="option"]'));
            return elements.filter(el => {
                if (el.offsetParent === null && el.tagName !== 'BODY') return false;
                if (el.disabled) return false;
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') return false;
                return true;
            });
        };

        const activeEl = document.activeElement;
        const trigger = container.querySelector('button, input') || container;

        if (key === 'Escape') {
            event.preventDefault();
            if (typeof openSetter === 'function') openSetter(false);
            if (trigger && typeof trigger.focus === 'function') trigger.focus();
            return;
        }

        const items = getItems();
        if (items.length === 0) return;

        const currentIndex = items.indexOf(activeEl);

        if (key === 'ArrowDown') {
            event.preventDefault();
            if (typeof openSetter === 'function') openSetter(true);

            let nextIndex = 0;
            if (currentIndex >= 0 && currentIndex < items.length - 1) {
                nextIndex = currentIndex + 1;
            } else if (currentIndex === items.length - 1) {
                nextIndex = 0;
            }

            const target = items[nextIndex];
            if (target) {
                target.focus();
                if (typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ block: 'nearest' });
                }
            }
        } else if (key === 'ArrowUp') {
            event.preventDefault();
            let prevIndex = items.length - 1;
            if (currentIndex > 0) {
                prevIndex = currentIndex - 1;
            } else if (currentIndex === 0) {
                if (trigger && activeEl !== trigger && typeof trigger.focus === 'function') {
                    trigger.focus();
                    return;
                }
                prevIndex = items.length - 1;
            }

            const target = items[prevIndex];
            if (target) {
                target.focus();
                if (typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ block: 'nearest' });
                }
            }
        } else if (key === 'Enter' && activeEl && activeEl !== trigger) {
            if (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA') return;
            event.preventDefault();
            activeEl.click();
        }
    }
};

const registerAlpineDropdown = () => {
    if (window.Alpine) {
        window.Alpine.directive('dropdown-nav', (el, { expression }, { evaluate }) => {
            const varName = expression || 'open';
            el.addEventListener('keydown', (e) => {
                if (['ArrowDown', 'ArrowUp', 'Escape', 'Enter'].includes(e.key)) {
                    const openSetter = (val) => {
                        try {
                            evaluate(`${varName} = ${val}`);
                        } catch (err) {
                            // Ignore if variable isn't present
                        }
                    };
                    window.KudosDropdownNav.handleKeydown(e, el, openSetter);
                }
            });
        });
    }
};

if (window.Alpine) {
    registerAlpineDropdown();
} else {
    document.addEventListener('alpine:init', registerAlpineDropdown);
}


// Interactive Demo Plan Spotlight Tour powered by Driver.js
window.KudosDemoTour = {
    driverInstance: null,

    steps: [
        {
            route: '/',
            element: '#tour-demo-btn',
            title: 'Paso 1: Bienvenida al Tour Interactivo',
            description: 'Bienvenido al Walkthrough Interactivo de Kudos Design Ops. Esta guia te mostrara paso a paso todas las capacidades clave, la gestion de SLA y la automatizacion del flujo de diseno.',
            talkingPoint: 'Hoy veremos como Kudos Design Ops convierte la actividad sin estructurar de Trello en un flujo ordenado y gobernado por SLA.',
            wow: 'Claridad Operativa Total',
            popoverPosition: 'bottom'
        },
        {
            route: '/',
            element: '#tour-stats-today',
            title: 'Paso 2: Filtro de Trabajo "Para Hoy"',
            description: 'Muestra las ordenes y subtareas programadas especificamente para la jornada de hoy. Haz clic para filtrar la tabla principal al instante.',
            talkingPoint: 'Permite que cada miembro del equipo se concentre unicamente en lo que vence el dia de hoy.',
            wow: 'Filtro instantaneo para entregas de hoy',
            popoverPosition: 'bottom'
        },
        {
            route: '/',
            element: '#tour-stats-overdue',
            title: 'Paso 3: Alertas de Ordenes Atrasadas',
            description: 'Identifica de inmediato todas las ordenes que superaron su fecha limite objetivo o incumplieron el tiempo de entrega SLA.',
            talkingPoint: 'Destaca de forma prioritaria los retrasos criticos para actuar antes de que afecten la relacion con el cliente.',
            wow: 'Visibilidad inmediata de retrasos',
            popoverPosition: 'bottom'
        },
        {
            route: '/',
            element: '#tour-stats-resolver',
            title: 'Paso 4: Casos que Requieren Atencion (Action Required)',
            description: 'Conteo en tiempo real de ordenes retenidas, bloqueadas o con inconsistencias que necesitan intervencion de Management.',
            talkingPoint: 'Agrupa las excepciones operativas para resolver cuellos de botella sin buscar en multiples pestañas.',
            wow: 'Gestion centralizada de excepciones',
            popoverPosition: 'bottom'
        },
        {
            route: '/',
            element: '#tour-role-switcher',
            title: 'Paso 5: Selector de Vistas por Rol',
            description: 'Alterna al instante entre Vista General, Diseñador y Gestión/Account para adaptar la densidad de informacion al perfil del usuario.',
            talkingPoint: 'Los disenadores ven sus prioridades operativas mientras los gerentes observan el estado general.',
            wow: 'Personalizacion por rol de usuario',
            popoverPosition: 'bottom'
        },
        {
            route: '/',
            element: '#tour-designer-colors',
            title: 'Paso 6: Codificacion Visual por Disenador',
            description: 'Perfil cromatico unico asignado a cada disenador: Euraliz (Magenta), Adrian (Verde), Cesar (Cian), Externo (Amarillo).',
            talkingPoint: 'Cada miembro del equipo tiene una firma de color distintiva en la interfaz, eliminando dudas sobre la propiedad de las tareas.',
            wow: 'Asignacion cromatica transparente',
            popoverPosition: 'right'
        },
        {
            route: '/trello-sync',
            element: '#tour-trello-sync-header',
            title: 'Paso 7: Sincronizacion en Vivo con Trello',
            description: 'Conexion bidireccional en tiempo real con tableros Trello. Permite importar tarjetas y sincronizar estados automaticamente.',
            talkingPoint: 'La integracion mantiene a Trello y al sistema alineados sin trabajo manual de duplicacion.',
            wow: 'Sincronizacion bidireccional',
            popoverPosition: 'bottom'
        },
        {
            route: '/trello-sync',
            element: '#tour-trello-sync-btn',
            title: 'Paso 8: Disparador de Sincronizacion Manual',
            description: 'Boton para forzar la actualizacion inmediata desde Trello, importando cambios recientes de listas y tarjetas.',
            talkingPoint: 'Permite refrescar datos al instante tras mover tarjetas en Trello durante reuniones en vivo.',
            wow: 'Sincronizacion bajo demanda',
            popoverPosition: 'bottom'
        },
        {
            route: '/trello-sync',
            element: '#tour-trello-clear-btn',
            title: 'Paso 9: Restablecimiento de Datos de Prueba',
            description: 'Herramienta para limpiar la base de datos local y regenerar datos de demostracion limpios.',
            talkingPoint: 'Facilita la preparacion de demostraciones repetibles y pruebas de flujo desde cero.',
            wow: 'Reinico rapido de entorno demo',
            popoverPosition: 'bottom'
        },
        {
            route: '/trello-sync',
            element: '#tour-trello-board-input',
            title: 'Paso 10: Configuracion de Tablero Trello',
            description: 'Campo para vincular cualquier URL o ID de tablero de Trello de 8 caracteres.',
            talkingPoint: 'Permite cambiar o conectar diferentes tableros operativos con un solo campo.',
            wow: 'Configuracion flexible de tableros',
            popoverPosition: 'top'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-title',
            title: 'Paso 11: Tablero Kanban y Contador de Listas',
            description: 'Gestion del ciclo de vida del trabajo a traves de 9 listas operativas (Backlog, En Proceso, En Revision, Completado, etc.).',
            talkingPoint: 'Visualiza el flujo continuo de proyectos desde la recepcion hasta la entrega final.',
            wow: 'Flujo continuo en 9 listas',
            popoverPosition: 'bottom'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-new-btn',
            title: 'Paso 12: Creacion Rapida de Ordenes',
            description: 'Boton directo para registrar manualmente nuevas ordenes de trabajo con cliente, tarea y prioridad.',
            talkingPoint: 'Permite ingresar ordenes urgentes sin pasar por Trello.',
            wow: 'Ingreso manual directo',
            popoverPosition: 'bottom'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-search',
            title: 'Paso 13: Busqueda Inteligente en Kanban',
            description: 'Buscador en tiempo real con desplegable de coincidencias por cliente, orden o tarea.',
            talkingPoint: 'Encuentra cualquier proyecto entre cientos de tarjetas en milisegundos.',
            wow: 'Busqueda rapida con autocompletado',
            popoverPosition: 'bottom'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-substatus-filter',
            title: 'Paso 14: Filtro Fino por Subestatus',
            description: 'Filtra el tablero por razones especificas (Esperando Aprobacion de Cliente, Revisiones Solicitadas, Pausado, Atrasado).',
            talkingPoint: 'Los subestatus explican exactamente por que una tarea esta detenida o en que fase de revision se encuentra.',
            wow: 'Transparencia en motivos de retencion',
            popoverPosition: 'bottom'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-group-tabs',
            title: 'Paso 15: Grupos de Columnas por Etapa',
            description: 'Pestanas para filtrar rapidamente bloques de listas: Bloqueadas & Pendientes, En Proceso, o Todas las Listas.',
            talkingPoint: 'Reduce la sobrecarga visual enfocando solo las columnas de interes.',
            wow: 'Vistas agrupadas de columnas',
            popoverPosition: 'bottom'
        },
        {
            route: '/kanban',
            element: '#tour-kanban-board',
            title: 'Paso 16: Columnas Drag and Drop',
            description: 'Arrastra y suelta tarjetas entre listas para actualizar su estado de trabajo en tiempo real con scroll automatico.',
            talkingPoint: 'Movimiento fluido de tarjetas con actualizacion instantanea en la base de datos.',
            wow: 'Drag & drop asistido con auto-scroll',
            popoverPosition: 'top'
        },
        {
            route: '/resolver',
            element: '#tour-resolver-header',
            title: 'Paso 17: Smart Resolver y Motor de Reglas SLA',
            description: 'El motor de resolucion proactiva identifica automaticamente ordenes que requieren atencion inmediata de Administracion o Management.',
            talkingPoint: 'Responde en tiempo real a la pregunta diaria: Que requiere atencion ahora mismo, por que esta retrasado y quien esta a cargo?',
            wow: 'Motor de respuesta automatica',
            popoverPosition: 'bottom'
        },
        {
            route: '/resolver',
            element: '#tour-resolver-cases-badge',
            title: 'Paso 18: Conteo de Casos Pendientes',
            description: 'Indicador visual del numero exacto de excepciones que exigen resolucion prioritaria.',
            talkingPoint: 'Mantiene al equipo enfocado en llevar a cero los casos pendientes.',
            wow: 'Contador de prioridad operativa',
            popoverPosition: 'bottom'
        },
        {
            route: '/planner',
            element: '#tour-planner-header',
            title: 'Paso 19: Planificador Semanal por Disenador',
            description: 'Agenda por disenador y dia para balancear la carga de trabajo diaria sin sobrecargar entregas.',
            talkingPoint: 'Los disenadores obtienen un cronograma diario sin distracciones, mientras los gerentes tienen visibilidad total.',
            wow: 'Planificacion clara y optimizada',
            popoverPosition: 'bottom'
        },
        {
            route: '/clients',
            element: '#tour-client-search',
            title: 'Paso 20: Busqueda Centralizada en Client Hub',
            description: 'Buscador de clientes con filtro instantaneo por nombre de empresa o contacto.',
            talkingPoint: 'Accede a la informacion y proyectos de cualquier cliente en un segundo.',
            wow: 'Buscador instantaneo de clientes',
            popoverPosition: 'bottom'
        },
        {
            route: '/clients',
            element: '#tour-client-new-btn',
            title: 'Paso 21: Creacion de Fichas de Cliente',
            description: 'Boton para registrar un nuevo cliente con sus datos corporativos y contactos.',
            talkingPoint: 'Permite crear nuevos perfiles de clientes para asociar ordenes de trabajo.',
            wow: 'Registro centralizado de clientes',
            popoverPosition: 'bottom'
        },
        {
            route: '/analytics',
            element: '#tour-analytics-header',
            title: 'Paso 22: Analytics e Insights de Rendimiento',
            description: 'Dashboard ejecutivo con metricas operativas en tiempo real y tendencias de entrega.',
            talkingPoint: 'Proporciona visibilidad basada en datos sobre el rendimiento del equipo de diseno.',
            wow: 'Metricas ejecutivas en tiempo real',
            popoverPosition: 'bottom'
        },
        {
            route: '/analytics',
            element: '#tour-analytics-kpi-1',
            title: 'Paso 23: Carga Operativa Activa',
            description: 'Tarjeta de KPI que muestra el total de ordenes activas en diseno excluyendo archivos y entregados.',
            talkingPoint: 'Proporciona el volumen exacto de proyectos simultaneos en ejecucion.',
            wow: 'Metrica de capacidad activa',
            popoverPosition: 'bottom'
        }
    ],

    getCurrentStepIndex() {
        const val = localStorage.getItem('kudos_demo_tour_step');
        return val !== null ? parseInt(val, 10) : 0;
    },

    saveCurrentStepIndex(idx) {
        localStorage.setItem('kudos_demo_tour_step', idx.toString());
    },

    start(startFromIndex = null) {
        const stepIdx = startFromIndex !== null ? startFromIndex : this.getCurrentStepIndex();
        const stepConfig = this.steps[stepIdx] || this.steps[0];

        if (window.location.pathname !== stepConfig.route) {
            window.__navigating_tour = true;
            this.saveCurrentStepIndex(stepIdx);
            localStorage.setItem('kudos_demo_tour_active', 'true');
            window.location.href = stepConfig.route;
            return;
        }

        this.showStep(stepIdx);
    },

    showStep(index) {
        window.__navigating_tour = false;
        this.saveCurrentStepIndex(index);
        localStorage.setItem('kudos_demo_tour_active', 'true');
        const stepConfig = this.steps[index];
        if (!stepConfig) return;

        let el = document.querySelector(stepConfig.element);
        if (!el) el = document.body;

        const isLast = index === this.steps.length - 1;

        if (this.driverInstance) {
            window.__navigating_tour = true;
            this.driverInstance.destroy();
            window.__navigating_tour = false;
        }

        this.driverInstance = driver({
            showProgress: true,
            animate: true,
            allowClose: true,
            onNextClick: () => {
                if (isLast) {
                    this.stop();
                } else {
                    this.start(index + 1);
                }
            },
            onPrevClick: () => {
                if (index > 0) {
                    this.start(index - 1);
                }
            },
            onDestroyed: () => {
                if (!window.__navigating_tour) {
                    localStorage.removeItem('kudos_demo_tour_active');
                }
            },
            popoverClass: 'kudos-driver-popover',
            steps: [
                {
                    element: el,
                    popover: {
                        title: `<div class="flex items-center justify-between gap-2 font-sans">
                                  <span class="font-bold text-sm text-stone-900">${stepConfig.title}</span>
                                  <span class="text-[10px] uppercase tracking-wider font-mono font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded border border-emerald-200">${index + 1}/${this.steps.length}</span>
                                </div>`,
                        description: `<div class="space-y-2 text-xs font-sans text-stone-700 mt-1">
                                        <p class="leading-relaxed font-medium">${stepConfig.description}</p>
                                        <div class="bg-stone-100 p-2.5 rounded-lg border border-stone-200 space-y-1">
                                          <div class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Punto Clave:</div>
                                          <div class="italic text-[11px] text-stone-800 font-serif leading-snug">"${stepConfig.talkingPoint}"</div>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[11px] text-purple-700 font-semibold pt-0.5">
                                          <span>${stepConfig.wow}</span>
                                        </div>
                                      </div>`,
                        side: stepConfig.popoverPosition || 'bottom',
                        align: 'start',
                        nextBtnText: isLast ? 'Finalizar Demo' : 'Siguiente Paso',
                        prevBtnText: 'Anterior',
                        doneBtnText: 'Finalizar',
                    }
                }
            ]
        });

        this.driverInstance.drive();
    },

    stop() {
        if (this.driverInstance) {
            this.driverInstance.destroy();
        }
        localStorage.removeItem('kudos_demo_tour_active');
        localStorage.removeItem('kudos_demo_tour_step');
    },

    checkAutoResume() {
        if (localStorage.getItem('kudos_demo_tour_active') === 'true') {
            const stepIdx = this.getCurrentStepIndex();
            setTimeout(() => {
                this.start(stepIdx);
            }, 300);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.KudosDemoTour.checkAutoResume();
});

window.addEventListener('toggle-tutorial-mode', () => {
    if (localStorage.getItem('kudos_demo_tour_active') === 'true') {
        window.KudosDemoTour.stop();
    } else {
        window.KudosDemoTour.start(0);
    }
});

window.cleanWoNumber = function(val) {
    if (!val) return '';
    let str = String(val).trim();
    return str.replace(/^(wo|#)[\s#\-:]*/i, '').trim() || str;
};

window.copyWoToClipboard = function(rawWoNumber, event = null) {
    if (event) {
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
        if (typeof event.preventDefault === 'function') event.preventDefault();
    }
    
    const numberToCopy = window.cleanWoNumber(rawWoNumber);
    if (!numberToCopy) return;

    const doCopy = () => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(numberToCopy);
        } else {
            return new Promise((resolve, reject) => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = numberToCopy;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    resolve();
                } catch (err) {
                    reject(err);
                }
            });
        }
    };

    doCopy().then(() => {
        window.dispatchEvent(new CustomEvent('toast', {
            detail: {
                message: `WO #${numberToCopy} copiado al portapapeles`,
                type: 'success'
            }
        }));
    }).catch(err => {
        console.error('Error al copiar WO:', err);
    });
};

