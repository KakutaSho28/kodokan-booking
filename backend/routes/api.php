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

Route::get('/health', fn () => ['ok' => true, 'service' => 'Kodokan Booking API']);

Route::post('/auth/patient', [AuthController::class, 'patientLogin']);
Route::post('/auth/staff', [AuthController::class, 'staffLogin']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/therapists', [SlotController::class, 'therapists']);
Route::get('/staff', [StaffController::class, 'publicIndex']);
Route::get('/staffs', [StaffController::class, 'index']);
Route::post('/staffs', [StaffController::class, 'store']);
Route::put('/staffs/{staff}', [StaffController::class, 'update']);
Route::put('/staffs/{staff}/deactivate', [StaffController::class, 'deactivate']);
Route::get('/staffs/{staff}/shifts', [StaffController::class, 'shifts']);
Route::post('/shifts', [ShiftController::class, 'store']);
Route::get('/slots', [SlotController::class, 'index']);
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
