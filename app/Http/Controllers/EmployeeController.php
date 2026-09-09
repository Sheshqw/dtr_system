<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $query = User::with('department', 'shift', 'campus')
            ->orderBy('last_name');

        $this->campusScope($request, $query);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('employee_id', 'like', "%{$s}%")
                ->orWhere('position', 'like', "%{$s}%")
            );
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Paginated response when ?page= is present; plain array otherwise (backwards-compat)
        if ($request->has('page')) {
            $perPage   = max(1, min(100, (int) $request->get('per_page', 25)));
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->map(fn(User $e) => $this->format($e))->values(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'from'         => $paginated->firstItem() ?? 0,
                    'to'           => $paginated->lastItem() ?? 0,
                ],
            ]);
        }

        $employees = $query->get()->map(fn(User $e) => $this->format($e));

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $this->requirePrivileged($request);

        $allowedRoles = $this->assignableRoles($request);

        $data = $request->validate([
            'employee_id'       => 'required|string|max:20|unique:employees',
            'first_name'        => 'required|string|max:80',
            'last_name'         => 'required|string|max:80',
            'middle_name'       => 'nullable|string|max:80',
            'suffix'            => 'nullable|string|max:10',
            'gender'            => 'nullable|in:male,female',
            'civil_status'      => 'nullable|in:single,married,widowed,separated',
            'email'             => 'nullable|email|max:120|unique:employees',
            'password'          => ['nullable', 'string', Password::default()],
            'role'              => ['required', Rule::in($allowedRoles)],
            'campus_id'         => 'nullable|exists:campuses,id',
            'department_id'     => 'nullable|exists:departments,id',
            'shift_id'          => 'nullable|exists:shifts,id',
            'position'          => 'nullable|string|max:100',
            'employment_status' => 'nullable|in:permanent,casual,contractual,part-time',
            'staff_type'        => 'nullable|in:faculty,administrative,security,custodial,maintenance,laboratory,driver,other',
            'contact_number'    => 'nullable|string|max:30',
            'date_of_birth'     => 'nullable|date',
            'date_hired'        => 'nullable|date',
            'gsis_number'       => 'nullable|string|max:30',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number'    => 'nullable|string|max:20',
            'tin_number'        => 'nullable|string|max:20',
            'fingerprint_id'    => 'nullable|string|max:20|unique:employees',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Non-super_admin always creates within their own campus
        if (! $this->isSuperAdmin($request)) {
            $data['campus_id'] = $request->user()->campus_id;
        }

        $employee = User::create($data);

        AuditLog::record('CREATE', 'employees', $employee->id, null, $employee->toArray());

        return response()->json($this->format($employee->load('department', 'shift', 'campus')), 201);
    }

    public function update(Request $request, User $employee)
    {
        $this->requirePrivileged($request);
        $this->assertSameCampus($request, $employee->campus_id);

        $allowedRoles = $this->assignableRoles($request);

        $data = $request->validate([
            'employee_id'       => ['sometimes', 'string', 'max:20', Rule::unique('employees')->ignore($employee->id)],
            'first_name'        => 'sometimes|string|max:80',
            'last_name'         => 'sometimes|string|max:80',
            'middle_name'       => 'nullable|string|max:80',
            'suffix'            => 'nullable|string|max:10',
            'gender'            => 'nullable|in:male,female',
            'civil_status'      => 'nullable|in:single,married,widowed,separated',
            'email'             => ['nullable', 'email', 'max:120', Rule::unique('employees')->ignore($employee->id)],
            'password'          => ['nullable', 'string', Password::default()],
            'role'              => ['sometimes', Rule::in($allowedRoles)],
            'campus_id'         => 'nullable|exists:campuses,id',
            'department_id'     => 'nullable|exists:departments,id',
            'shift_id'          => 'nullable|exists:shifts,id',
            'position'          => 'nullable|string|max:100',
            'employment_status' => 'nullable|in:permanent,casual,contractual,part-time',
            'staff_type'        => 'nullable|in:faculty,administrative,security,custodial,maintenance,laboratory,driver,other',
            'contact_number'    => 'nullable|string|max:30',
            'date_of_birth'     => 'nullable|date',
            'date_hired'        => 'nullable|date',
            'gsis_number'       => 'nullable|string|max:30',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number'    => 'nullable|string|max:20',
            'tin_number'        => 'nullable|string|max:20',
            'fingerprint_id'    => ['nullable', 'string', 'max:20', Rule::unique('employees')->ignore($employee->id)],
            'is_active'         => 'sometimes|boolean',
        ]);

        // Prevent non-super_admin from moving employees to another campus
        if (! $this->isSuperAdmin($request)) {
            unset($data['campus_id']);
        }

        $old = $employee->toArray();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $employee->update($data);

        AuditLog::record('UPDATE', 'employees', $employee->id, $old, $employee->fresh()->toArray());

        return response()->json($this->format($employee->load('department', 'shift', 'campus')));
    }

    public function destroy(Request $request, User $employee)
    {
        $this->requirePrivileged($request);
        $this->assertSameCampus($request, $employee->campus_id);

        $employee->update(['is_active' => false]);
        AuditLog::record('DEACTIVATE', 'employees', $employee->id, null, null);

        return response()->json(['message' => 'Employee deactivated.']);
    }

    public function departments(Request $request)
    {
        $query = Department::orderBy('name');

        if (! $this->isSuperAdmin($request)) {
            $query->where('campus_id', $request->user()->campus_id);
        } elseif ($request->filled('campus_id')) {
            $query->where('campus_id', $request->campus_id);
        }

        return response()->json($query->get(['id', 'name', 'code', 'campus_id']));
    }

    public function shifts()
    {
        return response()->json(Shift::orderBy('name')->get());
    }

    public function uploadPhoto(Request $request, User $employee): \Illuminate\Http\JsonResponse
    {
        $this->requirePrivileged($request);
        $this->assertSameCampus($request, $employee->campus_id);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($employee->photo_path && Storage::disk('public')->exists($employee->photo_path)) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $path = $request->file('photo')->store('photos', 'public');
        $employee->update(['photo_path' => $path]);

        return response()->json([
            'photo_path' => $path,
            'photo_url'  => Storage::url($path),
        ]);
    }

    private function requirePrivileged(Request $request): void
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only Super Admin, Admin, or HR Officer can manage employee accounts.');
        }
    }

    /**
     * Roles the acting user is allowed to assign to an employee. Prevents an
     * HR Officer from promoting an account to admin/super_admin.
     */
    private function assignableRoles(Request $request): array
    {
        return match ($request->user()->role) {
            'super_admin' => ['super_admin', 'admin', 'hr_officer', 'department_head', 'employee'],
            'admin'       => ['admin', 'hr_officer', 'department_head', 'employee'],
            default       => ['hr_officer', 'department_head', 'employee'],
        };
    }

    private function format(User $e): array
    {
        return [
            'id'                => $e->id,
            'employee_id'       => $e->employee_id,
            'name'              => $e->full_name,
            'first_name'        => $e->first_name,
            'last_name'         => $e->last_name,
            'middle_name'       => $e->middle_name,
            'suffix'            => $e->suffix,
            'gender'            => $e->gender,
            'civil_status'      => $e->civil_status,
            'email'             => $e->email,
            'role'              => $e->role,
            'position'          => $e->position,
            'employment_status' => $e->employment_status,
            'staff_type'        => $e->staff_type,
            'contact_number'    => $e->contact_number,
            'date_of_birth'     => $e->date_of_birth?->format('Y-m-d'),
            'date_hired'        => $e->date_hired?->format('Y-m-d'),
            'gsis_number'       => $e->gsis_number,
            'philhealth_number' => $e->philhealth_number,
            'pagibig_number'    => $e->pagibig_number,
            'tin_number'        => $e->tin_number,
            'is_active'         => $e->is_active,
            'campus_id'         => $e->campus_id,
            'campus'            => $e->campus?->name,
            'department_id'     => $e->department_id,
            'department'        => $e->department?->name,
            'shift_id'          => $e->shift_id,
            'shift'             => $e->shift?->name,
            'fingerprint_id'    => $e->fingerprint_id,
            'photo_path'        => $e->photo_path,
        ];
    }
}
