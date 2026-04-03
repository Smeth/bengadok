<?php

namespace App\Services\Ordonnances;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrdonnancePrescriptionDateParser
{
    /**
     * @return array<int, Carbon>
     */
    public function parseAllDatesFromText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $found = [];

        if (preg_match_all('/\b(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $c = $this->fromDayMonthYear((int) $m[1], (int) $m[2], (int) $m[3]);
                if ($c !== null) {
                    $found[] = $c;
                }
            }
        }

        if (preg_match_all('/\b(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $c = $this->fromYearMonthDay((int) $m[1], (int) $m[2], (int) $m[3]);
                if ($c !== null) {
                    $found[] = $c;
                }
            }
        }

        return $this->uniqueByDay($found);
    }

    /**
     * Choisit la date la plus probable (la plus récente parmi les dates passées ou aujourd’hui).
     */
    public function pickPrescriptionDate(array $dates, CarbonInterface $today): ?Carbon
    {
        if ($dates === []) {
            return null;
        }

        $pastOrToday = array_values(array_filter(
            $dates,
            fn (Carbon $d) => $d->startOfDay()->lte($today->copy()->startOfDay())
        ));

        if ($pastOrToday !== []) {
            usort($pastOrToday, fn (Carbon $a, Carbon $b) => $b->timestamp <=> $a->timestamp);

            return $pastOrToday[0]->copy()->startOfDay();
        }

        usort($dates, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return $dates[0]->copy()->startOfDay();
    }

    private function fromDayMonthYear(int $d, int $m, int $y): ?Carbon
    {
        if ($y < 100) {
            $y = $y < 70 ? 2000 + $y : 1900 + $y;
        }
        if (checkdate($m, $d, $y)) {
            return Carbon::createFromDate($y, $m, $d)->startOfDay();
        }

        return null;
    }

    private function fromYearMonthDay(int $y, int $m, int $d): ?Carbon
    {
        if ($y < 100) {
            $y = $y < 70 ? 2000 + $y : 1900 + $y;
        }
        if (checkdate($m, $d, $y)) {
            return Carbon::createFromDate($y, $m, $d)->startOfDay();
        }

        return null;
    }

    /**
     * @param  array<int, Carbon>  $dates
     * @return array<int, Carbon>
     */
    private function uniqueByDay(array $dates): array
    {
        $by = [];
        foreach ($dates as $d) {
            $key = $d->format('Y-m-d');
            if (! isset($by[$key])) {
                $by[$key] = $d;
            }
        }

        return array_values($by);
    }
}
