<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\Staff;
use App\Models\Therapist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffShiftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_staff_and_shifts(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $staffId = $this->withToken($token)
            ->postJson('/api/staffs', [
                'staff_id' => 'PT900',
                'name' => '勤務 太郎',
                'password' => 'staffpass',
                'role' => 'staff',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->withToken($token)
            ->putJson('/api/staffs/'.$staffId, [
                'staff_id' => 'PT900',
                'name' => '勤務 次郎',
                'password' => null,
                'role' => 'staff',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '勤務 次郎');

        $this->withToken($token)
            ->postJson('/api/shifts', [
                'staff_id' => $staffId,
                'work_date' => '2026-07-01',
                'start_time' => '10:00',
                'end_time' => '16:00',
                'is_day_off' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.start_time', '10:00:00');

        $this->withToken($token)
            ->getJson('/api/staffs/'.$staffId.'/shifts?month=2026-07')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->putJson('/api/staffs/'.$staffId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_non_admin_cannot_create_staff_or_shift(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'PT001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $staff = Staff::query()->where('staff_id', 'PT001')->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/staffs', [
                'staff_id' => 'PT901',
                'name' => '権限 なし',
                'password' => 'staffpass',
                'role' => 'staff',
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/shifts', [
                'staff_id' => $staff->id,
                'work_date' => '2026-07-01',
                'start_time' => '10:00',
                'end_time' => '16:00',
                'is_day_off' => false,
            ])
            ->assertForbidden();
    }

    public function test_slots_follow_staff_shift_hours_and_day_off(): void
    {
        $this->seed();

        $therapist = Therapist::query()->whereNotNull('staff_id')->firstOrFail();

        Shift::query()->updateOrCreate(
            [
                'staff_id' => $therapist->staff_id,
                'work_date' => '2026-07-02',
            ],
            [
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'is_day_off' => false,
            ],
        );

        $this->getJson('/api/slots?date=2026-07-02&therapist_id='.$therapist->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.time', '10:00')
            ->assertJsonPath('data.1.time', '10:30');

        Shift::query()->updateOrCreate(
            [
                'staff_id' => $therapist->staff_id,
                'work_date' => '2026-07-02',
            ],
            [
                'start_time' => null,
                'end_time' => null,
                'is_day_off' => true,
            ],
        );

        $this->getJson('/api/slots?date=2026-07-02&therapist_id='.$therapist->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
