<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $query = Department::withCount('employees')
            ->with(['head:id,first_name,last_name,middle_name', 'campus:id,name'])
            ->orderBy('name');

        $this->campusScope($request, $query);

        return response()->json(
            $query->get()->map(fn($d) => $this->format($d))
        );
    }

    public function store(Request $request)
    {
        $this->gate($request->user());

        $campusId = $this->resolvedCampusId($request) ?? $request->user()->campus_id;

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255',
                Rule::unique('departments')->where('campus_id', $campusId)],
            'code'      => ['required', 'string', 'max:20',
                Rule::unique('departments')->where('campus_id', $campusId)],
            'head_id'   => 'nullable|exists:employees,id',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        $data['campus_id'] = $campusId;

        $dept = Department::create($data);
        return response()->json($this->format($dept->load('head')->loadCount('employees')), 201);
    }

    public function update(Request $request, Department $department)
    {
        $this->gate($request->user());
        $this->assertSameCampus($request, $department->campus_id);

        $campusId = $department->campus_id;

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255',
                Rule::unique('departments')->where('campus_id', $campusId)->ignore($department->id)],
            'code'    => ['required', 'string', 'max:20',
                Rule::unique('departments')->where('campus_id', $campusId)->ignore($department->id)],
            'head_id' => 'nullable|exists:employees,id',
        ]);

        $department->update($data);
        return response()->json($this->format($department->fresh(['head'])->loadCount('employees')));
    }

    public function destroy(Request $request, Department $department)
    {
        $this->gate($request->user());
        $this->assertSameCampus($request, $department->campus_id);

        if ($department->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete a department with assigned employees.'], 422);
        }

        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }

    private function format(Department $d): array
    {
        return [
            'id'              => $d->id,
            'name'            => $d->name,
            'code'            => $d->code,
            'campus_id'       => $d->campus_id,
            'campus_name'     => $d->campus?->name,
            'employees_count' => $d->employees_count ?? 0,
            'head'            => $d->head ? ['id' => $d->head->id, 'name' => $d->head->name] : null,
        ];
    }

    private function gate(\App\Models\User $user): void
    {
        if (! in_array($user->role, ['super_admin', 'hr_officer'])) {
            abort(403, 'Only a Super Admin or HR Officer can manage departments.');
        }
    }
}
