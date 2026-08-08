<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecomputeDtr extends Command
{
    protected $signature = 'dtr:recompute
                            {--from= : Start date (Y-m-d), defaults to 30 days ago}
                            {--to=   : End date (Y-m-d), defaults to today}
                            {--employee= : Single employee DB id to recompute}';

    protected $description = 'Recompute daily_dtr rows from existing attendance_logs (fixes checkout slots)';

    public function handle(WorkingHoursService $wh): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subDays(30);

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : now();

        $this->info("Recomputing DTR from {$from->toDateString()} to {$to->toDateString()} …");

        $query = AttendanceLog::selectRaw('employee_id, DATE(punch_time) as work_date')
            ->whereBetween(\DB::raw('DATE(punch_time)'), [$from->toDateString(), $to->toDateString()])
            ->groupBy('employee_id', \DB::raw('DATE(punch_time)'));

        if ($this->option('employee')) {
            $query->where('employee_id', $this->option('employee'));
        }

        $pairs = $query->get();

        if ($pairs->isEmpty()) {
            $this->warn('No attendance logs found for the given range.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pairs->count());
        $bar->start();

        $count = 0;
        foreach ($pairs as $row) {
            $employee = User::find($row->employee_id);
            if ($employee) {
                $wh->computeDailyDtr($employee, $row->work_date);
                $count++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Recomputed {$count} DTR record(s).");

        return self::SUCCESS;
    }
}
