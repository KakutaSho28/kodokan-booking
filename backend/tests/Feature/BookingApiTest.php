<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_book_available_rehab_slot(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $slot = AppointmentSlot::query()
            ->whereDoesntHave('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/appointments', ['appointment_slot_id' => $slot->id])
            ->assertCreated()
            ->assertJsonPath('message', '予約を受け付けました。');

        $this->getJson('/api/slots?date='.$slot->date->toDateString().'&therapist_id='.$slot->therapist_id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $slot->id,
                'availability_mark' => '×',
                'is_available' => false,
            ]);
    }

    public function test_slot_availability_returns_half_hour_status_for_therapist(): void
    {
        $this->seed();

        $slot = AppointmentSlot::query()->firstOrFail();

        $this->getJson('/api/slots?date='.$slot->date->toDateString().'&therapist_id='.$slot->therapist_id)
            ->assertOk()
            ->assertJsonPath('data.0.time', '09:00')
            ->assertJsonPath('data.0.status', 'full')
            ->assertJsonPath('data.0.available_count', 0)
            ->assertJsonCount(16, 'data');
    }

    public function test_patient_can_book_by_therapist_date_and_time(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $slot = AppointmentSlot::query()
            ->whereDoesntHave('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/appointments', [
                'therapist_id' => $slot->therapist_id,
                'date' => $slot->date->toDateString(),
                'time' => substr($slot->starts_at, 0, 5),
            ])
            ->assertCreated()
            ->assertJsonPath('data.slot.therapist_id', $slot->therapist_id);
    }

    public function test_first_visit_patient_cannot_book_rehab(): void
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

    public function test_staff_can_cancel_appointment(): void
    {
        $this->seed();

        $appointment = Appointment::query()->firstOrFail();

        $token = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->deleteJson('/api/appointments/'.$appointment->id)
            ->assertOk()
            ->assertJsonPath('message', '予約をキャンセルしました。');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }
}
