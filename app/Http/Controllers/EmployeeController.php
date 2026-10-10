<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Department;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Rap2hpoutre\FastExcel\FastExcel;

class EmployeeController extends Controller
{
    use ScopesByCampus;

    private function filteredQuery(Request $request)
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

        if ($request->filled('staff_type')) {
            $query->where('staff_type', $request->staff_type);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        // Paginated response when ?page= is present; plain array otherwise (backwards-compat)
        if ($request->has('page')) {
            $perPage   = max(1, min(100, (int) $request->get('per_page', 25)));
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->map(fn(User $e) => $this->format($e, $request))->values(),
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

        $employees = $query->get()->map(fn(User $e) => $this->format($e, $request));

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
            'designation'       => 'nullable|string|max:100',
            'salary'            => 'nullable|numeric|min:0|max:99999999.99',
            'employment_status' => 'nullable|in:permanent,job_order,cos,part-time,sa,ojt',
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

        return response()->json($this->format($employee->load('department', 'shift', 'campus'), $request), 201);
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
            'designation'       => 'nullable|string|max:100',
            'salary'            => 'nullable|numeric|min:0|max:99999999.99',
            'employment_status' => 'nullable|in:permanent,job_order,cos,part-time,sa,ojt',
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

        return response()->json($this->format($employee->load('department', 'shift', 'campus'), $request));
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

    /**
     * Export the current filtered employee list as a real .xlsx workbook.
     * GET /api/employees/export/excel
     */
    public function exportExcel(Request $request)
    {
        $includeSalary = $this->isPrivileged($request);

        $rows = $this->filteredQuery($request)->get()->map(fn(User $e) => array_filter([
            'Employee ID'       => $e->employee_id,
            'Last Name'         => $e->last_name,
            'First Name'        => $e->first_name,
            'Middle Name'       => $e->middle_name,
            'Gender'            => $e->gender ? ucfirst($e->gender) : null,
            'Age'               => $e->age,
            'Email'             => $e->email,
            'Role'              => $e->role,
            'Campus'            => $e->campus?->name,
            'Department'        => $e->department?->name,
            'Position'          => $e->position,
            'Designation'       => $e->designation,
            'Monthly Salary'    => $includeSalary ? $e->salary : null,
            'Staff Type'        => $e->staff_type,
            'Employment Status' => $e->employment_status,
            'Date Hired'        => $e->date_hired?->format('Y-m-d'),
            'Status'            => $e->is_active ? 'Active' : 'Inactive',
        ], fn($v, $k) => $k !== 'Monthly Salary' || $includeSalary, ARRAY_FILTER_USE_BOTH));

        return (new FastExcel($rows))->download('employees_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * Export the current filtered employee list as a printable PDF.
     * GET /api/employees/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $employees = $this->filteredQuery($request)->get();

        $pdf = Pdf::loadView('reports.employees_list', [
            'employees'     => $employees,
            'includeSalary' => $this->isPrivileged($request),
            'generated'     => now()->format('F j, Y g:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('employees_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Blank starter workbook for bulk-import, headers only + one example row.
     * GET /api/employees/import/template
     */
    public function importTemplate(Request $request)
    {
        $this->requirePrivileged($request);

        $rows = collect([[
            'Employee ID'       => 'EMP-2026-001',
            'Last Name'         => 'Dela Cruz',
            'First Name'        => 'Juan',
            'Middle Name'       => 'Santos',
            'Gender'            => 'Male',
            'Birthdate'         => '1990-05-14',
            'Email'             => 'jdelacruz@nemsu.edu.ph',
            'Password'          => '',
            'Role'              => 'employee',
            'Campus'            => 'Cantilan Campus',
            'Department'        => 'Department of Computer Studies',
            'Position'          => 'Instructor I',
            'Designation'       => '',
            'Monthly Salary'    => '25000',
            'Staff Type'        => 'faculty',
            'Employment Status' => 'permanent',
            'Date Hired'        => '2026-01-01',
        ]]);

        return (new FastExcel($rows))->download('employee_import_template.xlsx');
    }

    /**
     * Dry-run an uploaded import file: resolve every row the same way
     * import() would, but write nothing. Lets the admin review exactly
     * what will happen — who gets created and why any row would be
     * skipped — before committing.
     * POST /api/employees/import/preview
     */
    public function importPreview(Request $request)
    {
        $this->requirePrivileged($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        [$rows, $ctx] = $this->prepareImport($request);

        $preview    = [];
        $seenIds    = [];
        $seenEmails = [];

        foreach ($rows as $i => $row) {
            $resolved = $this->resolveImportRow($row, $ctx, false, $seenIds, $seenEmails);

            $preview[] = [
                'row'         => $i + 2, // header occupies row 1
                'employee_id' => $resolved['employee_id'],
                'name'        => $resolved['name'] !== '' ? $resolved['name'] : null,
                'role'        => $resolved['role'],
                'campus'      => $resolved['campus'],
                'department'  => $resolved['department'],
                'valid'       => $resolved['ok'],
                'error'       => $resolved['error'],
            ];
        }

        $validCount = count(array_filter($preview, fn($r) => $r['valid']));

        return response()->json([
            'rows'          => $preview,
            'total'         => count($preview),
            'valid_count'   => $validCount,
            'invalid_count' => count($preview) - $validCount,
        ]);
    }

    /**
     * Bulk-create employees from an uploaded .xlsx/.csv built on the template
     * above. Bad rows are skipped and reported rather than failing the whole
     * batch — a typo in row 40 shouldn't cost the 39 good rows before it.
     * Row-by-row logic is identical to importPreview() (both call
     * resolveImportRow()) so what the admin previewed is exactly what happens.
     *
     * Validated rows are inserted in chunks via the query builder instead of
     * one Eloquent User::create() per row, and passwords are hashed once per
     * distinct value (in practice: once, since auto-generated rows share one
     * batch password — see prepareImport()) instead of once per row. Together
     * these are what take a 2,600-row import from several minutes to a few
     * seconds; see EmployeeImportPerformanceTest for the before/after case
     * this guards against regressing.
     * POST /api/employees/import
     */
    public function import(Request $request)
    {
        $this->requirePrivileged($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        [$rows, $ctx] = $this->prepareImport($request);

        $errors      = [];
        $seenIds     = [];
        $seenEmails  = [];
        $resolvedOk  = [];

        foreach ($rows as $i => $row) {
            $rowNum   = $i + 2; // header occupies row 1
            $resolved = $this->resolveImportRow($row, $ctx, true, $seenIds, $seenEmails);

            if (! $resolved['ok']) {
                $errors[] = "Row {$rowNum}: {$resolved['error']}";
                continue;
            }

            $resolvedOk[] = $resolved;
        }

        $imported      = 0;
        $usedDefault   = 0;
        $now           = now();

        if ($resolvedOk) {
            $defaultHash = Hash::make($ctx['defaultPassword']);
            $customHashCache = [];

            $insertRows = [];
            foreach ($resolvedOk as $resolved) {
                $data = $resolved['data'];

                if ($resolved['auto_generated']) {
                    $data['password'] = $defaultHash;
                    $usedDefault++;
                } else {
                    $pw = $resolved['plain_password'];
                    $customHashCache[$pw] ??= Hash::make($pw);
                    $data['password'] = $customHashCache[$pw];
                }

                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $insertRows[] = $data;
            }

            DB::transaction(function () use ($insertRows) {
                foreach (array_chunk($insertRows, 500) as $chunk) {
                    DB::table('employees')->insert($chunk);
                }
            });

            // One audit entry per created employee, same as the single-create
            // path (AuditLog::record), but batched the same way the inserts
            // above are — looking the new ids back up by employee_id (unique)
            // since a bulk insert doesn't return one id per row.
            $idMap = DB::table('employees')
                ->whereIn('employee_id', array_column($insertRows, 'employee_id'))
                ->pluck('id', 'employee_id');

            $auditRows = array_map(function ($data) use ($idMap, $request, $now) {
                $logged = $data;
                unset($logged['password']);
                $logged['id'] = $idMap[$data['employee_id']] ?? null;

                return [
                    'user_id'        => $request->user()->id,
                    'action'         => 'CREATE',
                    'table_affected' => 'employees',
                    'record_id'      => $idMap[$data['employee_id']] ?? null,
                    'old_value'      => null,
                    'new_value'      => json_encode($logged),
                    'reason'         => null,
                    'ip_address'     => $request->ip(),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }, $insertRows);

            foreach (array_chunk($auditRows, 500) as $chunk) {
                DB::table('audit_logs')->insert($chunk);
            }

            $imported = count($insertRows);
        }

        return response()->json([
            'imported'               => $imported,
            'failed'                 => count($errors),
            'errors'                 => $errors,
            'default_password'       => $usedDefault > 0 ? $ctx['defaultPassword'] : null,
            'default_password_count' => $usedDefault,
        ]);
    }

    /**
     * Parse the uploaded workbook and build the lookup context shared by
     * importPreview() and import() — computed once per request rather than
     * once per row. At 2,600+ rows, "once per row" for the uniqueness checks
     * and Campus/Department lookups below would mean several thousand extra
     * queries; preloading them here turns that into a handful.
     */
    private function prepareImport(Request $request): array
    {
        $isSuperAdmin   = $this->isSuperAdmin($request);
        $lockedCampusId = $isSuperAdmin ? null : $request->user()->campus_id;

        $rows = (new FastExcel)->import($request->file('file')->getRealPath());

        $fileEmployeeIds = $rows->map(fn($r) => trim((string) ($r['Employee ID'] ?? '')))->filter()->unique()->values();
        $fileEmails      = $rows->map(fn($r) => trim((string) ($r['Email'] ?? '')))->filter()->unique()->values();

        $minPasswordLength = max(12, SystemSetting::current()->password_min_length);

        // One shared temporary password for every auto-generated row in this
        // batch, rather than a unique one per row — the point isn't to reuse
        // a password long-term, it's to avoid computing a fresh bcrypt hash
        // (hundreds of ms each) per employee when hundreds or thousands are
        // being imported at once. Validated once against the same policy a
        // per-row generated password would have had to pass.
        do {
            $defaultPassword = Str::password($minPasswordLength);
        } while (Validator::make(['password' => $defaultPassword], ['password' => ['string', Password::default()]])->fails());

        $ctx = [
            'allowedRoles'            => $this->assignableRoles($request),
            'allowedStaffTypes'       => ['faculty', 'administrative', 'security', 'custodial', 'maintenance', 'laboratory', 'driver', 'other'],
            'allowedEmploymentStatus' => ['permanent', 'job_order', 'cos', 'part-time', 'sa', 'ojt'],
            'lockedCampusId'          => $lockedCampusId,
            'lockedCampusName'        => $lockedCampusId ? Campus::find($lockedCampusId)?->name : null,
            'isSuperAdmin'            => $isSuperAdmin,
            'minPasswordLength'       => $minPasswordLength,
            'defaultPassword'         => $defaultPassword,
            'existingIds'             => $fileEmployeeIds->isEmpty() ? collect() : User::whereIn('employee_id', $fileEmployeeIds)->pluck('employee_id')->flip(),
            'existingEmails'          => $fileEmails->isEmpty() ? collect() : User::whereIn('email', $fileEmails)->pluck('email')->flip(),
            'campusesByName'          => Campus::pluck('id', 'name'),
            'departments'             => Department::get(['id', 'name', 'campus_id']),
        ];

        return [$rows, $ctx];
    }

    /**
     * Validate and resolve a single import row into either a ready-to-create
     * User payload or an error message. $seenIds/$seenEmails are accumulated
     * across the whole file (by reference) so two rows sharing the same
     * Employee ID or email within one upload catch each other, not just
     * collisions against employees that already existed before the upload.
     *
     * $generatePassword controls whether a blank Password cell actually
     * generates+validates a real password (import()) or is left as a
     * "will be generated" placeholder (importPreview() — no need to burn
     * a random password just to show a preview row).
     */
    private function resolveImportRow(array $row, array $ctx, bool $generatePassword, array &$seenIds, array &$seenEmails): array
    {
        $employeeId = trim((string) ($row['Employee ID'] ?? ''));
        $lastName   = trim((string) ($row['Last Name'] ?? ''));
        $firstName  = trim((string) ($row['First Name'] ?? ''));
        $name       = trim("{$firstName} {$lastName}");

        $fail = fn(string $msg) => [
            'ok' => false, 'error' => $msg, 'employee_id' => $employeeId, 'name' => $name,
            'role' => null, 'campus' => null, 'department' => null,
        ];

        if ($employeeId === '' || $lastName === '' || $firstName === '') {
            return $fail('Employee ID, Last Name, and First Name are required.');
        }

        if (isset($seenIds[$employeeId]) || isset($ctx['existingIds'][$employeeId])) {
            return $fail("Employee ID '{$employeeId}' already exists.");
        }

        $email = trim((string) ($row['Email'] ?? '')) ?: null;
        if ($email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $fail("'{$email}' is not a valid email.");
            }
            if (isset($seenEmails[$email]) || isset($ctx['existingEmails'][$email])) {
                return $fail("Email '{$email}' already exists.");
            }
        }

        $role = trim((string) ($row['Role'] ?? '')) ?: 'employee';
        if (! in_array($role, $ctx['allowedRoles'], true)) {
            return $fail("Role '{$role}' is not allowed for your account.");
        }

        $campusId      = $ctx['lockedCampusId'];
        $campusDisplay = $ctx['lockedCampusName'];
        $campusName    = trim((string) ($row['Campus'] ?? ''));
        if ($ctx['isSuperAdmin'] && $campusName !== '') {
            $campusId = $ctx['campusesByName'][$campusName] ?? null;
            if (! $campusId) {
                return $fail("Campus '{$campusName}' not found.");
            }
            $campusDisplay = $campusName;
        }

        $departmentId   = null;
        $departmentName = trim((string) ($row['Department'] ?? ''));
        if ($departmentName !== '') {
            $department = $ctx['departments']->first(
                fn($d) => $d->name === $departmentName && (! $campusId || (int) $d->campus_id === (int) $campusId)
            );
            if (! $department) {
                return $fail("Department '{$departmentName}' not found" . ($campusId ? ' in that campus.' : '.'));
            }
            $departmentId = $department->id;
        }

        $staffType = trim((string) ($row['Staff Type'] ?? '')) ?: null;
        if ($staffType && ! in_array($staffType, $ctx['allowedStaffTypes'], true)) {
            return $fail("Staff Type '{$staffType}' is invalid.");
        }

        $employmentStatus = trim((string) ($row['Employment Status'] ?? '')) ?: null;
        if ($employmentStatus && ! in_array($employmentStatus, $ctx['allowedEmploymentStatus'], true)) {
            return $fail("Employment Status '{$employmentStatus}' is invalid.");
        }

        $dateHiredRaw = trim((string) ($row['Date Hired'] ?? ''));
        $dateHired    = null;
        if ($dateHiredRaw !== '') {
            try {
                $dateHired = \Carbon\Carbon::parse($dateHiredRaw)->format('Y-m-d');
            } catch (\Throwable) {
                return $fail("Date Hired '{$dateHiredRaw}' is not a valid date.");
            }
        }

        $genderRaw = trim((string) ($row['Gender'] ?? ''));
        $gender    = $genderRaw !== '' ? strtolower($genderRaw) : null;
        if ($gender && ! in_array($gender, ['male', 'female'], true)) {
            return $fail("Gender '{$genderRaw}' must be Male or Female.");
        }

        $birthdateRaw = trim((string) ($row['Birthdate'] ?? ''));
        $birthdate    = null;
        if ($birthdateRaw !== '') {
            try {
                $birthdate = \Carbon\Carbon::parse($birthdateRaw)->format('Y-m-d');
            } catch (\Throwable) {
                return $fail("Birthdate '{$birthdateRaw}' is not a valid date.");
            }
            if (\Carbon\Carbon::parse($birthdate)->isFuture()) {
                return $fail('Birthdate cannot be in the future.');
            }
        }

        $salaryRaw = trim((string) ($row['Monthly Salary'] ?? ''));
        $salary    = null;
        if ($salaryRaw !== '') {
            if (! is_numeric($salaryRaw) || (float) $salaryRaw < 0) {
                return $fail("Monthly Salary '{$salaryRaw}' must be a positive number.");
            }
            $salary = round((float) $salaryRaw, 2);
        }

        $designation = trim((string) ($row['Designation'] ?? '')) ?: null;

        $passwordInput = trim((string) ($row['Password'] ?? ''));
        $autoGenerated = $passwordInput === '';
        $plainPassword = null;

        if ($autoGenerated) {
            if ($generatePassword) {
                // Shared across the whole batch (see prepareImport()) rather
                // than a fresh Str::password() per row — the difference
                // between one bcrypt hash and a few thousand at import time.
                $plainPassword = $ctx['defaultPassword'];
            }
        } else {
            $validator = Validator::make(['password' => $passwordInput], ['password' => ['string', Password::default()]]);
            if ($validator->fails()) {
                return $fail('Password does not meet the security policy.');
            }
            $plainPassword = $passwordInput;
        }

        $seenIds[$employeeId] = true;
        if ($email) {
            $seenEmails[$email] = true;
        }

        return [
            'ok'             => true,
            'error'          => null,
            'employee_id'    => $employeeId,
            'name'           => $name,
            'role'           => $role,
            'campus'         => $campusDisplay,
            'department'     => $departmentName ?: null,
            'auto_generated' => $autoGenerated,
            'plain_password' => $plainPassword,
            'data' => [
                'employee_id'       => $employeeId,
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'middle_name'       => trim((string) ($row['Middle Name'] ?? '')) ?: null,
                'email'             => $email,
                'role'              => $role,
                'campus_id'         => $campusId,
                'department_id'     => $departmentId,
                'position'          => trim((string) ($row['Position'] ?? '')) ?: null,
                'designation'       => $designation,
                'salary'            => $salary,
                'staff_type'        => $staffType,
                'employment_status' => $employmentStatus,
                'gender'            => $gender,
                'date_of_birth'     => $birthdate,
                'date_hired'        => $dateHired,
            ],
        ];
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

    private function format(User $e, ?Request $request = null): array
    {
        $includeSalary = $request && $this->isPrivileged($request);

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
            'designation'       => $e->designation,
            'salary'            => $includeSalary ? $e->salary : null,
            'employment_status' => $e->employment_status,
            'semi_monthly_dtr'  => $e->usesSemiMonthlyDtr(),
            'staff_type'        => $e->staff_type,
            'contact_number'    => $e->contact_number,
            'date_of_birth'     => $e->date_of_birth?->format('Y-m-d'),
            'age'               => $e->age,
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
