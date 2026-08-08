<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
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

        $holiday->update($data);

        return response()->json([
            'id'   => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->format('Y-m-d'),
            'type' => $holiday->type,
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $this->gate(request()->user());
        $holiday->delete();
        return response()->json(['message' => 'Holiday deleted.']);
    }

    private function gate(\App\Models\User $user): void
    {
        if (! in_array($user->role, ['super_admin', 'admin', 'hr_officer'])) {
            abort(403, 'Only Super Admin, Admin, or HR Officer can manage holidays.');
        }
    }
}
