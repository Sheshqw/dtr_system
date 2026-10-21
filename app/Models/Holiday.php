<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'type', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** How the holiday appears on a DTR row, e.g. "All Saints' Day (Special Holiday)". */
    public function label(): string
    {
        return $this->type
            ? "{$this->name} (" . ucwords(str_replace('_', ' ', $this->type)) . ' Holiday)'
            : $this->name;
    }

    /**
     * Holidays declared between two dates, keyed by 'Y-m-d', so each DTR day
     * can look up its own holiday.
     *
     * @return array<string, self>
     */
    public static function keyedBetween(string $from, string $to): array
    {
        return static::whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->keyBy(fn (self $h) => $h->date->toDateString())
            ->all();
    }
}
