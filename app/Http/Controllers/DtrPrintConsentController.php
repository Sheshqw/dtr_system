<?php

namespace App\Http\Controllers;

use App\Events\PrintConsentDecided;
use App\Events\PrintConsentRequested;
use App\Http\Traits\ScopesByCampus;
use App\Models\DtrPrintConsent;
use App\Models\User;
use App\Notifications\PrintConsentReviewed;
use App\Support\DtrPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DtrPrintConsentController extends Controller
{
    use ScopesByCampus;

    /**
     * How many already-reviewed (non-pending) requests the default view
     * loads — it only grows over time (an approval is single-use, so every
     * subsequent download needs a fresh request), so loading the whole
     * history on every fetch gets slower and slower as a campus racks up
     * months of requests, even though the UI only ever shows a handful of
     * recent decisions at a time.
     */
    private const HISTORY_LIMIT = 10;

    private const EMPLOYEE_EAGER_LOAD = [
        'employee:id,employee_id,first_name,last_name,position,department_id,campus_id',
        'employee.department:id,name',
        'employee.campus:id,name',
        'reviewer:id,first_name,last_name',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        if ($this->isPrivileged($request)) {
            $baseQuery = DtrPrintConsent::query();
            if (! $this->isSuperAdmin($request)) {
                $baseQuery->whereHas('employee', fn($q) => $q->where('campus_id', $user->campus_id));
            }

            if ($request->filled('status')) {
                // An explicit status filter is a deliberate, narrower
                // request — honor it as-is rather than applying the
                // history limit meant for the default "everything at a
                // glance" view.
                $consents = (clone $baseQuery)
                    ->where('status', $request->query('status'))
                    ->with(self::EMPLOYEE_EAGER_LOAD)
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn($c) => $this->formatConsent($c, true));
            } else {
                // Default view: every pending request (actionable, stays
                // small since HR acts on them) + only the most recent
                // reviewed ones, not the entire history.
                $pending = (clone $baseQuery)->where('status', 'pending')
                    ->with(self::EMPLOYEE_EAGER_LOAD)
                    ->orderByDesc('created_at')
                    ->get();

                $history = (clone $baseQuery)->where('status', '!=', 'pending')
                    ->with(self::EMPLOYEE_EAGER_LOAD)
                    ->orderByDesc('created_at')
                    ->limit(self::HISTORY_LIMIT)
                    ->get();

                $consents = $pending->concat($history)->map(fn($c) => $this->formatConsent($c, true));
            }

            $summaryBase = DtrPrintConsent::whereHas('employee', fn($q) =>
                $q->when(! $this->isSuperAdmin($request), fn($q2) => $q2->where('campus_id', $user->campus_id))
            );

            $summary = [
                'pending'  => (clone $summaryBase)->where('status', 'pending')->count(),
                'approved' => (clone $summaryBase)->where('status', 'approved')->count(),
                'denied'   => (clone $summaryBase)->where('status', 'denied')->count(),
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
            'period'  => ['nullable', Rule::in(DtrPeriod::KEYS)],
            'purpose' => 'nullable|string|max:255',
        ]);

        $period = $validated['period'] ?? DtrPeriod::FULL;

        if ($period !== DtrPeriod::FULL && ! $user->usesSemiMonthlyDtr()) {
            throw ValidationException::withMessages([
                'period' => ['Half-month DTRs are only available for COS and Job Order employees.'],
            ]);
        }

        $existing = DtrPrintConsent::where('employee_id', $user->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('period', $period)
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
            'period'      => $period,
            'purpose'     => $validated['purpose'] ?? null,
            'status'      => 'pending',
        ]);

        $consent->loadMissing(['employee.department', 'employee.campus']);

        broadcast(new PrintConsentRequested(
            $this->formatConsent($consent, true),
            $user->campus?->code,
        ));

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

        $fresh = $consent->fresh(['employee.department', 'employee.campus', 'reviewer']);
        $formatted = $this->formatConsent($fresh, true);

        broadcast(new PrintConsentDecided(
            $formatted,
            $fresh->employee_id,
            $fresh->employee?->campus?->code,
        ));

        return response()->json([
            'message' => $validated['status'] === 'approved' ? 'Print request approved.' : 'Print request denied.',
            'consent' => $formatted,
        ]);
    }

    private function formatConsent(DtrPrintConsent $c, bool $includeEmployee): array
    {
        $data = [
            'id'               => $c->id,
            'month'            => $c->month,
            'year'             => $c->year,
            'period'           => $c->period ?? DtrPeriod::FULL,
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
