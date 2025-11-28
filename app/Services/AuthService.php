<?php

namespace App\Services;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Delivery;
use App\Models\Pharmacist;
use App\Models\CareProvider;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Specialization;
use App\Mail\VerificationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthService
{

    public function authenticate(string $email, string $password)
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken($user->email.'api-token')->plainTextToken;

        return [
            'token' => $token,
            'role' => $user->role,
            'email_verified' => $user->hasVerifiedEmail()
        ];
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken;
        
        if ($token) {
            $token->delete();
            return true;
        }

        return false;
    }

    public function register(array $data): array
    {
        DB::beginTransaction();

        try {
            $user = $this->createUser($data);

            // Create role-specific profile
            $this->createRoleProfile($user, $data);

            // Handle file uploads
            $this->handleFileUploads($user, $data);

            // Send verification email (capture success/failure)
            $emailSent = $this->sendVerificationEmail($user);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'User registered successfully. Please check your email for verification.',
                'user_id' => $user->id,
                'email_sent' => $emailSent,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed in AuthService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    private function createUser(array $data): User
    {
        return User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);
    }

    private function createRoleProfile(User $user, array $data): void
    {
        switch ($data['role']) {
            case 'patient':
                $this->createPatient($user, $data);
                break;
            case 'doctor':
                $this->createDoctor($user, $data);
                break;
            case 'pharmacist':
                $this->createPharmacist($user, $data);
                break;
            case 'care_provider':
                $this->createCareProvider($user, $data);
                break;
            case 'delivery':
                $this->createDelivery($user, $data);
                break;
            default:
                throw new \Exception('Invalid role specified');
        }
    }

    private function createPatient(User $user, array $data): void
    {
        $patient = Patient::create([
            'user_id' => $user->id,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        $medical = $data['medical_record'] ?? $data;
        if (
            isset($medical['treatment_plan']) || isset($medical['diagnosis']) || isset($medical['attachments']) || isset($medical['chronic_diseases']) || isset($medical['previous_surgeries']) || isset($medical['allergies']) || isset($medical['current_medications'])
        ) {
            
            MedicalRecord::create([
                'patient_id' => $patient->id,
                'treatment_plan' => $medical['treatment_plan'] ?? '',
                'diagnosis' => $medical['diagnosis'] ?? '',
                'attachments' => is_array($medical['attachments']) ? implode(',', $medical['attachments']) : ($medical['attachments'] ?? ''),
                'chronic_diseases' => $medical['chronic_diseases'] ?? '',
                'previous_surgeries' => $medical['previous_surgeries'] ?? '',
                'allergies' => $medical['allergies'] ?? '',
                'current_medications' => $medical['current_medications'] ?? '',
            ]);
        }
        if (!empty($medical['attachments']) && is_array($medical['attachments'])) {
        Upload::whereIn('id', $medical['attachments'])
            ->update([
                'user_id' => $user->id,
                'patient_id' => $patient->id,
            ]);
    }
    }



    private function createDoctor(User $user, array $data): void
    {
        // Handle specialization
        $specialization = Specialization::where('name', $data['specialization'])->first();
        Doctor::create([
            'user_id' => $user->id,
            'specialization_id' => $specialization->id,
            'gender' => $data['gender'],
            'doctor_image_id' => $data['doctor_image_id'] ?? null,
            'certificate_file_id' => $data['certificate_file_id'] ?? null,
            'from' => $data['from'],
            'to' => $data['to'],
            'consultation_fee' => $data['consultation_fee'],
        ]);
    }


    private function createPharmacist(User $user, array $data): void
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


    private function createCareProvider(User $user, array $data): void
    {
        CareProvider::create([
            'user_id' => $user->id,
            'care_provider_image_id' => $data['care_provider_image_id'] ?? null,
            'license_file_id' => $data['license_file_id'] ?? null,
            'session_fee' => $data['session_fee'],
            'type' => $data['type'],
        ]);
    }


    private function createDelivery(User $user, array $data): void
    {
        Delivery::create([
            'user_id' => $user->id,
            'delivery_image_id' => $data['delivery_image_id'],
            'vehicle_type' => $data['vehicle_type'],
            'plate_number' => $data['plate_number'],
            'driving_license_id' => $data['driving_license_file_id'],
        ]);
    }

    private function handleFileUploads(User $user, array $data): void
    {
        $fileIds = $this->getFileIds($data);
        
        if ($fileIds->count() > 0) {
            Upload::whereIn('id', $fileIds)->update(['user_id' => $user->id]);
        }
    }

    private function getFileIds(array $data): \Illuminate\Support\Collection
    {
        return collect($data)->only([
            'medical_record.attachments',
            'certificate_file_id',
            'doctor_image_id',
            'license_file_id',
            'care_provider_image_id',
            'delivery_image_id',
            'driving_license_id',
        ])->filter()->values();
    }

    private function sendVerificationEmail(User $user): bool
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        try {
            Mail::to($user->email)->send(new VerificationEmail($user, $verificationUrl));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
