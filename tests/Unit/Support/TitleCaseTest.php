<?php

namespace Tests\Unit\Support;

use App\Support\TitleCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TitleCaseTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'plain company' => ['KUDOS PRINT MEDIA', 'Kudos Print Media'],
            'numbers and dashes' => ['TALPA 14 MARIETTA - PODWER SPRING', 'Talpa 14 Marietta - Podwer Spring'],
            'dotted acronym' => ['S.R.S REMODELING GROUP', 'S.R.S Remodeling Group'],
            'known acronym' => ['NORTH GEORGIA CLEANING LLC', 'North Georgia Cleaning LLC'],
            'spanish small words and accents' => ['PANADERÍA SOL DE MAYO', 'Panadería Sol de Mayo'],
            'apostrophe' => ["FRIDA'S RESTAURANT", "Frida's Restaurant"],
            'hyphen, slash and parentheses' => ['COCA-COLA / PEPSI (MAIN)', 'Coca-Cola / Pepsi (Main)'],
            'mixed case is left alone' => ['Already Mixed Case', 'Already Mixed Case'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('names')]
    public function test_it_formats_uppercase_names_in_smart_title_case(string $stored, string $displayed): void
    {
        $this->assertSame($displayed, TitleCase::from($stored));
    }
}
