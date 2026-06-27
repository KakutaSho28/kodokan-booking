<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\Therapist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_filter_patients_with_resource_shape(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $query = http_build_query(['name' => '後楽', 'is_diagnosed' => '0']);

        $this->withToken($token)
            ->getJson('/api/patients?'.$query)
            ->assertOk()
            ->assertJsonPath('data.0.name', '後楽 花子')
            ->assertJsonPath('data.0.is_diagnosed', false)
            ->assertJsonStructure(['data' => [['id', 'card_number', 'chart_number', 'assigned_therapist']]]);
    }

    public function test_admin_can_create_update_and_diagnose_patient(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $therapist = Therapist::query()->firstOrFail();

        $patientId = $this->withToken($token)
            ->postJson('/api/patients', [
                'chart_number' => '900001',
                'name' => '診断 待子',
                'birth_date' => '1990-01-02',
                'assigned_therapist_id' => $therapist->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_diagnosed', false)
            ->json('data.id');

        $this->withToken($token)
            ->putJson('/api/patients/'.$patientId, [
                'chart_number' => '900001',
                'name' => '診断 済子',
                'birth_date' => '1990-01-02',
                'assigned_therapist_id' => $therapist->id,
                'is_diagnosed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_diagnosed', false);

        $this->withToken($token)
            ->putJson('/api/admin/patients/'.$patientId.'/diagnose')
            ->assertOk()
            ->assertJsonPath('message', '診断済みに更新しました。')
            ->assertJsonPath('data.is_diagnosed', true);
    }

    public function test_non_admin_staff_cannot_diagnose_patient(): void
    {
        $this->seed();

        Staff::query()->create([
            'staff_id' => 'KB002',
            'name' => '一般スタッフ',
            'password' => 'staffpass',
            'role' => 'staff',
        ]);

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB002',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $patient = Patient::query()->where('is_diagnosed', false)->firstOrFail();

        $this->withToken($token)
            ->putJson('/api/admin/patients/'.$patient->id.'/diagnose')
            ->assertForbidden()
            ->assertJsonPath('message', '管理者権限が必要です。');
    }

    public function test_undiagnosed_patient_cannot_book_reservation(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/patient', [
            'card_number' => '100002',
            'birth_date' => '1991-09-03',
        ])->assertOk()->assertJsonPath('can_book_rehab', false)->json('token');

        $slot = AppointmentSlot::query()
            ->whereDoesntHave('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/appointments', ['appointment_slot_id' => $slot->id])
            ->assertForbidden()
            ->assertJsonPath('message', '初診診断後にご予約いただけます');
    }

    public function test_patient_detail_contains_latest_reservations_and_assigned_therapist(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $patient = Patient::query()->whereHas('appointments')->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/patients/'.$patient->id)
            ->assertOk()
            ->assertJsonStructure(['data' => ['assigned_therapist', 'reservations']]);

        $this->withToken($token)
            ->getJson('/api/patients/'.$patient->id.'/reservations')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
