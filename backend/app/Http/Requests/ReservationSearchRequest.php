<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];

        if ($this->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return [
            'patient_name' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
            'status' => ['nullable', Rule::in([Appointment::STATUS_BOOKED, Appointment::STATUS_CANCELLED])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
