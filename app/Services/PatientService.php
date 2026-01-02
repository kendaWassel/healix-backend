<?php 

namespace App\Services;

use App\Models\Order;
use App\Models\Upload;
use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\Pharmacist;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientService
{
    /**
     * Get patient's scheduled consultations
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getScheduledConsultations(int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user', 404);
        }

        return Consultation::with(['doctor.user', 'doctor.specialization'])
            ->where('patient_id', $patient->id)
            ->paginate($perPage);
    }

    /**
     * Format consultation data for response
     *
     * @param Consultation $consultation
     * @return array
     */
    public function formatConsultationData(Consultation $consultation): array
    {
        $doctor = $consultation->doctor;
        $doctorImage = null;
        if ($doctor && !empty($doctor->doctor_image_id)) {
            $upload = Upload::find($doctor->doctor_image_id);
            if ($upload && $upload->file_path) {
                $doctorImage = asset('storage/public/' . ltrim($upload->file_path, '/'));
            }
        }

        return [
            'id' => $consultation->id,
            'doctor_id' => $doctor ? $doctor->id : null,
            'doctor_name' => $doctor ? 'Dr. ' . $doctor->user?->full_name : null,
            'doctor_phone' => $doctor && $doctor->user ? $doctor->user->phone : null,
            'doctor_image' => $doctorImage,
            'type' => $consultation->type,
            'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
            'specialization' => $doctor && $doctor->specialization ? $doctor->specialization->name : null,
            'fee' => $doctor ? $doctor->consultation_fee : null,
            'status' => $consultation->status,
        ];
    }

    /**
     * Get patient prescriptions
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPrescriptions(int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        return Prescription::with(['doctor.user', 'prescriptionImage'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Format prescription data for response
     *
     * @param Prescription $prescription
     * @return array
     */
    public function formatPrescriptionData(Prescription $prescription): array
    {
        $doctorUser = $prescription->doctor?->user;
        $prescriptionImage = $prescription->prescriptionImage;

        // Determine doctor name - for patient-uploaded, it might be null
        $doctorName = null;
        if ($prescription->source === 'doctor_written' && $doctorUser) {
            $doctorName = 'Dr. ' . $doctorUser->full_name;
        } elseif ($prescription->source === 'patient_uploaded') {
            $doctorName = $doctorUser ? 'Dr. ' . $doctorUser->full_name : null;
        }

        // Get the latest order for this prescription to check for rejection
        $order = Order::where('prescription_id', $prescription->id)->latest()->first();
        $rejectionReason = null;
        if ($order && $order->status === 'rejected') {
            $rejectionReason = $order->rejection_reason;
        }

        $result = [
            'id' => $prescription->id,
            'doctor_name' => $doctorName,
            'diagnosis' => $prescription->diagnosis,
            'status' => $prescription->status,
            'issued_at' => $prescription->created_at?->toIso8601String(),
            'source' => $prescription->source,
            'image_url' => $prescriptionImage ? asset('storage/' . $prescriptionImage->file_path) : null,
        ];

        // Include rejection reason if the prescription was rejected
        if ($rejectionReason) {
            $result['rejection_reason'] = $rejectionReason;
        }

        return $result;
    }

    /**
     * Get prescription details
     *
     * @param int $prescriptionId
     * @return array
     */
    public function getPrescriptionDetails(int $prescriptionId): array
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        $prescription = Prescription::with(['doctor.user', 'medications.medication'])
            ->where('id', $prescriptionId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$prescription) {
            throw new \Exception('Prescription not found.', 404);
        }

        if ($prescription->source === 'patient_uploaded') {
            return [
                'id' => $prescription->id,
                'prescription_image_url' => $prescription->prescriptionImage ? asset('storage/' . $prescription->prescriptionImage->file_path) : null,
            ];
        }

        $doctorUser = $prescription->doctor?->user;

        $medicines = $prescription->medications->map(function ($item) {
            $med = $item->medication;
            return [
                'name' => $med?->name,
                'dosage' => $med?->dosage,
                'quantity' => $item->boxes,
                'instructions' => $item->instructions,
            ];
        })->values();

        return [
            'id' => $prescription->id,
            'doctor_name' => $doctorUser ? 'Dr. ' . $doctorUser->full_name : null,
            'diagnosis' => $prescription->diagnosis,
            'notes' => $prescription->notes,
            'status' => $prescription->status,
            'medicines' => $medicines,
        ];
    }

    /**
     * Upload paper prescription
     *
     * @param array $validated
     * @return array
     */
    public function uploadPaperPrescription(array $validated): array
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        $file = $validated['image'];

        // Ensure the storage directory exists
        if (!is_dir(storage_path('app/public/prescriptions'))) {
            mkdir(storage_path('app/public/prescriptions'), 0755, true);
        }

        $path = $file->store('prescriptions', 'public');

        if (!$path) {
            throw new \Exception('Failed to store the uploaded file.', 500);
        }

        $upload = Upload::create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'category' => 'prescription',
            'file' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime' => $file->getClientMimeType(),
        ]);

        // Create a prescription record linked to this upload
        $prescription = Prescription::create([
            'consultation_id' => null,
            'doctor_id' => null,
            'patient_id' => $patient->id,
            'diagnosis' => null,
            'notes' => null,
            'source' => 'patient_uploaded',
            'status' => 'created',
            'prescription_image_id' => $upload->id,
        ]);

        return [
            'upload_id' => $upload->id,
            'prescription_id' => $prescription->id,
        ];
    }

    /**
     * Send prescription to pharmacy
     *
     * @param int $prescriptionId
     * @param int $pharmacyId
     * @return array
     */
    public function sendPrescriptionToPharmacy(int $prescriptionId, int $pharmacyId): array
    {
        $user = Auth::user();
        $patient = $user->patient;
        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        $prescription = Prescription::where('id', $prescriptionId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$prescription) {
            throw new \Exception('Prescription not found.', 404);
        }

        // Pharmacy closed check
        $pharmacist = Pharmacist::find($pharmacyId);
        if (!$pharmacist || !$pharmacist->isOpen()) {
            throw new \Exception('Selected pharmacy is currently closed, please choose another one.', 400);
        }

        // Check if there's an existing rejected order for this prescription
        $existingOrder = Order::where('prescription_id', $prescription->id)
            ->whereIn('status', ['rejected', 'pending'])
            ->first();

        if ($existingOrder) {
            // Update the existing rejected order to send to new pharmacy
            $existingOrder->update([
                'pharmacist_id' => $pharmacyId,
                'status' => 'sent_to_pharmacy',
                'rejection_reason' => null,
            ]);
            $order = $existingOrder;
        } else {
            // Create new order for this prescription & pharmacy
            $order = Order::create([
                'prescription_id' => $prescription->id,
                'patient_id' => $patient->id,
                'pharmacist_id' => $pharmacyId,
                'status' => 'sent_to_pharmacy',
            ]);
        }

        // Mark prescription as sent to pharmacy
        $prescription->update([
            'pharmacist_id' => $pharmacyId,
            'status' => 'sent_to_pharmacy',
        ]);

        return [
            'order_id' => $order->id,
            'prescription_id' => $prescription->id,
            'pharmacy_id' => $pharmacyId,
            'status' => 'sent_to_pharmacy',
        ];
    }

    /**
     * Get prescription status
     *
     * @param int $prescriptionId
     * @return array
     */
    public function getPrescriptionStatus(int $prescriptionId): array
    {
        $prescription = Prescription::find($prescriptionId);

        if (!$prescription) {
            throw new \Exception('Prescription not found', 404);
        }

        // Fetch the latest order associated with the prescription
        $order = Order::with('pharmacist.pharmacy')
            ->where('prescription_id', $prescriptionId)
            ->latest()
            ->first();

        if (!$order) {
            throw new \Exception('No order found for this prescription', 404);
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'pharmacy' => [
                'id' => $order->pharmacist->pharmacy->id ?? null,
                'name' => $order->pharmacist->pharmacy->name ?? null,
            ],
        ];
    }

    /**
     * Get prescriptions with pricing
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPrescriptionsWithPricing(int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        return Prescription::where('patient_id', $patient->id)
            ->whereIn('status', ['accepted'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Format prescription with pricing data
     *
     * @param Prescription $prescription
     * @return array|null
     */
    public function formatPrescriptionWithPricing(Prescription $prescription): ?array
    {
        // Get the latest order for this prescription
        $order = Order::with('pharmacist')->where('prescription_id', $prescription->id)->latest()->first();

        if (!$order || !$order->pharmacist) {
            return null;
        }

        // Get pharmacy info
        $pharmacy = [
            'id' => $order->pharmacist->id,
            'name' => $order->pharmacist->pharmacy_name ?? 'Unknown Pharmacy',
        ];

        if ($prescription->status === 'rejected') {
            return [
                'prescription_id' => $prescription->id,
                'status' => $prescription->status,
                'source' => $prescription->source === 'patient_uploaded' ? 'paper' : 'electronic',
                'pharmacy' => $pharmacy,
                'rejection_reason' => $order->rejection_reason,
                'rejected_at' => $order->updated_at->toIso8601String(),
            ];
        }

        // For accepted prescriptions, show pricing
        $prescriptionMedications = \App\Models\PrescriptionMedication::with('medication')
            ->where('prescription_id', $prescription->id)
            ->get();

        // Get items
        $items = $prescriptionMedications->map(function ($medication) {
            return [
                'medicine_name' => $medication->medication->name ?? 'Unknown',
                'quantity' => $medication->boxes ?? 1,
                'price' => (float) ($medication->price ?? 0),
            ];
        })->values();

        // Calculate total quantity
        $totalQuantity = $prescriptionMedications->sum('boxes');

        return [
            'prescription_id' => $prescription->id,
            'status' => $prescription->status,
            'source' => $prescription->source === 'patient_uploaded' ? 'patient uploaded' : 'doctor',
            'pharmacy' => $pharmacy,
            'items' => $items,
            'total_quantity' => $totalQuantity,
            'total_price' => (float) ($prescription->total_price ?? 0),
            'priced_at' => $prescription->updated_at->toIso8601String(),
        ];
    }

    /**
     * Get orders status
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOrdersStatus(int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $patient = Patient::where('user_id', $user->id)->first();

        if (!$patient) {
            throw new \Exception('Patient not found for this user.', 404);
        }

        return Order::with([
            'prescription',
            'delivery.delivery.user',
            'delivery.delivery' => function ($query) {
                $query->with('user');
            }
        ])
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Format order status data
     *
     * @param Order $order
     * @return array
     */
    public function formatOrderStatusData(Order $order): array
    {
        $deliveryTask = $order->delivery;
        $deliveryData = null;

        if ($deliveryTask && $deliveryTask->delivery_id) {
            $delivery = $deliveryTask->delivery;
            $deliveryUser = $delivery->user;

            // Get delivery image
            $deliveryImageUrl = null;
            if ($delivery->delivery_image_id) {
                $upload = Upload::find($delivery->delivery_image_id);
                if ($upload && $upload->file_path) {
                    $deliveryImageUrl = asset('storage/' . ltrim($upload->file_path, '/'));
                }
            }

            $deliveryData = [
                'status' => $deliveryTask->status,
                'name' => $deliveryUser ? $deliveryUser->full_name : null,
                'phone' => $deliveryUser ? $deliveryUser->phone : null,
                'image' => $deliveryImageUrl,
                'plate_number' => $delivery->plate_number,
            ];
        }

        $result = [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'total_amount' => $order->total_amount ? (float) $order->total_amount : null,
        ];

        // Include rejection reason if order is rejected
        if ($order->status === 'rejected' && $order->rejection_reason) {
            $result['rejection_reason'] = $order->rejection_reason;
        }

        if ($deliveryData) {
            $result['delivery'] = $deliveryData;
        } else {
            $result['delivery'] = null;
            if ($order->status !== 'rejected') {
                $result['message'] = 'No delivery agent has accepted your order yet';
            }
        }

        return $result;
    }
}
