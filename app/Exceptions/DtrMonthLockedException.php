<?php

namespace App\Exceptions;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when a write would touch a DTR record inside a month HR has
 * locked. Left uncaught, Laravel calls render() below automatically —
 * callers that need to keep processing the rest of a batch (e.g. batch
 * correction review) should catch it explicitly instead of letting it
 * propagate.
 *
 * Implements HttpExceptionInterface so this is recognized as an expected
 * 4xx outcome, not a crash: Laravel's default handler skips logging it to
 * storage/logs, and it matches this app's own System Health reportable()
 * skip check in bootstrap/app.php — without this, a routine "this month
 * is locked" refusal was showing up as a false "critical" System Health
 * alert every time it fired.
 */
class DtrMonthLockedException extends \Exception implements HttpExceptionInterface
{
    public function __construct(int $year, int $month)
    {
        $label = Carbon::create($year, $month, 1)->format('F Y');
        parent::__construct("{$label} has been locked by HR and can no longer be modified. An Admin or Super Admin must unlock it first.");
    }

    public function getStatusCode(): int
    {
        return 423;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function render(Request $request)
    {
        return response()->json(['message' => $this->getMessage()], 423);
    }
}
