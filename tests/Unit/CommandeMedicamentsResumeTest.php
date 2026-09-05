<?php

namespace Tests\Unit;

use App\Support\CommandeMedicamentsResume;
use PHPUnit\Framework\TestCase;

class CommandeMedicamentsResumeTest extends TestCase
{
    public function test_formats_designation_and_dosage(): void
    {
        $produits = [
            (object) ['designation' => 'Doliprane', 'dosage' => '500mg'],
            (object) ['designation' => 'Vitamine C', 'dosage' => null],
        ];

        $this->assertSame(
            'Doliprane 500mg, Vitamine C',
            CommandeMedicamentsResume::fromProduits($produits),
        );
    }

    public function test_returns_dash_when_empty(): void
    {
        $this->assertSame('-', CommandeMedicamentsResume::fromProduits([]));
    }
}
