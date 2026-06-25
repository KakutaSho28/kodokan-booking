<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function patientLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'card_number' => ['required', 'string'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $patient = Patient::query()
            ->where('card_number', $credentials['card_number'])
            ->whereDate('birth_date', $credentials['birth_date'])
            ->first();

        if (! $patient) {
            return response()->json(['message' => '診察券番号または生年月日が一致しません。'], 401);
        }

        return response()->json([
            'token' => AccessToken::issue('patient', $patient->id),
            'role' => 'patient',
            'user' => $patient->load('assignedTherapist'),
            'can_book_rehab' => $patient->canBookRehab(),
            'message' => $patient->canBookRehab()
                ? 'リハビリ予約が可能です。'
                : '初診診断前のため、リハビリ予約はできません。',
        ]);
    }

    public function staffLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'staff_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $staff = Staff::query()->where('staff_id', $credentials['staff_id'])->first();

        if (! $staff || ! $staff->is_active || ! Hash::check($credentials['password'], $staff->password)) {
            return response()->json(['message' => 'スタッフIDまたはパスワードが一致しません。'], 401);
        }

        return response()->json([
            'token' => AccessToken::issue('staff', $staff->id),
            'role' => 'staff',
            'user' => $staff,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            AccessToken::query()->where('token_hash', hash('sha256', $token))->delete();
        }

        return response()->json(['message' => 'ログアウトしました。']);
    }
}
