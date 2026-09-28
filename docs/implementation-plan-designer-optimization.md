# Plan de Implementación Técnica: Optimización y Des-hardcoding de Diseñadores

Este documento establece el plan de trabajo detallado para transformar la gestión de diseñadores en la aplicación **KUDOSDOES**, eliminando completamente las cadenas *hardcoded* (como `"Cesar"`, `"Euraliz"`, `"Adrián"`, etc.) y reemplazándolas por una arquitectura basada en **IDs, atributos dinámicos en base de datos y comandos Artisan para su gestión**.

---

## ⚙️ 1. Declaración del Problema y Objetivos

Actualmente, múltiples servicios, modelos, enums y vistas tienen nombres de diseñadores y sus colas de trabajo escritos directamente en código PHP y JavaScript. Esto genera los siguientes problemas:

1. **Rigidez operativa**: Agregar un nuevo diseñador o renombrar uno existente requiere modificar decenas de archivos en el backend y frontend.
2. **Fragilidad en la sincronización**: Coincidencias basadas en `match ($designer->name)` o `str_contains($name, 'eural')` fallan ante variaciones ortográficas, tildes o cambios en Trello.
3. **Imposibilidad de escalar**: No se pueden configurar dinámicamente colores, roles de supervisión (Lead Designer) o diseñadores externos sin tocar código fuente.

### Objetivos:
* **Centralización en Base de Datos**: Mover colores, alias, estado de cola y flags (`is_lead`, `is_external`) a la tabla `designers`.
* **Comandos Artisan**: Crear `php artisan designers:optimize` para la migración/normalización de datos existente y `php artisan designers:audit` para detectar desalineaciones.
* **Cero Hardcoding**: Eliminar comparaciones por texto en modelos, servicios (`AutomationEngine`, `TrelloSyncService`, `ClientMatchingService`, `SlaEngine`) e interfaces Livewire.

---

## 📌 2. Inventario Exhaustivo de Sitios Afectados & Conflictos

| Componente / Archivo | Ubicación Exacta | Lógica Hardcoded Actual | Solución Escalable con IDs / Atributos BD |
| :--- | :--- | :--- | :--- |
| **`Designer` Model**<br>`app/Models/Designer.php` | `getColorTypeAttribute()`<br>`isSamePersonAs()` | `str_contains($name, 'eural')`<br>`str_contains($name, 'cesar')`<br>`str_contains($name, 'adr')` | Leer de las columnas `designers.color_type` y `designers.hex_color`. Comparar por `user_id` o `id`. |
| **`Order` Model**<br>`app/Models/Order.php` | `getDesignerOrdersReceivedStatus()`<br>`syncDesigners()`<br>`unblock()` | `match ($designerName) { 'Adrián' => ..., 'César' => ..., default => EURALIZ_ORDERS_RECEIVED }`<br>`Designer::where('name', 'like', '%Eural%')` | Leer `$designer->queue_status` o `$designer->getQueueStatus()`. Para externos, enlazar dinámicamente con `Designer::getLeadDesigner()`. |
| **`AutomationEngine`**<br>`app/Services/AutomationEngine.php` | Líneas 277, 308, 416-418, 470-472 | `match ($order->designer?->name)` y arrays fijos `[CoreStatus::EURALIZ_ORDERS_RECEIVED, ...]` | Obtener la cola desde el diseñador primario `$order->getDesignerOrdersReceivedStatus()` o mediante `Designer::getAllQueueStatuses()`. |
| **`TrelloSyncService`**<br>`app/Services/TrelloSyncService.php` | Líneas 74-76, 176-182, 207-212 | Matching con `str_contains($normalized, 'CESAR')` y transliteración fija buscando `'cesar'`, `'guzman'` | Buscar por coincidencia en la columna JSON `designers.aliases` y usar `trello_member_id` indexado. |
| **`ClientMatchingService`**<br>`app/Services/ClientMatchingService.php` | Líneas 21, 145 | Array `$invalidKeywords` includes `'EURALIZ'`, `'CESAR'`, `'ADRIAN - CS'`, `'CAMILA'` | Cargar palabras clave dinámicamente desde `Designer::pluck('name')` y `aliases`. |
| **`OrderTitleParserService`**<br>`app/Services/OrderTitleParserService.php` | Línea 19-22 | Array `$incompatibleHeaderKeywords` incluye `'Cesar'`, `'Adrián'`, `'Euralíz'` | Cargar keywords dinámicamente desde los nombres activos de diseñadores en BD. |
| **`SlaEngine`**<br>`app/Services/SlaEngine.php` | Líneas 39-41 | Mapeo explícito por enum `CoreStatus::CESAR_ORDERS_RECEIVED => ...` | Mapear todas las colas de diseñador devueltas por `Designer::getQueueStatusMapping()`. |
| **`CoreStatus` Enum**<br>`app/Enums/CoreStatus.php` | Métodos `label()`, `color()`, `hexColor()` | Case explícito para cada diseñador (`EURALIZ`, `ADRIAN`, `CESAR`) | Exposer métodos helpers dinámicos que consulten la configuración del diseñador en BD cuando sea un estado de recepción. |
| **Vistas Livewire / UI**<br>`Kanban/Board.php`<br>`WeeklyPlanner.php`<br>`ResolverList.php` | Filtros y columnas de diseñador | Columnas de diseñador fijos y comprobación explicita de enums | Iterar dinámicamente sobre `Designer::where('active', true)->get()`. |

