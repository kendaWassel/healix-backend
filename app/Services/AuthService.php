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
use App\Models\Specialization;
use App\Mail\VerificationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AuthService
{

    public function authenticate(string $email, string $password): ?array //
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return [
            // 'user' => $user,
            'token' => $token,
            // 'token_type' => 'Bearer',
            'role' => $user->role,
            'email_verified' => $user->hasVerifiedEmail()
        ];
    }

    public function logout(Request $request): bool
    {
        $token = $request->user()->currentAccessToken;
        
        if ($token) {
            $token->delete();
            return true;
        }

        return false;
    }

    /**
     * Generate token for user
     *
     * @param User $user
     * @param string $tokenName
     * @return string
     */
    public function generateToken(User $user, string $tokenName = 'API Token'): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }

    /**
     * Verify user credentials
     *
     * @param string $email
     * @param string $password
     * @return User|null
     */
    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }

        return null;
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

            // Send verification email
            $this->sendVerificationEmail($user);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'User registered successfully. Please check your email for verification.',
                'user_id' => $user->id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
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
        Patient::create([
            'user_id' => $user->id,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
    }


    private function createDoctor(User $user, array $data): void
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
            'care_provider_image_id' => $data['care_provider_image_id'],
            'license_file_id' => $data['license_file_id'],
            'session_fee' => $data['session_fee'],
            'type' => $data['type'],
        ]);
    }

    /**
     * Create delivery profile
     *
     * @param User $user
     * @param array $data
     * @return void
     */
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

    /**
     * Handle file uploads by linking them to the user
     *
     * @param User $user
     * @param array $data
     * @return void
     */
    private function handleFileUploads(User $user, array $data): void
    {
        $fileIds = $this->getFileIds($data);
        
        if ($fileIds->count() > 0) {
            Upload::whereIn('id', $fileIds)->update(['user_id' => $user->id]);
        }
    }

    /**
     * Extract file IDs from data
     *
     * @param array $data
     * @return \Illuminate\Support\Collection
     */
    private function getFileIds(array $data): \Illuminate\Support\Collection
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

    /**
     * Send verification email to user
     *
     * @param User $user
     * @return void
     */
    private function sendVerificationEmail(User $user): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::to($user->email)->send(new VerificationEmail($user, $verificationUrl));
    }
}
