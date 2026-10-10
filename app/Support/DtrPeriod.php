<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The span of days one DTR covers: the whole month, or — for semi-monthly
 * (COS / Job Order) employees — the 1st half (days 1–15) or 2nd half
 * (16th to month end). Every place that renders or authorizes a DTR (Form 48
 * PDF, preview, CSV, print consents) goes through this so they always agree
 * on the dates and the label.
 */
final class DtrPeriod
{
    public const FULL   = 'full';
    public const FIRST  = 'first';
    public const SECOND = 'second';

    public const KEYS = [self::FULL, self::FIRST, self::SECOND];

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly string $key = self::FULL,
    ) {
    }

    public function isHalfMonth(): bool
    {
        return $this->key !== self::FULL;
    }

    public function startDay(): int
    {
        return $this->key === self::SECOND ? 16 : 1;
    }

    public function endDay(): int
    {
        return $this->key === self::FIRST
            ? 15
            : Carbon::create($this->year, $this->month)->daysInMonth;
    }

    public function start(): Carbon
    {
        return Carbon::create($this->year, $this->month, $this->startDay())->startOfDay();
    }

    public function end(): Carbon
    {
        return Carbon::create($this->year, $this->month, $this->endDay())->startOfDay();
    }

    /** "October 2026" or "October 1–15, 2026". */
    public function label(): string
    {
        $monthName = Carbon::create($this->year, $this->month)->format('F');

        return $this->isHalfMonth()
            ? "{$monthName} {$this->startDay()}–{$this->endDay()}, {$this->year}"
            : "{$monthName} {$this->year}";
    }

    /** "_1-15" / "_16-31" for half-month downloads, empty for the full month. */
    public function filenameSuffix(): string
    {
        return $this->isHalfMonth() ? "_{$this->startDay()}-{$this->endDay()}" : '';
    }

    public function toArray(): array
    {
        return [
            'key'       => $this->key,
            'month'     => $this->month,
            'year'      => $this->year,
            'start_day' => $this->startDay(),
            'end_day'   => $this->endDay(),
            'label'     => $this->label(),
        ];
    }
}