---

## 🏗️ 3. Rediseño de Base de Datos y Modelo `Designer`

### A. Migración de Base de Datos
Crear la migración `2026_09_28_180000_optimize_designers_table.php`:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designers', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('name');
            $table->string('color_type')->default('cyan')->after('slug'); // magenta, cyan, emerald, amber, etc.
            $table->string('hex_color')->nullable()->after('color_type');
            $table->boolean('is_lead')->default(false)->after('active'); // Diseñador principal / supervisor (Euralíz)
            $table->boolean('is_external')->default(false)->after('is_lead'); // Diseñador externo (requiere lead)
            $table->string('queue_status_value')->nullable()->after('is_external'); // Representación del CoreStatus
            $table->json('aliases')->nullable()->after('queue_status_value'); // Variaciones de nombre y Trello usernames
        });
    }

    public function down(): void
    {
        Schema::table('designers', function (Blueprint $table) {
            $table->dropColumn(['slug', 'color_type', 'hex_color', 'is_lead', 'is_external', 'queue_status_value', 'aliases']);
        });
    }
};
```

### B. Nuevos Métodos en el Modelo `Designer`

```php
public static function getLeadDesigner(): Designer
{
    return static::where('active', true)->where('is_lead', true)->first()
        ?? static::where('active', true)->first()
        ?? static::first();
}

public static function findByAliasOrName(string $identifier): ?Designer
{
    $clean = mb_strtolower(trim($identifier));
    
    return static::where('active', true)
        ->get()
        ->first(function ($designer) use ($clean) {
            if (mb_strtolower($designer->name) === $clean) return true;
            if ($designer->slug === Str::slug($clean)) return true;
            
            $aliases = is_array($designer->aliases) ? $designer->aliases : json_decode($designer->aliases ?? '[]', true);
            return collect($aliases)->contains(fn ($a) => mb_strtolower($a) === $clean || str_contains($clean, mb_strtolower($a)));
        });
}

public function getQueueStatus(): CoreStatus
{
    if ($this->queue_status_value && $status = CoreStatus::tryFrom($this->queue_status_value)) {
        return $status;
    }
    
    return match ($this->slug) {
        'adrian' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
        'cesar' => CoreStatus::CESAR_ORDERS_RECEIVED,
        default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
    };
}
```

---

## 🛠️ 4. Especificación de Comandos Artisan

### 1. `php artisan designers:optimize`

Este comando se encarga de poblar los nuevos campos de la base de datos para los diseñadores existentes, vincular sus usuarios y asegurar la integridad de datos sin romper órdenes históricas.

#### Ubicación: `app/Console/Commands/OptimizeDesignersCommand.php`

```php
namespace App\Console\Commands;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OptimizeDesignersCommand extends Command
{
    protected $signature = 'designers:optimize {--force : Sobrescribir datos existentes sin confirmar}';
    protected $description = 'Poblar metadatos de diseñadores (slugs, alias, colores, lead) y eliminar hardcoding en la BD.';

