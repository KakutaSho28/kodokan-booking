<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ReservationSearchController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/health', fn () => ['ok' => true, 'service' => 'Kodokan Booking API']);

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('/auth/patient', [AuthController::class, 'patientLogin']);
        Route::post('/auth/staff', [AuthController::class, 'staffLogin']);
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/therapists', [SlotController::class, 'therapists']);
    Route::get('/staff', [StaffController::class, 'publicIndex']);
    Route::get('/slots', [SlotController::class, 'index']);

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::post('/patients', [PatientController::class, 'store']);
        Route::put('/patients/{patient}', [PatientController::class, 'update']);
        Route::put('/patients/{patient}/diagnose', [PatientController::class, 'diagnose']);
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy']);
        Route::get('/reservations/summary', [AppointmentController::class, 'summary']);
        Route::get('/reservations/cancelled', [AppointmentController::class, 'cancelled']);
        Route::post('/staffs', [StaffController::class, 'store']);
        Route::put('/staffs/{staff}', [StaffController::class, 'update']);
        Route::put('/staffs/{staff}/deactivate', [StaffController::class, 'deactivate']);
        Route::post('/shifts', [ShiftController::class, 'store']);
    });

    Route::middleware('role:admin,staff')->prefix('staff')->group(function (): void {
        Route::get('/staffs', [StaffController::class, 'index']);
        Route::get('/staffs/{staff}/shifts', [StaffController::class, 'shifts']);
        Route::get('/patients', [PatientController::class, 'index']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
        Route::get('/patients/{patient}/reservations', [PatientController::class, 'reservations']);
        Route::get('/reservations/search', ReservationSearchController::class);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::get('/waitlists', [WaitlistController::class, 'index']);
    });

    Route::middleware('role:patient')->prefix('portal')->group(function (): void {
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::post('/reservations', [AppointmentController::class, 'store']);
        Route::get('/my-reservations', [AppointmentController::class, 'myReservations']);
        Route::delete('/reservations/{appointment}', [AppointmentController::class, 'destroyPortal']);
        Route::get('/waitlists', [WaitlistController::class, 'index']);
        Route::post('/waitlists', [WaitlistController::class, 'store']);
        Route::delete('/waitlists/{waitlist}', [WaitlistController::class, 'destroy']);
    });

    // 既存フロント互換用のルート。Controller 側の権限チェックも残す。
    Route::get('/staffs', [StaffController::class, 'index']);
    Route::post('/staffs', [StaffController::class, 'store']);
    Route::put('/staffs/{staff}', [StaffController::class, 'update']);
    Route::put('/staffs/{staff}/deactivate', [StaffController::class, 'deactivate']);
    Route::get('/staffs/{staff}/shifts', [StaffController::class, 'shifts']);
    Route::post('/shifts', [ShiftController::class, 'store']);
    Route::put('/admin/patients/{patient}/diagnose', [PatientController::class, 'diagnose']);
    Route::get('/patients/{patient}/reservations', [PatientController::class, 'reservations']);
    Route::apiResource('/patients', PatientController::class);
    Route::get('/waitlists', [WaitlistController::class, 'index']);
    Route::post('/waitlists', [WaitlistController::class, 'store']);
    Route::delete('/waitlists/{waitlist}', [WaitlistController::class, 'destroy']);

    Route::get('/reservations/search', ReservationSearchController::class);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});
