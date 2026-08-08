<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DailyDtr;
use App\Models\DtrCorrection;
use App\Models\User;
use App\Notifications\CorrectionReviewed;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DtrCorrectionController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $user   = $request->user();
        $status = $request->query('status');

        if ($this->isPrivileged($request)) {
            $query = DtrCorrection::with([
                'employee:id,employee_id,first_name,last_name,position,campus_id',
                'reviewer:id,first_name,last_name',
            ]);

            if (! $this->isSuperAdmin($request)) {
                $query->whereHas('employee', fn($q) =>
                    $q->where('campus_id', $user->campus_id)
                );
            }

            if ($status) $query->where('status', $status);

            $corrections = $query
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($c) => $this->formatCorrection($c, true));

            $baseQuery = DtrCorrection::whereHas('employee', fn($q) =>
                $q->when(! $this->isSuperAdmin($request), fn($q2) =>
                    $q2->where('campus_id', $user->campus_id)
                )
            );

            $summary = [
                'pending'  => (clone $baseQuery)->where('status', 'pending')->count(),
                'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
                'denied'   => (clone $baseQuery)->where('status', 'denied')->count(),
            ];
        } else {
            $query = DtrCorrection::with('reviewer:id,first_name,last_name')
                ->where('employee_id', $user->id);

            if ($status) $query->where('status', $status);

            $corrections = $query
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($c) => $this->formatCorrection($c, false));

            $summary = [
                'pending'  => DtrCorrection::where('employee_id', $user->id)->where('status', 'pending')->count(),
                'approved' => DtrCorrection::where('employee_id', $user->id)->where('status', 'approved')->count(),
                'denied'   => DtrCorrection::where('employee_id', $user->id)->where('status', 'denied')->count(),
            ];
        }

        return response()->json([
            'corrections' => $corrections,
            'summary'     => $summary,
        ]);
    }

    /**
     * Submit one or more field corrections for the same date in a single request.
     * fields: [{ field, requested_value }, ...]
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'work_date'                    => 'required|date|before_or_equal:today',
            'fields'                       => 'required|array|min:1|max:4',
            'fields.*.field'               => 'required|in:morning_in,morning_out,afternoon_in,afternoon_out',
            'fields.*.requested_value'     => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'reason'                       => 'required|string|min:10|max:500',
        ]);

        $workDate = $validated['work_date'];

        // Check for duplicate pending per field
        foreach ($validated['fields'] as $fd) {
            $exists = DtrCorrection::where('employee_id', $user->id)
                ->where('work_date', $workDate)
                ->where('field', $fd['field'])
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                $label = $this->fieldLabel($fd['field']);
                return response()->json([
                    'message' => "You already have a pending correction for {$label} on {$workDate}.",
                ], 422);
            }
        }

        $dtr     = DailyDtr::where('employee_id', $user->id)->where('work_date', $workDate)->first();
        $groupId = count($validated['fields']) > 1 ? (string) Str::uuid() : null;
        $created = [];

        foreach ($validated['fields'] as $fd) {
            $correction = DtrCorrection::create([
                'group_id'        => $groupId,
                'employee_id'     => $user->id,
                'work_date'       => $workDate,
                'field'           => $fd['field'],
                'original_value'  => $dtr?->{$fd['field']},
                'requested_value' => $fd['requested_value'],
                'reason'          => $validated['reason'],
                'status'          => 'pending',
            ]);
            $created[] = $correction;
        }

        $count = count($created);

        return response()->json([
            'message'     => $count > 1
                ? "Correction request submitted for {$count} fields. HR will review it shortly."
                : 'Correction request submitted successfully.',
            'corrections' => array_map(fn($c) => $this->formatCorrection($c->load('reviewer'), false), $created),
        ], 201);
    }

    /**
     * Review a single correction. If it belongs to a group, approves/denies the whole group.
     */
    public function review(Request $request, DtrCorrection $correction)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only HR Officers and Admins can review correction requests.');
        }

        $this->assertSameCampus($request, $correction->employee?->campus_id);

        $user = $request->user();

        if ((int) $correction->employee_id === (int) $user->id) {
            abort(403, 'You cannot review your own correction request.');
        }

        if ($correction->status !== 'pending') {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'status'           => 'required|in:approved,denied',
            'reviewer_remarks' => 'nullable|string|max:500',
        ]);

        // If grouped, review all pending corrections in the group together
        $toReview = $correction->group_id
            ? DtrCorrection::where('group_id', $correction->group_id)->where('status', 'pending')->get()
            : collect([$correction]);

        foreach ($toReview as $c) {
            $this->applyReview($c, $validated, $user);
        }

        // Notify employee once (not once per field)
        $employee = User::find($correction->employee_id);
        if ($employee && $employee->email) {
            try {
                $employee->notify(new CorrectionReviewed($correction, $validated['status'], $validated['reviewer_remarks'] ?? null));
            } catch (\Throwable) {
                // Silent — don't fail the review if mail is misconfigured
            }
        }

        $label = $validated['status'] === 'approved' ? 'approved' : 'denied';
        $count = $toReview->count();

        return response()->json([
            'message'    => $count > 1
                ? "{$count} correction fields {$label} successfully."
                : "Correction request {$label}.",
            'correction' => $this->formatCorrection($correction->fresh(['employee', 'reviewer']), true),
        ]);
    }

    /**
     * Batch approve or deny multiple pending corrections at once.
     * POST /api/dtr-corrections/batch-review
     */
    public function batchReview(Request $request)
    {
        if (! $this->isPrivileged($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'ids'              => 'required|array|min:1',
            'ids.*'            => 'integer|exists:dtr_corrections,id',
            'status'           => 'required|in:approved,denied',
            'reviewer_remarks' => 'nullable|string|max:500',
        ]);

        $user        = $request->user();
        $corrections = DtrCorrection::whereIn('id', $validated['ids'])
            ->where('status', 'pending')
            ->where('employee_id', '!=', $user->id)
            ->when(! $this->isSuperAdmin($request), fn($q) =>
                $q->whereHas('employee', fn($q2) => $q2->where('campus_id', $user->campus_id))
            )
            ->get();

        // Expand group members so approving one grouped item approves all siblings
        $groupIds      = $corrections->pluck('group_id')->filter()->unique()->values();
        $groupSiblings = $groupIds->isNotEmpty()
            ? DtrCorrection::whereIn('group_id', $groupIds)->where('status', 'pending')->get()
            : collect();

        $allToReview = $corrections->merge($groupSiblings)->unique('id');

        foreach ($allToReview as $c) {
            $this->applyReview($c, $validated, $user);
        }

        // Notify each unique employee once
        $allToReview->groupBy('employee_id')->each(function ($items) use ($validated) {
            $employee = User::find($items->first()->employee_id);
            if ($employee && $employee->email) {
                try {
                    $employee->notify(new CorrectionReviewed($items->first(), $validated['status'], $validated['reviewer_remarks'] ?? null, $items->count()));
                } catch (\Throwable) {}
            }
        });

        $processed = $allToReview->count();
        $label     = $validated['status'] === 'approved' ? 'approved' : 'denied';

        return response()->json([
            'message'   => "{$processed} correction " . ($processed === 1 ? 'request' : 'requests') . " {$label}.",
            'processed' => $processed,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function applyReview(DtrCorrection $correction, array $validated, User $reviewer): void
    {
        $correction->update([
            'status'           => $validated['status'],
            'reviewed_by'      => $reviewer->id,
            'reviewed_at'      => now(),
            'reviewer_remarks' => $validated['reviewer_remarks'] ?? null,
        ]);

        if ($validated['status'] !== 'approved') return;

        $dtr = DailyDtr::firstOrCreate(
            ['employee_id' => $correction->employee_id, 'work_date' => $correction->work_date],
            ['status' => 'present', 'hours_rendered' => 0]
        );

        $dtr->{$correction->field} = $correction->requested_value;

        if ($dtr->morning_in && $dtr->morning_out && $dtr->afternoon_in && $dtr->afternoon_out) {
            $morHours = Carbon::parse($dtr->morning_in)->diffInMinutes(Carbon::parse($dtr->morning_out)) / 60;
            $aftHours = Carbon::parse($dtr->afternoon_in)->diffInMinutes(Carbon::parse($dtr->afternoon_out)) / 60;
            $dtr->hours_rendered = round($morHours + $aftHours, 2);
        }

        if ($dtr->status === 'absent' && ($dtr->morning_in || $dtr->afternoon_in)) {
            $dtr->status = 'present';
        }

        $dtr->is_corrected      = true;
        $dtr->corrected_by      = $reviewer->id;
        $dtr->corrected_at      = now();
        $dtr->correction_reason = $correction->reason;
        $dtr->save();
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'morning_in'    => 'Morning Time-In',
            'morning_out'   => 'Morning Time-Out',
            'afternoon_in'  => 'Afternoon Time-In',
            'afternoon_out' => 'Afternoon Time-Out',
            default         => $field,
        };
    }

    private function formatCorrection(DtrCorrection $c, bool $includeEmployee): array
    {
        $data = [
            'id'               => $c->id,
            'group_id'         => $c->group_id,
            'work_date'        => $c->work_date?->format('Y-m-d'),
            'work_date_label'  => $c->work_date?->format('M j, Y') . ' (' . $c->work_date?->format('D') . ')',
            'field'            => $c->field,
            'field_label'      => $c->field_label,
            'original_value'   => $c->original_value ? substr($c->original_value, 0, 5) : null,
            'requested_value'  => substr($c->requested_value, 0, 5),
            'reason'           => $c->reason,
            'status'           => $c->status,
            'reviewer_remarks' => $c->reviewer_remarks,
            'reviewed_at'      => $c->reviewed_at?->format('M j, Y h:i A'),
            'reviewed_by_name' => $c->reviewer
                ? $c->reviewer->first_name . ' ' . $c->reviewer->last_name
                : null,
            'submitted_at'     => $c->created_at->format('M j, Y h:i A'),
        ];

        if ($includeEmployee && $c->employee) {
            $data['employee'] = [
                'id'          => $c->employee->id,
                'name'        => $c->employee->full_name,
                'employee_id' => $c->employee->employee_id,
                'position'    => $c->employee->position,
            ];
        }

        return $data;
    }
}
