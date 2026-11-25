<?php

namespace Tests\Unit\Domain;

use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryEnumsTest extends TestCase
{
    /**
     * @return list<class-string<\BackedEnum>>
     */
    private static function domainEnums(): array
    {
        $enums = [];
        foreach (glob(dirname(__DIR__, 3).'/app/Domain/Delivery/{Enums,Workflow}/*.php', GLOB_BRACE) as $file) {
            $directory = basename(dirname($file));
            $class = 'App\\Domain\\Delivery\\'.$directory.'\\'.basename($file, '.php');
            if (enum_exists($class)) {
                $enums[] = $class;
            }
        }

        return $enums;
    }

    /**
     * @return array<string, array{0: class-string<\BackedEnum>, 1: string}>
     */
    public static function localeCases(): array
    {
        $cases = [];
        foreach (self::domainEnums() as $enum) {
            foreach (['en', 'es'] as $locale) {
                $cases[class_basename($enum).' '.$locale] = [$enum, $locale];
            }
        }

        return $cases;
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    #[DataProvider('localeCases')]
    public function test_every_case_has_a_label_in_both_locales(string $enum, string $locale): void
    {
        app()->setLocale($locale);

        foreach ($enum::cases() as $case) {
            $label = $case->label();

            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('enums.', $label, "{$enum}::{$case->name} has no {$locale} label");
        }
    }

    public function test_spanish_and_english_labels_differ_for_the_statuses(): void
    {
        app()->setLocale('en');
        $english = DeliverableStatus::IN_DESIGN->label();
        app()->setLocale('es');

        $this->assertSame('In design', $english);
        $this->assertSame('En diseño', DeliverableStatus::IN_DESIGN->label());
    }

    public function test_there_are_twelve_statuses_in_flow_order(): void
    {
        $codes = array_map(fn (DeliverableStatus $status): string => $status->value, DeliverableStatus::cases());

        $this->assertSame([
            'intake', 'ready', 'in_design', 'internal_review', 'client_review', 'production_files',
            'ready_for_production', 'production_queue', 'in_production', 'produced', 'fulfillment', 'done',
        ], $codes);
        $this->assertSame(range(1, 12), array_map(fn (DeliverableStatus $status): int => $status->position(), DeliverableStatus::cases()));
    }

    public function test_statuses_belong_to_the_three_areas(): void
    {
        $this->assertSame(Area::DESIGN, DeliverableStatus::READY_FOR_PRODUCTION->area());
        $this->assertSame(Area::PRODUCTION, DeliverableStatus::PRODUCTION_QUEUE->area());
        $this->assertSame(Area::PRODUCTION, DeliverableStatus::PRODUCED->area());
        $this->assertSame(Area::FULFILLMENT, DeliverableStatus::FULFILLMENT->area());
        $this->assertSame(Area::FULFILLMENT, DeliverableStatus::DONE->area());
        $this->assertCount(7, DeliverableStatus::inArea(Area::DESIGN));
        $this->assertCount(3, DeliverableStatus::inArea(Area::PRODUCTION));
        $this->assertCount(2, DeliverableStatus::inArea(Area::FULFILLMENT));
    }

    public function test_only_done_is_closed(): void
    {
        $closed = array_filter(DeliverableStatus::cases(), fn (DeliverableStatus $status): bool => $status->isClosed());

        $this->assertSame([DeliverableStatus::DONE], array_values($closed));
    }

    public function test_transition_reasons_and_customer_service_role_exist(): void
    {
        $this->assertSame(
            ['manual', 'task_completed', 'schedule', 'daily_cycle', 'import', 'system'],
            array_map(fn (TransitionReason $reason): string => $reason->value, TransitionReason::cases()),
        );
        $this->assertSame('customer_service', UserRole::CUSTOMER_SERVICE->value);
    }
}
