# Plan de Implementación: Optimización Integral del Perfil de Usuario y Gestión de Sesión

> **Proyecto:** Kudos Design Operations (KUDOSDOES)  
> **Fecha:** 29 de Septiembre, 2026  
> **Estado:** Propuesta Técnica y Plan de Ejecución  
> **Objetivo:** Resolver el problema crítico de accesibilidad del cierre de sesión (*Log out*), reestructurar la pantalla `/settings/profile` para transformar un formulario estático básico en un centro operativo personalizado según el rol del usuario (`Admin`, `Coordinador/PM`, `Diseñador`, `Comercial`), y centralizar preferencias de trabajo.

---

## 🚨 1. Diagnóstico del Problema: ¿Por qué no hay botón de Log Out?

Actualmente existen dos causas técnicas por las cuales el usuario experimenta la sensación de "estar atrapado" sin poder cerrar sesión:

### A. Condición de Visibilidad en la Barra Lateral (`components/layouts/app.blade.php`)
En el pie de la barra lateral, el formulario de cierre de sesión está condicionado por Alpine.js:
```blade
<!-- User Profile & Logout Sidebar Footer -->
@auth
    <div class="px-3 py-2 border-t border-[#e9e9e7] bg-[#f7f7f5] flex items-center justify-between gap-2">
        <a href="{{ route('settings.profile') }}" ...>
            ...
        </a>

        <!-- ESTE ELEMENTO SE OCULTA AL COLAPSAR LA BARRA -->
        <form method="POST" action="{{ route('logout') }}" x-show="sidebarOpen">
            @csrf
            <button type="submit" ... title="{{ __('Cerrar Sesión') }}">
                <x-lucide-log-out class="w-4 h-4" />
            </button>
        </form>
    </div>
@endauth
```
* **Consecuencia:** Cuando la barra lateral está en modo compacto (`sidebarOpen = false`), en pantallas medianas o si el usuario la colapsó deliberadamente, el formulario de logout desaparece por completo del DOM visible, dejando solo el círculo de iniciales (`EB`) sin ninguna acción interactiva.

