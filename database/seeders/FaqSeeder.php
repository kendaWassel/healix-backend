<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Faq::truncate();

        $faqs = [
            [
                'question' => 'What is Healix?',
                'answer' => 'Healix is a digital health platform to book doctor consultations, request home care, manage prescriptions, and get medications delivered.',
            ],
            [
                'question' => 'How do I book an appointment?',
                'answer' => 'Search by specialty or doctor, pick a time, select consultation type(call or schedule), and confirm your booking.',
            ],
            [
                'question' => 'Do you offer online consultations?',
                'answer' => 'Yes. Join secure audio calls from your device at the scheduled time. A stable internet connection is recommended.',
            ],
            [
                'question' => 'What areas do you cover?',
                'answer' => 'Home visits and medication delivery are available in selected areas and depend on provider availability.',
            ],
            [
                'question' => 'How does pricing work?',
                'answer' => 'Consultation and home visit fees are shown before you confirm. Pharmacy orders are priced after prescription review. No hidden charges.',
            ],
            [
                'question' => 'What payment methods are accepted?',
                'answer' => 'We accept major cards and mobile wallets. Cash may be available for some home services depending on location.',
            ],
            [
                'question' => 'Do you accept insurance?',
                'answer' => 'Insurance options vary by provider. Where supported, you can upload policy or claims information during checkout.',
            ],
            [
                'question' => 'How do prescriptions and delivery work?',
                'answer' => 'Doctors issue digital prescriptions in-app. You can also upload external prescriptions for review and delivery to your address.',
            ],
            [
                'question' => 'How fast is medication delivery?',
                'answer' => 'Most orders arrive the same day after payment, typically within a few hours in covered areas.',
            ],
            [
                'question' => 'How is my data protected?',
                'answer' => 'We use encryption and strict access controls so only you and authorized providers can view your information.',
            ],
            [
                'question' => 'How can I contact support?',
                'answer' => 'Use Help & Support in the app or email support@healix.example. Support is available daily from 08:00 to 20:00.',
            ],
            [
                'question' => 'Is Healix for emergencies?',
                'answer' => 'No. For symptoms like chest pain, stroke signs, severe bleeding, or breathing difficulty, call your local emergency number immediately.',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
