<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\FeaturePermission;
use App\Models\User;

beforeEach(function () {
    $this->campus = Campus::factory()->create(['code' => 'CAN']);
    $this->month  = (int) now()->format('n');
    $this->year   = (int) now()->format('Y');
});

function trackingUrls(int $month, int $year): array
{
    return [
        "/api/tracking/tardiness?month={$month}&year={$year}",
        '/api/tracking/bradford-factor?days=90',
    ];
}

function setTrackingGrant(User $user, bool $granted): void
{
    FeaturePermission::create(['user_id' => $user->id, 'feature_key' => 'tracking_analytics', 'granted' => $granted]);
}

test('employees cannot open tracking by default', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    foreach (trackingUrls($this->month, $this->year) as $url) {
        $this->actingAs($employee, 'sanctum')->getJson($url)->assertForbidden();
    }
});

test('an employee granted tracking by the super admin can open it, seeing only their own records', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);
    $coworker = User::factory()->create(['campus_id' => $this->campus->id]);
    setTrackingGrant($employee, true);

    foreach ([$employee, $coworker] as $person) {
        DailyDtr::create([
            'employee_id'       => $person->id,
            'work_date'         => now()->startOfMonth()->toDateString(),
            'status'            => 'late',
            'tardiness_minutes' => 12,
        ]);
    }

    $this->actingAs($employee, 'sanctum')
        ->getJson(trackingUrls($this->month, $this->year)[0])
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.employee_id', $employee->employee_id);
});

test('hr officers and the super admin keep tracking by default', function () {
    foreach (['hrOfficer', 'superAdmin'] as $state) {
        $user = User::factory()->{$state}()->create(['campus_id' => $this->campus->id]);

        foreach (trackingUrls($this->month, $this->year) as $url) {
            $this->actingAs($user, 'sanctum')->getJson($url)->assertOk();
        }
    }
});

test('revoking tracking from an hr officer in access control blocks it', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);
    setTrackingGrant($admin, false);

    foreach (trackingUrls($this->month, $this->year) as $url) {
        $this->actingAs($admin, 'sanctum')->getJson($url)->assertForbidden();
    }
});

test('the profile endpoint reports the current tracking grant, so the menu can follow it', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($employee, 'sanctum')->getJson('/api/auth/me')
        ->assertJsonPath('feature_permissions.tracking_analytics', false);

    setTrackingGrant($employee, true);

    $this->actingAs($employee, 'sanctum')->getJson('/api/auth/me')
        ->assertJsonPath('feature_permissions.tracking_analytics', true);
});
