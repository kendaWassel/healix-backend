<?php

namespace App\Services;

use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Models\{User,Doctor,Patient,Delivery,Pharmacist,CareProvider,Upload,MedicalRecord,Specialization};
use Illuminate\Http\Request;
use App\Mail\VerificationEmail;
use Illuminate\Support\Facades\{DB,Log,Hash,Mail,URL};

class AuthService
{

    public function authenticate(string $email, string $password)
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password) || !$user->isApproved() || !$user->isActive()) {
            return null;
        }

        //return the type of care provider (nurse, therapist) along with role
        if ($user->role === 'care_provider') {
            $careProvider = $user->careProvider;
            if ($careProvider) {
                $user->role = $careProvider->type;
            }
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
        // currentAccessToken() is a plain method on Sanctum's HasApiTokens trait,
        // not a relationship. Accessing it as a property (no parentheses) makes
        // Eloquent's __get() try to resolve it as a relation via the method's
        // return value, which isn't a Relation instance — hence the "must return
        // a relationship instance" LogicException. Must be called with ().
        $token = $request->user()->currentAccessToken();
        
        if ($token) {
            $token->delete();
            return response()->json(['message' => __('auth.logged_out_success')], 200)  ;
        }

        return response()->json(['message' => __('auth.logout_failed')], 400);
    }

    /**
     * @param bool $adminCreated When true (Admin::addUser), the account is
     *   approved/activated/verified immediately instead of going through the
     *   normal self-registration approval + email-verification flow — the
     *   admin creating it directly IS the approval. Setting
     *   email_verified_at before sendVerificationEmail() runs makes that
     *   call's own hasVerifiedEmail() guard skip sending a "verify your
     *   email" mail the account no longer needs.
     */
    public function register(array $data, bool $adminCreated = false): array
    {
        DB::beginTransaction();

        try {
            $user = $this->createUser($data);

            if ($adminCreated) {
                $user->status = 'approved';
                $user->is_active = true;
                $user->approved_at = now();
                $user->email_verified_at = now();
                $user->admin_note = 'Created directly by admin ID: ' . auth()->id();
                $user->save();
            }

            // Create role-specific profile
            $this->createRoleProfile($user, $data);

            // Handle file uploads
            $this->handleFileUploads($user, $data);

            // Send verification email. A failure here must not roll back an
            // otherwise-successful registration — it's reported honestly below
            // instead (and logged inside sendVerificationEmail()). No-op when
            // $adminCreated already marked the email verified above.
            $emailSent = VerifyEmailController::sendVerificationEmail($user);

            DB::commit();

            return [
                'status' => 'success',
                'message' => $adminCreated
                    ? 'User created successfully.'
                    : ($emailSent
                        ? 'User registered successfully. Please check your email for verification.'
                        : 'User registered successfully, but we could not send the verification email. Please request a new one.'),
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
            // Remember the language the user registered in so queued mail and
            // SMS (which run without a request) reach them in that language.
            'preferred_locale' => app()->getLocale(),
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
                throw new \Exception(__('auth.invalid_role'));
        }
    }

    private function createPatient(User $user, array $data): void
    {
        $patient = Patient::create([
            'user_id' => $user->id,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        // Always create a medical record for new patients
        $medical = $data['medical_record'] ?? [];

        // Registration collects pregnancy as yes/no; the column is boolean.
        $isPregnant = ($medical['is_pregnant'] ?? null) === 'yes';

        $medicalRecord = MedicalRecord::create([
            'patient_id' => $patient->id,
            'doctor_id' => null, // No doctor assigned during registration
            'treatment_plan' => $medical['treatment_plan'] ?? null,
            'diagnosis' => $medical['diagnosis'] ?? null,
            // Array of DrugCentral-standard condition names (cast to JSON).
            'chronic_diseases' => $medical['chronic_diseases'] ?? null,
            'other_conditions' => $medical['other_conditions'] ?? null,
            'previous_surgeries' => $medical['previous_surgeries'] ?? null,
            'allergies' => $medical['allergies'] ?? null,
            'current_medications' => $medical['current_medications'] ?? null,
            'is_pregnant' => $isPregnant,
        ]);

        // Attach uploads if provided (using pivot table relationship)
        if (isset($medical['attachments']) && is_array($medical['attachments']) && !empty($medical['attachments'])) {
            // Validate that all upload IDs exist
            $file = Upload::whereIn('id', $medical['attachments'])
                ->where('category', 'medical_record')
                //split to file and image
                ->pluck('id')
                ->toArray();
            
            if (!empty($validUploadIds)) {
                // Update uploads to link to the user
                Upload::whereIn('id', $validUploadIds)->update(['user_id' => $user->id]);
                
                // Attach uploads to medical record using pivot table
                $medicalRecord->attachments()->attach($validUploadIds);
            }
        }
    }



    private function createDoctor(User $user, array $data): void
    {
        // Handle specialization
        // Accept the specialization in either language: the registration form is
        // bilingual, so the client may post back whichever label it displayed.
        $specialization = Specialization::where('name', $data['specialization'])
            ->orWhere('name_ar', $data['specialization'])
            ->first();
        
        if (!$specialization) {
            throw new \Exception(__('auth.specialization_not_found', ['name' => $data['specialization']]));
        }
        
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
            'driving_license_id' => $data['driving_license_id'],
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
        $fileIds = collect();
        
        // Handle nested medical_record.attachments
        if (isset($data['medical_record']['attachments']) && is_array($data['medical_record']['attachments'])) {
            $fileIds = $fileIds->merge($data['medical_record']['attachments']);
        }
        
        // Handle flat file ID fields
        $flatFields = [
            'certificate_file_id',
            'doctor_image_id',
            'license_file_id',
            'care_provider_image_id',
            'delivery_image_id',
            'driving_license_id',
        ];
        
        foreach ($flatFields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $fileIds->push($data[$field]);
            }
        }
        
        return $fileIds->filter()->values();
    }


}