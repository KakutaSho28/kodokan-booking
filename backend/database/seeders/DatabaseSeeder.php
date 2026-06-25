<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Therapist;
use App\Models\TreatmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $staff = Staff::updateOrCreate(
            ['staff_id' => 'KB001'],
            ['name' => '受付スタッフ', 'password' => 'staffpass', 'role' => 'admin', 'is_active' => true],
        );

        $therapistStaff = collect([
            Staff::updateOrCreate(['staff_id' => 'PT001'], ['name' => '加藤 理学療法士', 'password' => 'staffpass', 'role' => 'staff', 'is_active' => true]),
            Staff::updateOrCreate(['staff_id' => 'PT002'], ['name' => '稲垣 理学療法士', 'password' => 'staffpass', 'role' => 'staff', 'is_active' => true]),
            Staff::updateOrCreate(['staff_id' => 'PT003'], ['name' => '佐藤 理学療法士', 'password' => 'staffpass', 'role' => 'staff', 'is_active' => true]),
        ]);

        $therapists = collect([
            Therapist::updateOrCreate(['name' => '加藤 理学療法士'], ['staff_id' => $therapistStaff->get(0)->id, 'specialty' => '運動器リハビリ', 'is_active' => true]),
            Therapist::updateOrCreate(['name' => '稲垣 理学療法士'], ['staff_id' => $therapistStaff->get(1)->id, 'specialty' => 'スポーツ外傷', 'is_active' => true]),
            Therapist::updateOrCreate(['name' => '佐藤 理学療法士'], ['staff_id' => $therapistStaff->get(2)->id, 'specialty' => '慢性疼痛', 'is_active' => true]),
        ]);

        $patients = collect([
            Patient::updateOrCreate(
                ['card_number' => '100001'],
                [
                    'name' => '春日 太郎',
                    'birth_date' => '1984-04-12',
                    'email' => 'taro.kasuga@example.com',
                    'is_first_visit' => false,
                    'has_rehab_clearance' => true,
                    'is_diagnosed' => true,
                    'assigned_therapist_id' => $therapists->first()->id,
                ],
            ),
            Patient::updateOrCreate(
                ['card_number' => '100002'],
                [
                    'name' => '後楽 花子',
                    'birth_date' => '1991-09-03',
                    'email' => 'hanako.koraku@example.com',
                    'is_first_visit' => true,
                    'has_rehab_clearance' => false,
                    'is_diagnosed' => false,
                    'assigned_therapist_id' => null,
                ],
            ),
            Patient::updateOrCreate(
                ['card_number' => '100003'],
                [
                    'name' => '水道橋 健',
                    'birth_date' => '1976-12-20',
                    'email' => 'ken.suidobashi@example.com',
                    'is_first_visit' => false,
                    'has_rehab_clearance' => true,
                    'is_diagnosed' => true,
                    'assigned_therapist_id' => $therapists->get(1)->id,
                ],
            ),
        ]);

        $treatmentTypes = collect([
            TreatmentType::updateOrCreate(['name' => '運動器リハビリ']),
            TreatmentType::updateOrCreate(['name' => '物理療法']),
            TreatmentType::updateOrCreate(['name' => '術後リハビリ']),
        ]);

        $times = [
            ['09:00:00', '09:30:00'],
            ['09:30:00', '10:00:00'],
            ['10:00:00', '10:30:00'],
            ['10:30:00', '11:00:00'],
            ['11:00:00', '11:30:00'],
            ['15:00:00', '15:30:00'],
            ['15:30:00', '16:00:00'],
            ['16:00:00', '16:30:00'],
            ['16:30:00', '17:00:00'],
            ['17:00:00', '17:30:00'],
        ];

        for ($offset = 0; $offset < 14; $offset++) {
            $date = CarbonImmutable::today()->addDays($offset);

            if ($date->isSunday()) {
                continue;
            }

            foreach ($therapists as $therapist) {
                if ($therapist->staff_id) {
                    Shift::updateOrCreate(
                        [
                            'staff_id' => $therapist->staff_id,
                            'work_date' => $date->toDateString(),
                        ],
                        [
                            'start_time' => '09:00:00',
                            'end_time' => '17:00:00',
                            'is_day_off' => false,
                        ],
                    );
                }

                foreach ($times as [$startsAt, $endsAt]) {
                    if ($date->isFriday() && $startsAt < '12:00:00') {
                        continue;
                    }

                    if ($date->isSaturday() && $startsAt >= '12:00:00') {
                        continue;
                    }

                    AppointmentSlot::updateOrCreate(
                        [
                            'therapist_id' => $therapist->id,
                            'date' => $date->toDateString(),
                            'starts_at' => $startsAt,
                        ],
                        [
                            'ends_at' => $endsAt,
                            'capacity' => 1,
                        ],
                    );
                }
            }
        }

        $firstSlot = AppointmentSlot::query()->orderBy('date')->orderBy('starts_at')->first();

        if ($firstSlot) {
            Appointment::updateOrCreate(
                [
                    'patient_id' => $patients->last()->id,
                    'appointment_slot_id' => $firstSlot->id,
                ],
                [
                    'staff_id' => $staff->id,
                    'treatment_type_id' => $treatmentTypes->first()->id,
                    'status' => Appointment::STATUS_BOOKED,
                ],
            );
        }

        Appointment::query()
            ->whereNull('staff_id')
            ->update(['staff_id' => $staff->id]);

        Appointment::query()
            ->whereNull('treatment_type_id')
            ->update(['treatment_type_id' => $treatmentTypes->first()->id]);
    }
}
