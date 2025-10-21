<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|unique:users,phone',
            'role' => 'required|string|in:patient,doctor,pharmacist,care_provider,delivery,admin'
        ];

        // Add role-specific rules based on the role field
        $role = request()->get('role');
        if ($role) {
            $roleRules = match ($role) {
                'patient' => [
                    'birth_date' => 'required|date',
                    'gender' => 'required|string|in:male,female',
                    'address' => 'required|string',
                    'latitude' => 'required|numeric',
                    'longitude' => 'required|numeric',
                ],
                'doctor' => [
                    'specialization' => 'string',
                    'certificate_file_id' => 'required|exists:uploads,id',
                    'doctor_image_id' => 'required|exists:uploads,id',
                    'gender' => 'required|string|in:male,female',
                    'from' => 'required|string',
                    'to' => 'required|string',
                    'consultation_fee' => 'required|numeric',
                ],
                'pharmacist' => [
                    'pharmacy_name' => 'required|string',
                    'cr_number' => 'required|integer',
                    'address' => 'required|string',
                    'license_file_id' => 'required|exists:uploads,id',
                    'from' => 'required|string',
                    'to' => 'required|string',
                    'latitude' => 'required|numeric',
                    'longitude' => 'required|numeric',
                ],
                'care_provider' => [
                    'care_provider_image_id' => 'required|exists:uploads,id',
                    'license_file_id' => 'required|exists:uploads,id',
                    'session_fee' => 'required|numeric|min:0',
                    'type' => 'required|string|in:nurse,physiotherapist',
                ],
                'delivery' => [
                    'delivery_image_id' => 'required|exists:uploads,id',
                    'vehicle_type' => 'required|string|max:50',
                    'plate_number' => 'required|string|max:50',
                    'driving_license_file_id' => 'required|exists:uploads,id',
                ],
                default => [],
            };

            $rules = array_merge($rules, $roleRules);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email already exists',
            'phone.unique' => 'Phone number already exists',
            'password.min' => 'Password must be at least 8 characters',
            'role.in' => 'Invalid role selected',
            'gender.in' => 'Gender must be either male or female',
            'type.in' => 'Care provider type must be either nurse or physiotherapist',
        ];
    }


}