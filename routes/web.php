<?php

use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use App\Events\ConsultationCreated;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {

//     return view('welcome');

// });

// Route::get('/test', function () {

//     Mail::raw('This is a test email from Laravel ', function ($message) {

//         $message->to('lililampa9@gmail.com')

//                 ->subject('Testing SMTP');

//     });



//     return 'Mail sent!';

// });


Route::get('/', function () {
    // $patientId =  22; // أو جاي من request
    // $doctorId = 13;   // نفس الشي

    // // استرجع كائن المستخدم من قاعدة البيانات
    // $patient = User::findOrFail($patientId)->load('patient');
    // $doctor =  User::findOrFail($doctorId)->load('doctor');
    

    // $consultation = Consultation::create(['patient_id' => $patientId, 'doctor_id' => $doctorId]); // استرجع أو أنشئ الـ Consultation

    // event(new ConsultationCreated($patient, $doctor, $consultation));

    // return 'Event fired';
    return view('welcome');
});