    public function handle(): int
    {
        $this->info('Iniciando optimización de la estructura de diseñadores...');

        $defaults = [
            'Euralíz' => [
                'slug' => 'euraliz',
                'color_type' => 'magenta',
                'hex_color' => '#d946ef',
                'is_lead' => true,
                'is_external' => false,
                'queue_status_value' => CoreStatus::EURALIZ_ORDERS_RECEIVED->value,
                'aliases' => ['euraliz', 'euralíz', 'bravo', 'euraliz bravo'],
            ],
            'César' => [
                'slug' => 'cesar',
                'color_type' => 'cyan',
                'hex_color' => '#06b6d4',
                'is_lead' => false,
                'is_external' => false,
                'queue_status_value' => CoreStatus::CESAR_ORDERS_RECEIVED->value,
                'aliases' => ['cesar', 'césar', 'guzman', 'guzmán', 'cesar guzman'],
            ],
            'Adrián' => [
                'slug' => 'adrian',
                'color_type' => 'emerald',
                'hex_color' => '#10b981',
                'is_lead' => false,
                'is_external' => false,
                'queue_status_value' => CoreStatus::ADRIAN_ORDERS_RECEIVED->value,
                'aliases' => ['adrian', 'adrián', 'reinoza', 'adrian reinosa'],
            ],
        ];

        foreach (Designer::all() as $designer) {
            $matchKey = collect(array_keys($defaults))->first(function ($name) use ($designer) {
                return str_contains(mb_strtolower($designer->name), mb_strtolower(substr($name, 0, 4)));
            });

            if ($matchKey && isset($defaults[$matchKey])) {
                $data = $defaults[$matchKey];
                $designer->update([
                    'slug' => $data['slug'],
                    'color_type' => $data['color_type'],
                    'hex_color' => $data['hex_color'],
                    'is_lead' => $data['is_lead'],
                    'is_external' => $data['is_external'],
                    'queue_status_value' => $data['queue_status_value'],
                    'aliases' => $data['aliases'],
                ]);
                $this->line("  ✓ Actualizado diseñador: {$designer->name} [Lead: ".($data['is_lead'] ? 'SI' : 'NO')."]");
            } else {
                // Configuración por defecto para nuevos o externos
                $slug = Str::slug($designer->name);
                $isExternal = str_contains(mb_strtolower($designer->name), 'extern') || $designer->color_type === 'yellow';
                $designer->update([
                    'slug' => $slug,
                    'color_type' => $isExternal ? 'amber' : 'indigo',
                    'hex_color' => $isExternal ? '#f59e0b' : '#6366f1',
                    'is_lead' => false,
                    'is_external' => $isExternal,
                    'queue_status_value' => CoreStatus::EURALIZ_ORDERS_RECEIVED->value,
                    'aliases' => [$slug, mb_strtolower($designer->name)],
                ]);
                $this->line("  ✓ Configurado diseñador genérico/externo: {$designer->name}");
            }
        }

        $this->info('Optimización de diseñadores completada con éxito.');
        return Command::SUCCESS;
    }
}
```

---

### 2. `php artisan designers:audit`

Comando de mantenimiento preventivo para detectar incongruencias.

#### Ubicación: `app/Console/Commands/AuditDesignersCommand.php`

```php
namespace App\Console\Commands;

use App\Models\Designer;
use App\Models\Order;
use Illuminate\Console\Command;

class AuditDesignersCommand extends Command
{
    protected $signature = 'designers:audit';
    protected $description = 'Auditar desalineaciones entre designer_id, pivote designer_order y CoreStatus de órdenes.';

