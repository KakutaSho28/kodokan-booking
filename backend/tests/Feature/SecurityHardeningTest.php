<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_added_to_api_responses(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_role_middleware_blocks_wrong_role(): void
    {
        $this->seed();

        $patientToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $this->withToken($patientToken)
            ->getJson('/api/staff/patients')
            ->assertForbidden();

        $staffToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'PT001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $patient = Patient::query()->where('is_diagnosed', false)->firstOrFail();

        $this->withToken($staffToken)
            ->putJson('/api/admin/patients/'.$patient->id.'/diagnose')
            ->assertForbidden();
    }

    public function test_staff_role_patient_responses_are_masked(): void
    {
        $this->seed();

        $staffToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'PT001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $this->withToken($staffToken)
            ->getJson('/api/staff/patients')
            ->assertOk()
            ->assertJsonPath('data.0.birth_date', '****年生まれ')
            ->assertJsonPath('data.0.chart_number', '****0001');
    }

    public function test_patient_login_locks_after_five_failed_attempts(): void
    {
        Cache::flush();
        $this->seed();

        for ($index = 0; $index < 4; $index++) {
            $this->postJson('/api/auth/patient', [
                'card_number' => '100001',
                'birth_date' => '1900-01-01',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1900-01-01',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'ログイン試行回数が上限に達しました。15分後に再試行してください。');

        $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertStatus(429);
    }

    public function test_audit_logs_are_written_for_patient_and_reservation_changes(): void
    {
        Queue::fake();
        $this->seed();

        $adminToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $patientId = $this->withToken($adminToken)
            ->postJson('/api/admin/patients', [
                'chart_number' => '990001',
                'name' => '監査 太郎',
                'birth_date' => '1980-01-01',
                'email' => 'audit@example.com',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_type' => 'staff',
            'action' => 'patient.created',
            'target_id' => $patientId,
        ]);

        $patientToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $slot = AppointmentSlot::query()
            ->whereDoesntHave('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $appointmentId = $this->withToken($patientToken)
            ->postJson('/api/portal/appointments', ['appointment_slot_id' => $slot->id])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_type' => 'patient',
            'action' => 'reservation.created',
            'target_id' => $appointmentId,
        ]);
    }
}
