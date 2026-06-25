<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentSlot;
use App\Models\Shift;
use App\Models\Therapist;
use App\Models\Waitlist;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function therapists(): JsonResponse
    {
        return response()->json([
            'data' => Therapist::query()
                ->where('is_active', true)
                ->whereHas('staff', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'therapist_id' => ['nullable', 'integer', 'exists:therapists,id'],
        ]);

        $date = $filters['date'];
        $therapistIds = isset($filters['therapist_id'])
            ? [(int) $filters['therapist_id']]
            : Therapist::query()
                ->where('is_active', true)
                ->whereHas('staff', fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->pluck('id')
                ->all();

        return response()->json([
            'data' => collect($therapistIds)
                ->flatMap(fn (int $therapistId): array => $this->availabilityForTherapist($date, $therapistId))
                ->values(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availabilityForTherapist(string $date, int $therapistId): array
    {
        $therapist = Therapist::query()->with('staff')->find($therapistId);

        if (! $therapist?->staff?->is_active) {
            return [];
        }

        $shift = Shift::query()
            ->where('staff_id', $therapist->staff_id)
            ->whereDate('work_date', $date)
            ->where('is_day_off', false)
            ->first();

        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return [];
        }

        $slots = AppointmentSlot::query()
            ->with(['therapist', 'appointments.patient'])
            ->withCount([
                'waitlists as waiting_waitlists_count' => fn ($query) => $query->where('status', Waitlist::STATUS_WAITING),
            ])
            ->whereDate('date', $date)
            ->where('therapist_id', $therapistId)
            ->get()
            ->keyBy(fn (AppointmentSlot $slot): string => substr($slot->starts_at, 0, 5));

        $data = [];
        $cursor = CarbonImmutable::createFromFormat('H:i:s', $shift->start_time);
        $end = CarbonImmutable::createFromFormat('H:i:s', $shift->end_time);

        while ($cursor < $end) {
            $time = $cursor->format('H:i');
            $endsAt = $cursor->addMinutes(30)->format('H:i');
            $slot = $slots->get($time);
            $maxCapacity = $slot?->capacity ?? 1;
            $confirmedCount = $slot
                ? $slot->appointments->where('status', 'booked')->count()
                : 0;
            $availableCount = max($maxCapacity - $confirmedCount, 0);
            $status = $availableCount > 0 ? 'available' : 'full';

            $data[] = [
                'id' => $slot?->id,
                'appointment_slot_id' => $slot?->id,
                'therapist_id' => $therapistId,
                'date' => $date,
                'time' => $time,
                'starts_at' => $time.':00',
                'ends_at' => $endsAt.':00',
                'status' => $status,
                'available_count' => $availableCount,
                'max_capacity' => $maxCapacity,
                'booked_count' => $confirmedCount,
                'is_available' => $status === 'available',
                'availability_mark' => $status === 'available' ? '○' : '×',
                'waitlist_count' => $slot?->waiting_waitlists_count ?? 0,
                'therapist' => $slot?->therapist,
            ];

            $cursor = $cursor->addMinutes(30);
        }

        return $data;
    }
}
