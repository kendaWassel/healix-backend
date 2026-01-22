<?php

namespace App\Providers;

use App\Models\Consultation;
use App\Models\CareProvider;
use App\Models\Delivery;
use App\Models\DeliveryTask;
use App\Models\Doctor;
use App\Models\Faq;
use App\Models\FirstAid;
use App\Models\HomeVisit;
use App\Models\MedicalRecord;
use App\Models\Medication;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\Rating;
use App\Models\Specialization;
use App\Models\Upload;
use App\Policies\CareProviderPolicy;
use App\Policies\ConsultationPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\DeliveryTaskPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\FaqPolicy;
use App\Policies\FirstAidPolicy;
use App\Policies\HomeVisitPolicy;
use App\Policies\MedicalRecordPolicy;
use App\Policies\MedicationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PharmacistPolicy;
use App\Policies\PrescriptionPolicy;
use App\Policies\PrescriptionMedicationPolicy;
use App\Policies\RatingPolicy;
use App\Policies\SpecializationPolicy;
use App\Policies\UploadPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Patient::class => PatientPolicy::class,
        Consultation::class => ConsultationPolicy::class,
        Prescription::class => PrescriptionPolicy::class,
        MedicalRecord::class => MedicalRecordPolicy::class,
        Order::class => OrderPolicy::class,
        HomeVisit::class => HomeVisitPolicy::class,
        Delivery::class => DeliveryPolicy::class,
        Doctor::class => DoctorPolicy::class,
        CareProvider::class => CareProviderPolicy::class,       
        Upload::class => UploadPolicy::class,
        Medication::class => MedicationPolicy::class,
        PrescriptionMedication::class => PrescriptionMedicationPolicy::class,
        Specialization::class => SpecializationPolicy::class,
        Faq::class => FaqPolicy::class,
        FirstAid::class => FirstAidPolicy::class,
        DeliveryTask::class => DeliveryTaskPolicy::class,
        Pharmacist::class => PharmacistPolicy::class,
        Rating::class => RatingPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
