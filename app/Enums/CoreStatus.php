<?php

namespace App\Enums;

use App\Services\ColorCodingService;

enum CoreStatus: string
{
    case ENTRANTE = 'ENTRANTE';
    case EURALIZ_ORDERS_RECEIVED = 'EURALIZ ORDERS RECEIVED';
    case ADRIAN_ORDERS_RECEIVED = 'ADRIAN ORDERS RECEIVED';
    case CESAR_ORDERS_RECEIVED = 'CESAR ORDERS RECEIVED';
    case TO_DO_TODAY = 'TO DO TODAY';
    case ENVIADO_A_CAMILA = 'ENVIADO A CAMILA';
    case ENVIADO_AL_CLIENTE = 'ENVIADO AL CLIENTE';
    case ON_HOLD = 'ON HOLD';
    case EN_PRODUCCION = 'EN PRODUCCIÓN';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::ENTRANTE => __('BLOCKED'),
            self::EURALIZ_ORDERS_RECEIVED => __('Euralíz Orders Received'),
            self::ADRIAN_ORDERS_RECEIVED => __('Adrián Orders Received'),
            self::CESAR_ORDERS_RECEIVED => __('César Orders Received'),
            self::TO_DO_TODAY => __('Working Today'),
            self::ENVIADO_A_CAMILA => __('Sent to Camila'),
            self::ENVIADO_AL_CLIENTE => __('Sent to Client'),
            self::ON_HOLD => __('On Hold'),
            self::EN_PRODUCCION => __('In Production'),
            self::ARCHIVED => __('Archived'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ENTRANTE => __('Entrante'),
            self::EURALIZ_ORDERS_RECEIVED => __('Euralíz'),
            self::ADRIAN_ORDERS_RECEIVED => __('Adrián'),
            self::CESAR_ORDERS_RECEIVED => __('César'),
            self::TO_DO_TODAY => __('Working'),
            self::ENVIADO_A_CAMILA => __('Camila'),
            self::ENVIADO_AL_CLIENTE => __('Cliente'),
            self::ON_HOLD => __('On Hold'),
            self::EN_PRODUCCION => __('Producción'),
            self::ARCHIVED => __('Archivado'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ENTRANTE => 'orange',
            self::EURALIZ_ORDERS_RECEIVED => 'fuchsia',
            self::ADRIAN_ORDERS_RECEIVED => 'emerald',
            self::CESAR_ORDERS_RECEIVED => 'cyan',
            self::TO_DO_TODAY => 'green',
            self::ENVIADO_A_CAMILA => 'purple',
            self::ENVIADO_AL_CLIENTE => 'blue',
            self::ON_HOLD => 'slate',
            self::EN_PRODUCCION => 'pink',
            self::ARCHIVED => 'rose',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::ENTRANTE => 'bg-orange-50 text-orange-700 border-orange-200',
            self::EURALIZ_ORDERS_RECEIVED => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
            self::ADRIAN_ORDERS_RECEIVED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::CESAR_ORDERS_RECEIVED => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::TO_DO_TODAY => 'bg-green-50 text-green-700 border-green-300 font-semibold',
            self::ENVIADO_A_CAMILA => 'bg-purple-50 text-purple-700 border-purple-200',
            self::ENVIADO_AL_CLIENTE => 'bg-blue-50 text-blue-700 border-blue-200',
            self::ON_HOLD => 'bg-stone-100 text-stone-700 border-stone-200',
            self::EN_PRODUCCION => 'bg-pink-50 text-pink-700 border-pink-200',
            self::ARCHIVED => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    public static function designerQueueStatuses(): array
    {
        return [
            self::EURALIZ_ORDERS_RECEIVED,
            self::ADRIAN_ORDERS_RECEIVED,
            self::CESAR_ORDERS_RECEIVED,
        ];
    }

    public static function isPendingDesign(self $status): bool
    {
        return in_array($status, self::designerQueueStatuses(), true);
    }

    public function colorCodingKey(): ?string
    {
        return match ($this) {
            self::ENTRANTE => 'blocked',
            self::EURALIZ_ORDERS_RECEIVED => 'designer_euraliz',
            self::ADRIAN_ORDERS_RECEIVED => 'designer_adrian',
            self::CESAR_ORDERS_RECEIVED => 'designer_cesar',
            self::TO_DO_TODAY => 'todo_today',
            self::ENVIADO_A_CAMILA => 'camila',
            self::ENVIADO_AL_CLIENTE => 'client',
            self::ON_HOLD => 'cs_hold',
            self::EN_PRODUCCION => 'production',
            self::ARCHIVED => null,
        };
    }

    public function hexColor(): string
    {
        if ($key = $this->colorCodingKey()) {
            try {
                return app(ColorCodingService::class)->getHex($key);
            } catch (\Throwable $e) {
                // Fallback to static values if service is unavailable
            }
        }

        return match ($this) {
            self::ENTRANTE => '#f97316',
            self::EURALIZ_ORDERS_RECEIVED => '#F3A8FF',
            self::ADRIAN_ORDERS_RECEIVED => '#5FE9B5',
            self::CESAR_ORDERS_RECEIVED => '#52EAFD',
            self::TO_DO_TODAY => '#22c55e',
            self::ENVIADO_A_CAMILA => '#a855f7',
            self::ENVIADO_AL_CLIENTE => '#3b82f6',
            self::ON_HOLD => '#64748b',
            self::EN_PRODUCCION => '#db2777',
            self::ARCHIVED => '#fb7185',
        };
    }

    public function badgeInlineStyle(): string
    {
        if ($key = $this->colorCodingKey()) {
            try {
                $palette = app(ColorCodingService::class)->getPalette($key);

                return $palette['badge_style'];
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        return "background-color: {$this->hexColor()}; color: #ffffff;";
    }

    public function dotStyle(): string
    {
        return "background-color: {$this->hexColor()};";
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::ENTRANTE => 'bg-orange-500',
            self::EURALIZ_ORDERS_RECEIVED => 'bg-[#F3A8FF] border border-fuchsia-300',
            self::ADRIAN_ORDERS_RECEIVED => 'bg-[#5FE9B5] border border-emerald-400',
            self::CESAR_ORDERS_RECEIVED => 'bg-[#52EAFD] border border-cyan-400',
            self::TO_DO_TODAY => 'bg-green-500',
            self::ENVIADO_A_CAMILA => 'bg-purple-500',
            self::ENVIADO_AL_CLIENTE => 'bg-blue-500',
            self::ON_HOLD => 'bg-slate-500',
            self::EN_PRODUCCION => 'bg-pink-600',
            self::ARCHIVED => 'bg-rose-400',
        };
    }

    /**
     * Get the substatuses allowed specifically for this core status.
     */
    public function validSubstatuses(): array
    {
        return match ($this) {
            self::ENTRANTE => [
                Substatus::BLOQUEADA,
                Substatus::FALTA_APROBACION_ESTIMADO,
                Substatus::PONER_EN_ALTA,
            ],
            self::EURALIZ_ORDERS_RECEIVED, self::ADRIAN_ORDERS_RECEIVED, self::CESAR_ORDERS_RECEIVED => [
                Substatus::PONER_EN_ALTA,
                Substatus::AJUSTES_PRODUCCION,
            ],
            self::TO_DO_TODAY => [
                Substatus::CAMBIOS_CLIENTE,
                Substatus::CAMBIOS_CAMILA,
                Substatus::AJUSTES_PRODUCCION,
                Substatus::PONER_EN_ALTA,
            ],
            self::ENVIADO_A_CAMILA => [
                Substatus::CAMBIOS_CAMILA,
            ],
            self::ENVIADO_AL_CLIENTE => [
                Substatus::WAITING_FOR_CLIENT,
                Substatus::CAMBIOS_CLIENTE,
                Substatus::NO_RESPUESTA,
            ],
            self::ON_HOLD => [
                Substatus::PAUSADO,
                Substatus::ESPERANDO_PERMISO,
                Substatus::CUSTOMER_SERVICE_REQUIRED,
            ],
            self::EN_PRODUCCION => [
                Substatus::ENVIADO_EN_ALTA,
                Substatus::AJUSTES_PRODUCCION,
            ],
            self::ARCHIVED => [
                Substatus::FINALIZADA,
                Substatus::CANCELADA,
                Substatus::CANCELADA_POR_CLIENTE,
                Substatus::CANCELADA_POR_CAMILA,
                Substatus::NO_REALIZADA_TRANSFERIDA,
                Substatus::CLIENTE_NO_RESPONSIVE,
            ],
        };
    }

    /**
     * Get the default substatus auto-assigned upon entering this core status.
     */
    public function defaultSubstatus(): ?Substatus
    {
        return match ($this) {
            self::ENTRANTE => Substatus::BLOQUEADA,
            self::ENVIADO_A_CAMILA => Substatus::CAMBIOS_CAMILA,
            self::ENVIADO_AL_CLIENTE => Substatus::WAITING_FOR_CLIENT,
            self::ON_HOLD => Substatus::PAUSADO,
            self::EN_PRODUCCION => Substatus::ENVIADO_EN_ALTA,
            self::ARCHIVED => Substatus::FINALIZADA,
            default => null,
        };
    }

    /**
     * Get a global presentation map of all core statuses.
     *
     * @return array<string, array{
     *     value: string,
     *     name: string,
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     hex_color: string,
     *     badge_style: string,
     *     badge_inline_style: string,
     *     dot_style: string,
     *     dot_class: string,
     * }>
     */
    public static function map(): array
    {
        $map = [];

        foreach (self::cases() as $case) {
            $map[$case->value] = [
                'value' => $case->value,
                'name' => $case->name,
                'label' => $case->label(),
                'short_label' => $case->shortLabel(),
                'color' => $case->color(),
                'hex_color' => $case->hexColor(),
                'badge_style' => $case->badgeStyle(),
                'badge_inline_style' => $case->badgeInlineStyle(),
                'dot_style' => $case->dotStyle(),
                'dot_class' => $case->dotClass(),
            ];
        }

        return $map;
    }

    /**
     * Get metadata for a specific core status (instance or string value).
     *
     * @return array{
     *     value: string,
     *     name: string,
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     hex_color: string,
     *     badge_style: string,
     *     badge_inline_style: string,
     *     dot_style: string,
     *     dot_class: string,
     * }|null
     */
    public static function getMetadata(self|string|null $status): ?array
    {
        if ($status === null) {
            return null;
        }

        $enum = $status instanceof self ? $status : self::tryFrom($status);

        if (! $enum) {
            return null;
        }

        return [
            'value' => $enum->value,
            'name' => $enum->name,
            'label' => $enum->label(),
            'short_label' => $enum->shortLabel(),
            'color' => $enum->color(),
            'hex_color' => $enum->hexColor(),
            'badge_style' => $enum->badgeStyle(),
            'badge_inline_style' => $enum->badgeInlineStyle(),
            'dot_style' => $enum->dotStyle(),
            'dot_class' => $enum->dotClass(),
        ];
    }

    public static function labelFor(self|string|null $status, string $default = ''): string
    {
        if ($status instanceof self) {
            return $status->label();
        }

        if (is_string($status) && $enum = self::tryFrom($status)) {
            return $enum->label();
        }

        return $default;
    }

    public static function shortLabelFor(self|string|null $status, string $default = ''): string
    {
        if ($status instanceof self) {
            return $status->shortLabel();
        }

        if (is_string($status) && $enum = self::tryFrom($status)) {
            return $enum->shortLabel();
        }

        return $default;
    }
}
