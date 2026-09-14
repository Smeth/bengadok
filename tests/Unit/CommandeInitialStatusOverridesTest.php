<?php

namespace Tests\Unit;

use App\Models\Commande;
use App\Support\CommandeInitialStatusOverrides;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommandeInitialStatusOverridesTest extends TestCase
{
    #[Test]
    public function it_builds_livree_overrides_with_custom_date(): void
    {
        Carbon::setTestNow('2026-09-10 14:00:00');

        $overrides = CommandeInitialStatusOverrides::fromManualEntry(
            'retiree',
            '2026-01-15',
            '09:30',
        );

        $this->assertSame('retiree', $overrides['status']);
        $this->assertSame(Commande::STATUT_PHARMACIE_CA_COMPTABILISE, $overrides['status_pharmacie']);
        $this->assertSame('09:30', $overrides['heurs']);
        $this->assertSame('2026-01-15', $overrides['date']->toDateString());
        $this->assertSame('2026-01-15', $overrides['livree_at']->toDateString());

        Carbon::setTestNow();
    }
}
