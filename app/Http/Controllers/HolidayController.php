<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\User;
use App\Services\PhilippineHolidayService;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(
        private PhilippineHolidayService $phHolidays,
        private WorkingHoursService $wh,
    ) {}

    /**
     * A holiday has no attendance punches by design (no office work), so
     * without this, the day simply never gets a daily_dtr row and shows up
     * as blank/absent instead of "Holiday" everywhere it's displayed.
     */
    private function markHolidayForAllEmployees(string $date): void
    {
        User::where('is_active', true)->chunk(200, function ($employees) use ($date) {
            foreach ($employees as $employee) {
                $this->wh->computeDailyDtr($employee, $date);
            }
        });
    }


    public function index(Request $request)
    {
        $year = $request->integer('year', now()->year);

        return response()->json(
            Holiday::whereYear('date', $year)
                ->orderBy('date')
                ->get()
                ->map(fn($h) => [
                    'id'   => $h->id,
                    'name' => $h->name,
                    'date' => $h->date->format('Y-m-d'),
                    'type' => $h->type,
                ])
        );
    }

    public function store(Request $request)
    {
        $this->gate($request->user());

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date|unique:holidays,date',
            'type' => 'required|in:regular,special',
        ]);

        $data['created_by'] = $request->user()->id;
        $holiday = Holiday::create($data);

        $this->markHolidayForAllEmployees($holiday->date->toDateString());

        return response()->json([
            'id'   => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->format('Y-m-d'),
            'type' => $holiday->type,
        ], 201);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $this->gate($request->user());

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date|unique:holidays,date,' . $holiday->id,
            'type' => 'required|in:regular,special',
        ]);

        $oldDate = $holiday->date->toDateString();
        $holiday->update($data);
        $newDate = $holiday->date->toDateString();

        if ($oldDate !== $newDate) {
            $this->markHolidayForAllEmployees($oldDate); // no longer a holiday — recompute back to normal
        }
        $this->markHolidayForAllEmployees($newDate);

        return response()->json([
            'id'   => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->format('Y-m-d'),
            'type' => $holiday->type,
        ]);
    }

    /**
     * Seed the standard Philippine regular and special non-working holidays
     * for a given year. Safe to re-run — existing dates are left untouched.
     */
    public function autofill(Request $request)
    {
        $this->gate($request->user());

        $year = $request->integer('year', now()->year);

        $created = [];
        foreach ($this->phHolidays->forYear($year) as $h) {
            $holiday = Holiday::firstOrCreate(
                ['date' => $h['date']],
                ['name' => $h['name'], 'type' => $h['type'], 'created_by' => $request->user()->id]
            );
            if ($holiday->wasRecentlyCreated) {
                $created[] = $holiday;
                $this->markHolidayForAllEmployees($holiday->date->toDateString());
            }
        }

        return response()->json([
            'message' => count($created) . ' holiday(s) added for ' . $year . '.',
            'created' => collect($created)->map(fn($h) => [
                'id' => $h->id, 'name' => $h->name, 'date' => $h->date->format('Y-m-d'), 'type' => $h->type,
            ]),
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $this->gate(request()->user());
        $date = $holiday->date->toDateString();
        $holiday->delete();
        $this->markHolidayForAllEmployees($date); // recompute back to normal (absent/present/weekend)
        return response()->json(['message' => 'Holiday deleted.']);
    }

    private function gate(\App\Models\User $user): void
    {
        if (! in_array($user->role, ['super_admin', 'admin', 'hr_officer'])) {
            abort(403, 'Only Super Admin, Admin, or HR Officer can manage holidays.');
        }
    }
}