### B. Ausencia de Acciones de Sesión en la Página de Perfil (`/settings/profile`)
* Al hacer clic en el avatar para buscar una opción de salida, la vista [profile-settings.blade.php](file:///Users/euriz/Documents/www/KUDOSDOES/resources/views/livewire/settings/profile-settings.blade.php) solo ofrece dos tarjetas:
  1. *Datos básicos* (Nombre, Email, Teléfono).
  2. *Cambio de contraseña*.
* No existe ningún botón de **Cerrar Sesión**, ni opciones de gestión de dispositivos, ni menú de usuario tipo *Dropdown* en la barra superior (*Topbar*).

---

## 🎯 2. Visión del Nuevo Perfil: De Formulario Plano a Centro Operativo

Kudos DOES no es un sistema genérico; es el centro de operaciones diarias del equipo de diseño. Un diseñador como **Euralíz** (Lead Designer) necesita poder controlar su disponibilidad operativa, mientras que una coordinadora como **Camila** requiere ajustar vistas predeterminadas y umbrales de alerta.

### Estructura de Navegación Modular (Tabs Notion/Linear Style)

Para evitar una pantalla saturada de inputs verticales, el nuevo perfil se organiza en **5 Pestañas Temáticas**:

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│  Mi Perfil y Preferencias                                      [ 🚪 Cerrar Sesión ]     │
│  Administra tus credenciales, rol operativo y preferencias de trabajo.                  │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│  [ 👤 Perfil General ]  [ 🎨 Operación / Rol ]  [ 🔔 Alertas ]  [ ⚙️ Entorno ]  [ 🛡️ Seguridad ]  │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 📋 3. Desglose Detallado por Módulos

### Módulo 1: Cabecera Global y Acciones de Sesión (Inmediato)
* **Header Action Bar:**
  * Botón persistente `Cerrar Sesión` (`POST /logout`) con icono `x-lucide-log-out`, estilo neutro con hover de advertencia en rojo suave.
  * Botón de guardado rápido `Guardar Cambios`.
* **Fix de Barra Lateral (`app.blade.php`):**
  * Si la barra está colapsada (`sidebarOpen = false`), el avatar debe mostrar un popover o menú desplegable rápido al hacer clic o hover, con opciones:
    * `Mi Perfil` (`/settings/profile`)
    * `Cerrar Sesión` (`POST /logout`)
* **Topbar User Menu (Opcional):**
  * Avatar accesible en la esquina derecha del Topbar con menú desplegable para acceso inmediato desde cualquier pantalla.

---

### Módulo 2: Pestaña "General" (Identidad & Datos de Contacto)
* **Avatar y Personalización Visual:**
  * Selector de avatar: Soporte para foto de perfil (`avatar_url`) o personalizador de monograma de iniciales con color de fondo dinámico.
  * Badge visual de Rol (`UserRole`): `Admin` (Púrpura), `Coordinador / PM` (Azul), `Diseñador` (Esmeralda), `Comercial` (Ámbar).
  * Cargo / Título formal en Kudos (ej: *"Lead Graphic Designer & Workflow Lead"*).
* **Datos Personales:**
  * Nombre completo (`name`).
  * Correo corporativo (`email`).
  * Teléfono / WhatsApp de coordinación interna (`phone`).
  * Estado de la cuenta (`active` / `last_login_at`).

---

### Módulo 3: Pestaña "Operación & Rol" (Dinamismo según el Rol)

Esta pestaña adapta su contenido según el `UserRole` del usuario autenticado:

#### A. Si el rol es `DESIGNER` (ej. Euralíz, César, Adrián):
1. **Interruptor de Disponibilidad Operativa:**
   * Estados con indicador de color:
     * 🟢 **Disponible:** Abierto a recibir nuevas asignaciones del Kanban/Backlog.
     * 🟡 **Carga Completa / Enfoque:** Ocupado en órdenes complejas; sugiere a la PM no asignar más entregas para hoy.
     * 🔴 **Ausente / Vacaciones:** Bloquea temporalmente sugerencias automáticas de asignación.
2. **Capacidad Sugerida Diaria:**
   * Número de órdenes activas recomendadas por día (ej. 2 a 4 órdenes diarias).
3. **Especialidades y Habilidades (Skill Tags):**
   * Tags seleccionables: `Renders 3D`, `Vectorización`, `Packaging`, `Identidad Visual`, `Diseño Editorial`, `Corrección de Medidas`.
   * *Propósito:* Facilita al Coordinador/PM asignar la persona ideal al crear o derivar subtareas.
4. **Ficha de Sincronización Trello:**
   * Mostrar el `trello_member_id` vinculado en el modelo `Designer`.
   * Color institucional asignado en el sistema (`hex_color` y `color_type`).
   * Estado de sincronización en tiempo real.
5. **Mini-Métricas del Diseñador:**
   * Órdenes activas bajo su cargo hoy (`CoreStatus::..._ORDERS_RECEIVED`, `TO_DO_TODAY`).
   * Órdenes en espera de revisión (`ENVIADO_A_CAMILA`).
   * Total de órdenes finalizadas en la última semana.

#### B. Si el rol es `COORDINATOR` (ej. Camila):
1. **Vista Predeterminada de Arranque:**
   * Seleccionar qué pantalla cargar al iniciar sesión: `Weekly Planner`, `Kanban Board` o cola de `Resolver` (bloqueos).
2. **Filtros Favoritos:**
   * Activar por defecto el filtro de diseñadores activos o clientes críticos.
3. **Umbrales de Tolerancia Operativa:**
   * Horas límite para considerar una orden estancada en revisión o sin medidas antes de emitir alerta.

#### C. Si el rol es `ADMIN`:
1. **Atajos a Gestión de Plataforma:**
   * Enlaces directos a [Gestión de Usuarios y Roles (`/settings/users`)](file:///Users/euriz/Documents/www/KUDOSDOES/resources/views/livewire/settings/user-management.blade.php), Backups del Sistema, Sincronización Trello y Mapeo de Subestatus.
2. **Indicador de Salud del Workspace:**
   * Conectividad de Webhooks Trello, estado de la base de datos y tareas programadas.

#### D. Si el rol es `SALES`:
1. **Filtros Comerciales:**
   * Clientes asignados prioritarios y acceso directo a creación rápida de órdenes entrantes.

---

### Módulo 4: Pestaña "Notificaciones & Alertas"
Permite al usuario gobernar su [Centro de Notificaciones (`NotificationCenter`)](file:///Users/euriz/Documents/www/KUDOSDOES/app/Livewire/Notifications/NotificationCenter.php):

* **Matriz de Toggles de Notificación:**
  * `[✓]` **Nueva Orden Asignada:** Notificarme inmediatamente cuando una orden o subtarea me es asignada.
  * `[✓]` **Cambio a Bloqueo (`BLOQUEADA` / `Faltan Medidas`):** Alerta cuando un trabajo se frena.
  * `[✓]` **Feedback de Revisión:** Cuando una orden enviada a Camila o al cliente recibe comentarios o solicitudes de cambio.
  * `[✓]` **Órdenes Vencidas (`OVERDUE`):** Advertencia destacada de retrasos en entregas.
  * `[✓]` **Reprogramación en Planner:** Notificar si mi calendario semanal fue modificado por coordinación.
* **Canales:**
  * Alertas en la campana de la app (In-App).
  * Sonido sutil de campana para eventos de alta prioridad.

---

### Módulo 5: Pestaña "Entorno & Preferencias"
Centraliza las preferencias del sistema que hoy están dispersas o ausentes:
* **Idioma del Sistema:**
  * Selector intuitivo entre `Español (ES)` e `English (EN)`, reutilizando la lógica existente en `App\Livewire\Settings\Language`.
* **Página de Inicio Predeterminada:**
  * Opciones: `Dashboard Principal`, `Kanban Board`, `Weekly Planner`, `Backlog`, `Cola Resolver`.
* **Formato de Fechas:**
  * `DD/MM/YYYY` (Estándar LATAM/España) vs `MM/DD/YYYY` (Estándar USA).

---

### Módulo 6: Pestaña "Seguridad & Sesiones"
* **Actualización de Contraseña:**
  * Contraseña actual, nueva contraseña y confirmación.
  * Indicador de robustez y visibilidad con icono de ojo (`mostrar/ocultar`).
* **Auditoría de Sesión:**
  * Fecha y hora del último acceso registrado (`last_login_at`).
  * Dirección IP y navegador actual.
* **Cierre de Sesiones Remotas:**
  * Botón para invalidar sesiones en otros dispositivos manteniendo la sesión actual activa.

---

## 🛠️ 4. Arquitectura de Datos y Migraciones Requeridas

Para soportar las nuevas preferencias sin saturar el modelo `User`, se plantean dos adiciones sencillas y seguras:

### A. Campo JSON `preferences` en tabla `users`
Migración `add_preferences_to_users_table.php`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->json('preferences')->nullable()->after('phone');
    // Almacena:
    // - default_landing_page ('kanban', 'planner', 'dashboard')
    // - locale ('es', 'en')
    // - date_format ('d/m/Y')
    // - notifications (assigned, blocked, review, sound_enabled)
});
```

### B. Atributos Operativos en modelo `Designer`
Aprovechar la tabla `designers` existente para disponibilidad y habilidades:
* `designers.is_available` (booleano, por defecto `true`).
* `designers.max_daily_capacity` (entero, por defecto `3`).
* `designers.skills` (JSON array: `['3d', 'vector', 'editorial']`).

---

## 🚀 5. Fases de Implementación

```
┌────────────────────────────────────────────────────────────────────────┐
│ FASE 1: Solución Inmediata de Ergonomía & Botón Log Out (Hoy)          │
│ • Agregar botón "Cerrar Sesión" en cabecera de profile-settings.       │
│ • Resolver bug de sidebar colapsada en app.blade.php.                  │
├────────────────────────────────────────────────────────────────────────┤
│ FASE 2: Reestructuración UI con Pestañas en ProfileSettings            │
│ • Layout tabulado estilo Notion/Linear en Blade.                       │
│ • Componente Livewire actualizado con pestañas reactivas ($activeTab). │
├────────────────────────────────────────────────────────────────────────┤
│ FASE 3: Preferencias de Usuario & Operación de Rol                     │
│ • Migración para campo preferences en users.                           │
│ • Toggles de disponibilidad para diseñadores y métricas personales.    │
│ • Integración de selección de idioma (ES/EN) en el perfil.             │
├────────────────────────────────────────────────────────────────────────┤
│ FASE 4: Notificaciones, Seguridad & Tests                              │
│ • Configuración de alertas de NotificationCenter por usuario.          │
│ • Pruebas automáticas con PHPUnit (AuthenticationAndProfileTest.php).  │
└────────────────────────────────────────────────────────────────────────┘
```

---

## ✅ 6. Criterios de Aceptación (Definition of Done)

1. **Logout siempre accesible:** El usuario puede cerrar sesión desde el perfil y desde la barra lateral sin importar si está colapsada o expandida.
2. **Experiencia de Rol rica:** Al ingresar como diseñador (ej. Euralíz), el perfil muestra su vinculación con Trello, su estado de disponibilidad operativa y sus órdenes activas.
3. **Persistencia limpia:** Las preferencias de idioma, landing page y notificaciones se guardan inmediatamente con validación y feedback visual mediante toasts o mensajes flash.
4. **Diseño consistente:** Respeta la estética sobria, limpia y moderna del sistema Kudos (paleta neutra `#fbfbfa`, fuentes legibles, componentes Lucide y bordes sutiles).
5. **Calidad de Código:** Formateado con Laravel Pint (`vendor/bin/pint --format agent`) y pruebas unitarias/feature en verde.
