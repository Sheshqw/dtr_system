<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Standard yearly Philippine regular and special non-working holidays.
 * Movable dates (Maundy Thursday, Good Friday, Black Saturday) are computed
 * from Easter Sunday via the Meeus/Jones/Butcher algorithm, so this works
 * without depending on PHP's optional ext-calendar extension.
 */
class PhilippineHolidayService
{
    /** @return array<int, array{name: string, date: string, type: string}> */
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        $holidays = [
            // Regular holidays
            ['name' => "New Year's Day",              'date' => "{$year}-01-01", 'type' => 'regular'],
            ['name' => 'Araw ng Kagitingan',           'date' => "{$year}-04-09", 'type' => 'regular'],
            ['name' => 'Maundy Thursday',              'date' => $easter->copy()->subDays(3)->toDateString(), 'type' => 'regular'],
            ['name' => 'Good Friday',                  'date' => $easter->copy()->subDays(2)->toDateString(), 'type' => 'regular'],
            ['name' => 'Labor Day',                    'date' => "{$year}-05-01", 'type' => 'regular'],
            ['name' => 'Independence Day',             'date' => "{$year}-06-12", 'type' => 'regular'],
            ['name' => 'National Heroes Day',          'date' => $this->lastMondayOfAugust($year)->toDateString(), 'type' => 'regular'],
            ['name' => 'Bonifacio Day',                'date' => "{$year}-11-30", 'type' => 'regular'],
            ['name' => 'Christmas Day',                'date' => "{$year}-12-25", 'type' => 'regular'],
            ['name' => 'Rizal Day',                    'date' => "{$year}-12-30", 'type' => 'regular'],

            // Special (non-working) holidays
            ['name' => 'Black Saturday',               'date' => $easter->copy()->subDay()->toDateString(), 'type' => 'special'],
            ['name' => 'Ninoy Aquino Day',              'date' => "{$year}-08-21", 'type' => 'special'],
            ["name" => "All Saints' Day",               'date' => "{$year}-11-01", 'type' => 'special'],
            ['name' => "All Souls' Day",                'date' => "{$year}-11-02", 'type' => 'special'],
            ['name' => 'Feast of the Immaculate Conception', 'date' => "{$year}-12-08", 'type' => 'special'],
            ['name' => 'Last Day of the Year',          'date' => "{$year}-12-31", 'type' => 'special'],
        ];

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    private function easterSunday(int $year): Carbon
    {
        // Meeus/Jones/Butcher Gregorian algorithm.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }

    private function lastMondayOfAugust(int $year): Carbon
    {
        $date = Carbon::create($year, 8, 31);
        while (! $date->isMonday()) {
            $date->subDay();
        }
        return $date;
    }
}
