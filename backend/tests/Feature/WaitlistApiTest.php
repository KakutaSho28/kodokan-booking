<?php

namespace Tests\Feature;

use App\Jobs\WaitlistPromotedJob;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WaitlistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_join_waitlist_for_full_slot(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $fullSlot = AppointmentSlot::query()
            ->whereHas('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])
            ->assertCreated()
            ->assertJsonPath('message', 'キャンセル待ちに登録しました。')
            ->assertJsonPath('data.priority', 1);

        $this->getJson('/api/slots?date='.$fullSlot->date->toDateString().'&therapist_id='.$fullSlot->therapist_id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $fullSlot->id,
                'waitlist_count' => 1,
            ]);
    }

    public function test_patient_cannot_join_same_slot_waitlist_twice(): void
    {
        $this->seed();

        $token = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $fullSlot = AppointmentSlot::query()
            ->whereHas('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $this->withToken($token)->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slot_id');
    }

    public function test_patient_can_delete_only_own_waitlist(): void
    {
        $this->seed();

        $ownerToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $otherToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100003',
            'birth_date' => '1976-12-20',
        ])->assertOk()->json('token');

        $fullSlot = AppointmentSlot::query()
            ->whereHas('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $waitlistId = $this->withToken($ownerToken)
            ->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($otherToken)
            ->deleteJson('/api/waitlists/'.$waitlistId)
            ->assertForbidden();

        $this->withToken($ownerToken)
            ->deleteJson('/api/waitlists/'.$waitlistId)
            ->assertOk()
            ->assertJsonPath('message', 'キャンセル待ちを取り消しました。');

        $this->assertDatabaseMissing('waitlists', ['id' => $waitlistId]);
    }

    public function test_staff_can_list_waitlists_for_slot_ordered_by_priority(): void
    {
        $this->seed();

        $staffToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $fullSlot = AppointmentSlot::query()
            ->whereHas('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $firstToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $secondToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100003',
            'birth_date' => '1976-12-20',
        ])->assertOk()->json('token');

        $this->withToken($firstToken)->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])->assertCreated();
        $this->withToken($secondToken)->postJson('/api/waitlists', ['slot_id' => $fullSlot->id])->assertCreated();

        $this->withToken($staffToken)
            ->getJson('/api/waitlists?slot_id='.$fullSlot->id)
            ->assertOk()
            ->assertJsonPath('data.0.priority', 1)
            ->assertJsonPath('data.1.priority', 2);
    }

    public function test_cancelled_reservation_promotes_first_waitlist_entry(): void
    {
        Queue::fake();
        $this->seed();

        $staffToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $patientToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $appointment = Appointment::query()->where('status', Appointment::STATUS_BOOKED)->firstOrFail();

        $waitlistId = $this->withToken($patientToken)
            ->postJson('/api/waitlists', ['slot_id' => $appointment->appointment_slot_id])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($staffToken)
            ->deleteJson('/api/appointments/'.$appointment->id)
            ->assertOk();

        $this->assertDatabaseHas('waitlists', [
            'id' => $waitlistId,
            'status' => Waitlist::STATUS_PROMOTED,
        ]);

        Queue::assertPushed(WaitlistPromotedJob::class);
    }
}
