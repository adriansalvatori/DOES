<?php

namespace Tests\Unit;

use App\Casts\CoreStatusCast;
use App\Enums\CoreStatus;
use App\Models\Order;
use Tests\TestCase;

class CoreStatusTest extends TestCase
{
    public function test_all_cases_have_valid_short_labels(): void
    {
        foreach (CoreStatus::cases() as $status) {
            $this->assertNotEmpty($status->shortLabel(), "CoreStatus {$status->name} should have a non-empty shortLabel");
            $this->assertIsString($status->shortLabel());
        }

        $this->assertEquals(__('Camila'), CoreStatus::ENVIADO_A_CAMILA->shortLabel());
        $this->assertEquals(__('Working'), CoreStatus::TO_DO_TODAY->shortLabel());
        $this->assertEquals(__('Entrante'), CoreStatus::ENTRANTE->shortLabel());
    }

    public function test_map_returns_all_statuses_with_metadata(): void
    {
        $map = CoreStatus::map();

        $this->assertCount(count(CoreStatus::cases()), $map);

        foreach (CoreStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $map);

            $meta = $map[$status->value];
            $this->assertEquals($status->value, $meta['value']);
            $this->assertEquals($status->name, $meta['name']);
            $this->assertEquals($status->label(), $meta['label']);
            $this->assertEquals($status->shortLabel(), $meta['short_label']);
            $this->assertEquals($status->color(), $meta['color']);
            $this->assertEquals($status->hexColor(), $meta['hex_color']);
            $this->assertEquals($status->badgeStyle(), $meta['badge_style']);
            $this->assertEquals($status->dotStyle(), $meta['dot_style']);
            $this->assertEquals($status->dotClass(), $meta['dot_class']);
        }
    }

    public function test_get_metadata_by_enum_and_string(): void
    {
        $metaEnum = CoreStatus::getMetadata(CoreStatus::ENVIADO_A_CAMILA);
        $this->assertNotNull($metaEnum);
        $this->assertEquals('ENVIADO A CAMILA', $metaEnum['value']);
        $this->assertEquals(__('Camila'), $metaEnum['short_label']);

        $metaString = CoreStatus::getMetadata('TO DO TODAY');
        $this->assertNotNull($metaString);
        $this->assertEquals('TO DO TODAY', $metaString['value']);
        $this->assertEquals(__('Working'), $metaString['short_label']);

        $this->assertNull(CoreStatus::getMetadata('INVALID_STATUS'));
        $this->assertNull(CoreStatus::getMetadata(null));
    }

    public function test_label_for_and_short_label_for_helpers(): void
    {
        $this->assertEquals(
            CoreStatus::ENVIADO_A_CAMILA->label(),
            CoreStatus::labelFor(CoreStatus::ENVIADO_A_CAMILA)
        );
        $this->assertEquals(
            CoreStatus::ENVIADO_A_CAMILA->label(),
            CoreStatus::labelFor('ENVIADO A CAMILA')
        );
        $this->assertEquals(
            'Default Label',
            CoreStatus::labelFor('NON_EXISTENT', 'Default Label')
        );

        $this->assertEquals(
            __('Camila'),
            CoreStatus::shortLabelFor(CoreStatus::ENVIADO_A_CAMILA)
        );
        $this->assertEquals(
            __('Camila'),
            CoreStatus::shortLabelFor('ENVIADO A CAMILA')
        );
        $this->assertEquals(
            'Default Short',
            CoreStatus::shortLabelFor('NON_EXISTENT', 'Default Short')
        );
    }

    public function test_order_model_handles_lowercase_core_status_via_cast(): void
    {
        $cast = new CoreStatusCast;
        $dummy = new Order;

        // When DB contains lowercase 'entrante', it must cast to CoreStatus::ENTRANTE
        $result = $cast->get($dummy, 'core_status', 'entrante', []);
        $this->assertSame(CoreStatus::ENTRANTE, $result);

        // When DB contains 'ENTRANTE'
        $resultUpper = $cast->get($dummy, 'core_status', 'ENTRANTE', []);
        $this->assertSame(CoreStatus::ENTRANTE, $resultUpper);

        // When setting, it must normalize to uppercase string
        $this->assertEquals('ENTRANTE', $cast->set($dummy, 'core_status', CoreStatus::ENTRANTE, []));
        $this->assertEquals('ENTRANTE', $cast->set($dummy, 'core_status', 'entrante', []));
    }
}
