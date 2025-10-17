<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Delivery;
use App\Models\Pharmacist;
use App\Models\CareProvider;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = $this->createUser($request->validated());

            // Create user based on role
            switch ($request->role) {
                case 'patient':
                    $this->createPatient($user, $request->validated());
                    break;
                case 'doctor':
                    $this->createDoctor($user, $request->validated());
                    break;
                case 'pharmacist':
                    $this->createPharmacist($user, $request->validated());
                    break;
                case 'care_provider':
                    $this->createCareProvider($user, $request->validated());
                    break;
                case 'delivery':
                    $this->createDelivery($user, $request->validated());
                    break;
            }

            
            $fileIds = $this->getFileIds($request->validated());
            if ($fileIds->count() > 0) {
                Upload::whereIn('id', $fileIds)->update(['user_id' => $user->id]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'user_id' => $user->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function createUser($data)
    {
        return User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);
    }

    private function createPatient($user, $data)
    {
        Patient::create([
            'user_id' => $user->id,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
    }

    private function createDoctor($user, $data)
    {
        $specialization = Specialization::where('name', $data['specialization'])->first();
        if (!$specialization) {
            throw new \Exception('Specialization not found');
        }

        Doctor::create([
            'user_id' => $user->id,
            'specialization_id' => $specialization->id,
            'gender' => $data['gender'],
            'doctor_image_id' => $data['doctor_image_id'],
            'certificate_file_id' => $data['certificate_file_id'],
            'from' => $data['from'],
            'to' => $data['to'],
            'consultation_fee' => $data['consultation_fee'],
        ]);
    }

    private function createPharmacist($user, $data)
    {
        Pharmacist::create([
            'user_id' => $user->id,
            'pharmacy_name' => $data['pharmacy_name'],
            'cr_number' => $data['cr_number'],
            'address' => $data['address'],
            'license_file_id' => $data['license_file_id'],
            'from' => $data['from'],
            'to' => $data['to'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
    }

    private function createCareProvider($user, $data)
    {
        CareProvider::create([
            'user_id' => $user->id,
            'care_provider_image_id' => $data['care_provider_image_id'],
            'license_file_id' => $data['license_file_id'],
            'session_fee' => $data['session_fee'],
            'type' => $data['type'],
        ]);
    }

    private function createDelivery($user, $data)
    {
        Delivery::create([
            'user_id' => $user->id,
            'delivery_image_id' => $data['delivery_image_id'],
            'vehicle_type' => $data['vehicle_type'],
            'plate_number' => $data['plate_number'],
            'driving_license_id' => $data['driving_license_file_id'],
        ]);
    }

    private function getFileIds($data)
    {
        return collect($data)->only([
            'certificate_file_id',
            'doctor_image_id',
            'license_file_id',
            'care_provider_image_id',
            'delivery_image_id',
            'driving_license_id',
        ])->filter()->values();
    }
}
