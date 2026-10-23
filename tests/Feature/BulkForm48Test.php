<?php

use App\Http\Controllers\ReportController;
use App\Models\Campus;
use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->can = Campus::factory()->create(['code' => 'CAN']);
    $this->tan = Campus::factory()->create(['code' => 'TAN']);
    $this->hr  = User::factory()->hrOfficer()->create(['campus_id' => $this->can->id]);
});

function printAll(array $params = []): array
{
    return ['/api/reports/form48-bulk', ['month' => 9, 'year' => 2026] + $params];
}

/** Sheets in the print document — one A4 sheet per employee. */
function sheets(string $html): int
{
    return substr_count($html, '<table class="pair">');
}

test('Print All returns one print document with a sheet per active employee in the campus', function () {
    User::factory()->count(3)->create(['campus_id' => $this->can->id]);
    User::factory()->create(['campus_id' => $this->can->id, 'is_active' => false]);
    User::factory()->count(2)->create(['campus_id' => $this->tan->id]);

    $res = $this->actingAs($this->hr, 'sanctum')->post(...printAll())
        ->assertOk()
        ->assertHeader('X-Sheet-Count', '4');

    // The 3 employees + the HR officer themself; not the inactive one or TAN's.
    expect(sheets($res->getContent()))->toBe(4);
});

test('Print All honours the department filter and a hand-picked list', function () {
    $dept   = Department::create(['name' => 'DITCS', 'code' => 'DITCS', 'campus_id' => $this->can->id]);
    $inDept = User::factory()->count(2)->create(['campus_id' => $this->can->id, 'department_id' => $dept->id]);
    User::factory()->count(2)->create(['campus_id' => $this->can->id]);

    $byDept = $this->actingAs($this->hr, 'sanctum')->post(...printAll(['department_id' => $dept->id]))->assertOk();
    expect(sheets($byDept->getContent()))->toBe(2);

    $picked = $this->actingAs($this->hr, 'sanctum')->post(...printAll(['employee_ids' => [$inDept[0]->id]]))->assertOk();
    expect(sheets($picked->getContent()))->toBe(1);
});

test('an hr officer cannot print another campus by hand-picking its employees', function () {
    $other = User::factory()->create(['campus_id' => $this->tan->id]);

    $this->actingAs($this->hr, 'sanctum')
        ->postJson(...printAll(['employee_ids' => [$other->id]]))
        ->assertStatus(422);
});

test('a half-month Print All covers only COS and Job Order employees', function () {
    User::factory()->create(['campus_id' => $this->can->id, 'employment_status' => 'cos']);
    User::factory()->create(['campus_id' => $this->can->id, 'employment_status' => 'job_order']);
    User::factory()->create(['campus_id' => $this->can->id, 'employment_status' => 'permanent']);

    $res = $this->actingAs($this->hr, 'sanctum')->post(...printAll(['period' => 'first']))->assertOk();

    expect(sheets($res->getContent()))->toBe(2);
});

test('Print All refuses a batch over the limit', function () {
    User::factory()->count(ReportController::BULK_PRINT_LIMIT)->create(['campus_id' => $this->can->id]);

    $this->actingAs($this->hr, 'sanctum')
        ->postJson(...printAll())
        ->assertStatus(422)
        ->assertJsonValidationErrors('employee_ids');
});

test('a regular employee cannot use Print All', function () {
    $employee = User::factory()->create(['campus_id' => $this->can->id]);

    $this->actingAs($employee, 'sanctum')->postJson(...printAll())->assertForbidden();
});
