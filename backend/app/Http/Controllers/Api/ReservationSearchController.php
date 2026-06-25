<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationSearchRequest;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;

class ReservationSearchController extends Controller
{
    public function __invoke(ReservationSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $reservations = Appointment::query()
            ->with(['patient', 'staff', 'treatmentType', 'slot'])
            ->when($filters['patient_name'] ?? null, function ($query, string $patientName): void {
                $query->whereHas('patient', function ($patientQuery) use ($patientName): void {
                    $patientQuery->where('name', 'like', '%'.$patientName.'%');
                });
            })
            ->when($filters['staff_id'] ?? null, function ($query, int $staffId): void {
                $query->where('staff_id', $staffId);
            })
            ->when($filters['date_from'] ?? null, function ($query, string $dateFrom): void {
                $query->whereHas('slot', function ($slotQuery) use ($dateFrom): void {
                    $slotQuery->whereDate('date', '>=', $dateFrom);
                });
            })
            ->when($filters['date_to'] ?? null, function ($query, string $dateTo): void {
                $query->whereHas('slot', function ($slotQuery) use ($dateTo): void {
                    $slotQuery->whereDate('date', '<=', $dateTo);
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->whereHas('slot')
            ->join('appointment_slots', 'appointments.appointment_slot_id', '=', 'appointment_slots.id')
            ->select('appointments.*')
            ->orderBy('appointment_slots.date')
            ->orderBy('appointment_slots.starts_at')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $reservations->items(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'from' => $reservations->firstItem(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'to' => $reservations->lastItem(),
                'total' => $reservations->total(),
            ],
            'links' => [
                'first' => $reservations->url(1),
                'last' => $reservations->url($reservations->lastPage()),
                'prev' => $reservations->previousPageUrl(),
                'next' => $reservations->nextPageUrl(),
            ],
        ]);
    }
}
