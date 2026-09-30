<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255|unique:shifts,name',
            'staff_type'           => 'nullable|string|in:faculty,administrative,security,custodial,maintenance,laboratory,driver,other',
            'morning_in'           => 'required|date_format:H:i',
            'morning_out'          => 'required|date_format:H:i',
            'afternoon_in'         => 'required|date_format:H:i',
            'afternoon_out'        => 'required|date_format:H:i',
            'grace_period_minutes' => 'required|integer|min:0|max:60',
            'required_hours'       => 'required|numeric|min:1|max:12',
        ]);

        return response()->json(Shift::create($data)->loadCount('employees'), 201);
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255|unique:shifts,name,' . $shift->id,
            'staff_type'           => 'nullable|string|in:faculty,administrative,security,custodial,maintenance,laboratory,driver,other',
            'morning_in'           => 'required|date_format:H:i',
            'morning_out'          => 'required|date_format:H:i',
            'afternoon_in'         => 'required|date_format:H:i',
            'afternoon_out'        => 'required|date_format:H:i',
            'grace_period_minutes' => 'required|integer|min:0|max:60',
            'required_hours'       => 'required|numeric|min:1|max:12',
        ]);

        $shift->update($data);
        return response()->json($shift->fresh()->loadCount('employees'));
    }

    public function destroy(Shift $shift)
    {
        if ($shift->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete a shift with assigned employees.'], 422);
        }

        $shift->delete();
        return response()->json(['message' => 'Shift deleted.']);
    }
}
