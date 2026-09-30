<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DtrPrintConsent;
use App\Models\User;
use App\Notifications\PrintConsentReviewed;
use Illuminate\Http\Request;

class DtrPrintConsentController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $user = $request->user();

        if ($this->isPrivileged($request)) {
            $query = DtrPrintConsent::with([
                'employee:id,employee_id,first_name,last_name,position,department_id,campus_id',
                'employee.department:id,name',
                'employee.campus:id,name',
                'reviewer:id,first_name,last_name',
            ]);

            if (! $this->isSuperAdmin($request)) {
                $query->whereHas('employee', fn($q) => $q->where('campus_id', $user->campus_id));
            }

            if ($request->filled('status')) $query->where('status', $request->query('status'));

            $consents = $query
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($c) => $this->formatConsent($c, true));

            $baseQuery = DtrPrintConsent::whereHas('employee', fn($q) =>
                $q->when(! $this->isSuperAdmin($request), fn($q2) => $q2->where('campus_id', $user->campus_id))
            );

            $summary = [
                'pending'  => (clone $baseQuery)->where('status', 'pending')->count(),
                'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
                'denied'   => (clone $baseQuery)->where('status', 'denied')->count(),
            ];
        } else {
            $query = DtrPrintConsent::with('reviewer:id,first_name,last_name')
                ->where('employee_id', $user->id);

            $consents = $query
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($c) => $this->formatConsent($c, false));

            $summary = [
                'pending'  => DtrPrintConsent::where('employee_id', $user->id)->where('status', 'pending')->count(),
                'approved' => DtrPrintConsent::where('employee_id', $user->id)->where('status', 'approved')->count(),
                'denied'   => DtrPrintConsent::where('employee_id', $user->id)->where('status', 'denied')->count(),
            ];
        }

        return response()->json([
            'consents' => $consents,
            'summary'  => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'month'   => 'required|integer|between:1,12',
            'year'    => 'required|integer|between:2020,2099',
            'purpose' => 'nullable|string|max:255',
        ]);

        $existing = DtrPrintConsent::where('employee_id', $user->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where(fn($q) => $q->where('status', 'pending')
                ->orWhere(fn($q2) => $q2->where('status', 'approved')->whereNull('used_at')))
            ->first();

        if ($existing) {
            $message = $existing->status === 'pending'
                ? 'You already have a pending print request for this period.'
                : 'You already have an approved, unused print request for this period — go ahead and download it.';
            return response()->json(['message' => $message], 422);
        }

        $consent = DtrPrintConsent::create([
            'employee_id' => $user->id,
            'month'       => $validated['month'],
            'year'        => $validated['year'],
            'purpose'     => $validated['purpose'] ?? null,
            'status'      => 'pending',
        ]);

        return response()->json([
            'message' => 'Print request submitted. HR/Admin will review it shortly.',
            'consent' => $this->formatConsent($consent, false),
        ], 201);
    }

    public function review(Request $request, DtrPrintConsent $consent)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only HR Officers and Admins can review print requests.');
        }

        $this->assertSameCampus($request, $consent->employee?->campus_id);

        if ((int) $consent->employee_id === (int) $request->user()->id) {
            abort(403, 'You cannot review your own print request.');
        }

        if ($consent->status !== 'pending') {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'status'           => 'required|in:approved,denied',
            'reviewer_remarks' => 'nullable|string|max:500',
        ]);

        $consent->update([
            'status'           => $validated['status'],
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'reviewer_remarks' => $validated['reviewer_remarks'] ?? null,
        ]);

        $employee = User::find($consent->employee_id);
        if ($employee && $employee->email) {
            try {
                $employee->notify(new PrintConsentReviewed($consent, $validated['status'], $validated['reviewer_remarks'] ?? null));
            } catch (\Throwable) {
                // Silent — don't fail the review if mail is misconfigured
            }
        }

        return response()->json([
            'message' => $validated['status'] === 'approved' ? 'Print request approved.' : 'Print request denied.',
            'consent' => $this->formatConsent($consent->fresh(['employee', 'reviewer']), true),
        ]);
    }

    private function formatConsent(DtrPrintConsent $c, bool $includeEmployee): array
    {
        $data = [
            'id'               => $c->id,
            'month'            => $c->month,
            'year'             => $c->year,
            'month_label'      => $c->month_label,
            'purpose'          => $c->purpose,
            'status'           => $c->status,
            'reviewer_remarks' => $c->reviewer_remarks,
            'reviewed_at'      => $c->reviewed_at?->format('M j, Y h:i A'),
            'reviewed_by_name' => $c->reviewer ? $c->reviewer->first_name . ' ' . $c->reviewer->last_name : null,
            'used_at'          => $c->used_at?->format('M j, Y h:i A'),
            'submitted_at'     => $c->created_at->format('M j, Y h:i A'),
        ];

        if ($includeEmployee && $c->employee) {
            $data['employee'] = [
                'id'          => $c->employee->id,
                'name'        => $c->employee->full_name,
                'employee_id' => $c->employee->employee_id,
                'position'    => $c->employee->position,
                'department'  => $c->employee->department?->name,
                'campus'      => $c->employee->campus?->name,
            ];
        }

        return $data;
    }
}
