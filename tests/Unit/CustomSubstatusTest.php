<?php

namespace Tests\Unit;

use App\Casts\SubstatusCast;
use App\Enums\Substatus;
use App\Models\Order;
use App\Support\CustomSubstatus;
use PHPUnit\Framework\TestCase;

class CustomSubstatusTest extends TestCase
{
    public function test_custom_substatus_methods(): void
    {
        $custom = new CustomSubstatus('CAMBIOS CAMILA');

        $this->assertEquals('CAMBIOS CAMILA', $custom->value);
        $this->assertEquals('CAMBIOS CAMILA', $custom->label());
        $this->assertEquals('CAMBIOS CAMILA', (string) $custom);
        $this->assertFalse($custom->isGlobal());
        $this->assertNull($custom->defaultCoreStatus());
        $this->assertNotEmpty($custom->badgeStyle());
        $this->assertEquals('', $custom->getInlineBadgeStyle());
    }

    public function test_substatus_cast_handles_enums_and_strings(): void
    {
        $cast = new SubstatusCast;
        $model = new Order;

        $this->assertNull($cast->get($model, 'substatus', null, []));
        $this->assertNull($cast->get($model, 'substatus', '', []));

        $enumResult = $cast->get($model, 'substatus', 'URGENTE', []);
        $this->assertInstanceOf(Substatus::class, $enumResult);
        $this->assertEquals(Substatus::URGENTE, $enumResult);

        $customResult = $cast->get($model, 'substatus', 'CAMBIOS CAMILA', []);
        $this->assertInstanceOf(CustomSubstatus::class, $customResult);
        $this->assertEquals('CAMBIOS CAMILA', $customResult->value);
    }
}