    public function handle(): int
    {
        $this->info('Iniciando auditoría de diseñadores...');

        // 1. Verificar diseñador Lead
        $leads = Designer::where('is_lead', true)->get();
        if ($leads->count() === 0) {
            $this->error('⚠️ ALERTA: No hay ningún Lead Designer configurado en el sistema.');
        } elseif ($leads->count() > 1) {
            $this->warn("⚠️ ADVERTENCIA: Se encontraron múltiples Lead Designers ({$leads->count()}).");
        } else {
            $this->info("✓ Lead Designer activo: {$leads->first()->name}");
        }

        // 2. Órdenes en cola de recepción desalineadas con su designer_id
        $designers = Designer::whereNotNull('queue_status_value')->get();
        $misalignedCount = 0;

        foreach ($designers as $designer) {
            $queueStatus = $designer->getQueueStatus();
            $wrongOrders = Order::where('core_status', $queueStatus)
                ->where(function ($q) use ($designer) {
                    $q->where('designer_id', '!=', $designer->id)
                      ->orWhereNull('designer_id');
                })
                ->count();

            if ($wrongOrders > 0) {
                $this->warn("  ⚠️ {$wrongOrders} órdenes en cola '{$queueStatus->value}' no tienen como asignado principal a {$designer->name}.");
                $misalignedCount += $wrongOrders;
            }
        }

        if ($misalignedCount === 0) {
            $this->info('✓ Todas las órdenes en colas de recepción están correctamente asignadas a sus diseñadores.');
        }

        return Command::SUCCESS;
    }
}
```

---

## ⚡ 5. Matriz de Edge Cases Atendidos

| Edge Case | Escenario Posible | Solución Implementada |
| :--- | :--- | :--- |
| **1. Diseñador Desactivado** | Un diseñador deja la empresa (`active = false`). | `Designer::getLeadDesigner()` asume la recepción de nuevas órdenes y el tablero oculta la columna inactiva sin perder el historial. |
| **2. Diseñador Externo (Yellow)** | Se asigna un freelancer sin cola propia. | `Order::syncDesigners()` detecta `$designer->is_external` e incluye al `Lead Designer` en la tabla pivote de forma dinámica. |
| **3. Órdenes Unassigned** | Una orden pasa a cola sin `designer_id`. | `Order::getDesignerOrdersReceivedStatus()` retorna el `queue_status` del `Lead Designer` en lugar de caer en el fallback *hardcoded*. |
| **4. Integración Trello** | Llega un nuevo miembro de Trello sin registro previo. | `TrelloSyncService` busca en la columna JSON `aliases`. Si no existe, crea el registro generando un `slug` y asignándole la paleta armónica. |
| **5. Coincidencias por Alias** | En Trello el usuario es `cesarguzman` o `Euraliz Bravo`. | El método `Designer::findByAliasOrName()` encuentra al diseñador correcto sin necesidad de regex escritas a mano en el servicio. |

---

## 📅 6. Plan de Ejecución y Verificación

1. **Paso 1: Migración y Comando Artisan**
   * Crear y ejecutar la migración `2026_09_28_180000_optimize_designers_table.php`.
   * Crear el comando `php artisan designers:optimize` y ejecutar `php artisan designers:optimize`.
2. **Paso 2: Refactorización de Modelos y Servicios Core**
   * Modificar `app/Models/Designer.php` y `app/Models/Order.php`.
   * Refactorizar `app/Services/AutomationEngine.php` y `app/Services/TrelloSyncService.php`.
3. **Paso 3: Refactorización de Parseos y SLA**
   * Actualizar `ClientMatchingService`, `OrderTitleParserService` y `SlaEngine`.
4. **Paso 4: Vistas Livewire**
   * Hacer dinámicas las columnas y filtros en `Kanban\Board`, `WeeklyPlanner` y `ResolverList`.
5. **Paso 5: Verificación y Linter**
   * Ejecutar auditoría: `php artisan designers:audit`.
   * Formatear código: `vendor/bin/pint --format agent`.
   * Ejecutar la suite de tests: `php artisan test --compact`.
