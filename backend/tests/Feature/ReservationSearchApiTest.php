<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_search_returns_paginated_results_with_relations(): void
    {
        $this->seed();

        $staff = Staff::query()->firstOrFail();
        $query = http_build_query([
            'patient_name' => '水道橋',
            'staff_id' => $staff->id,
            'status' => Appointment::STATUS_BOOKED,
        ]);

        $this->getJson('/api/reservations/search?'.$query)
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('data.0.patient.name', '水道橋 健')
            ->assertJsonPath('data.0.staff.id', $staff->id)
            ->assertJsonPath('data.0.treatment_type.name', '運動器リハビリ');
    }

    public function test_reservation_search_validates_query_params(): void
    {
        $this->seed();

        $this->getJson('/api/reservations/search?date_from=invalid&status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from', 'status']);
    }
}
