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
                'question_ar' => 'ما هو Healix؟',
                'answer' => 'Healix is a digital health platform to book doctor consultations, request home care, manage prescriptions, and get medications delivered.',
                'answer_ar' => 'Healix منصة صحية رقمية لحجز استشارات الأطباء، وطلب الرعاية المنزلية، وإدارة الوصفات الطبية، وتوصيل الأدوية.',
            ],
            [
                'question' => 'How do I book an appointment?',
                'question_ar' => 'كيف أحجز موعدًا؟',
                'answer' => 'Search by specialty or doctor, pick a time, select consultation type(call or schedule), and confirm your booking.',
                'answer_ar' => 'ابحث حسب التخصص أو الطبيب، واختر الوقت المناسب، ثم حدد نوع الاستشارة (مكالمة فورية أو موعد مجدول)، وأكّد الحجز.',
            ],
            [
                'question' => 'Do you offer online consultations?',
                'question_ar' => 'هل تقدمون استشارات عبر الإنترنت؟',
                'answer' => 'Yes. Join secure audio calls from your device at the scheduled time. A stable internet connection is recommended.',
                'answer_ar' => 'نعم. يمكنك الانضمام إلى مكالمات صوتية آمنة من جهازك في الموعد المحدد. يُنصح باتصال إنترنت مستقر.',
            ],
            [
                'question' => 'What areas do you cover?',
                'question_ar' => 'ما المناطق التي تغطونها؟',
                'answer' => 'Home visits and medication delivery are available in selected areas and depend on provider availability.',
                'answer_ar' => 'تتوفر الزيارات المنزلية وتوصيل الأدوية في مناطق محددة، وتعتمد على توفر مقدمي الخدمة.',
            ],
            [
                'question' => 'How does pricing work?',
                'question_ar' => 'كيف يتم تحديد الأسعار؟',
                'answer' => 'Consultation and home visit fees are shown before you confirm. Pharmacy orders are priced after prescription review. No hidden charges.',
                'answer_ar' => 'تُعرض رسوم الاستشارة والزيارة المنزلية قبل التأكيد. أما طلبات الصيدلية فتُسعّر بعد مراجعة الوصفة الطبية. لا توجد رسوم خفية.',
            ],
            [
                'question' => 'What payment methods are accepted?',
                'question_ar' => 'ما طرق الدفع المقبولة؟',
                'answer' => 'We accept major cards and mobile wallets. Cash may be available for some home services depending on location.',
                'answer_ar' => 'نقبل البطاقات البنكية الرئيسية والمحافظ الإلكترونية. وقد يتوفر الدفع النقدي لبعض الخدمات المنزلية حسب الموقع.',
            ],
            [
                'question' => 'Do you accept insurance?',
                'question_ar' => 'هل تقبلون التأمين الصحي؟',
                'answer' => 'Insurance options vary by provider. Where supported, you can upload policy or claims information during checkout.',
                'answer_ar' => 'تختلف خيارات التأمين حسب مقدم الخدمة. وحيثما كان مدعومًا، يمكنك رفع بيانات وثيقة التأمين أو المطالبة أثناء إتمام الطلب.',
            ],
            [
                'question' => 'How do prescriptions and delivery work?',
                'question_ar' => 'كيف تعمل الوصفات الطبية والتوصيل؟',
                'answer' => 'Doctors issue digital prescriptions in-app. You can also upload external prescriptions for review and delivery to your address.',
                'answer_ar' => 'يصدر الأطباء وصفات طبية رقمية داخل التطبيق. كما يمكنك رفع وصفات طبية خارجية لمراجعتها وتوصيلها إلى عنوانك.',
            ],
            [
                'question' => 'How fast is medication delivery?',
                'question_ar' => 'ما سرعة توصيل الأدوية؟',
                'answer' => 'Most orders arrive the same day after payment, typically within a few hours in covered areas.',
                'answer_ar' => 'تصل معظم الطلبات في نفس اليوم بعد الدفع، وعادةً خلال بضع ساعات في المناطق المشمولة بالتغطية.',
            ],
            [
                'question' => 'How is my data protected?',
                'question_ar' => 'كيف تُحمى بياناتي؟',
                'answer' => 'We use encryption and strict access controls so only you and authorized providers can view your information.',
                'answer_ar' => 'نستخدم التشفير وضوابط وصول صارمة، بحيث لا يطّلع على معلوماتك سواك ومقدمو الرعاية المصرح لهم.',
            ],
            [
                'question' => 'How can I contact support?',
                'question_ar' => 'كيف يمكنني التواصل مع الدعم؟',
                'answer' => 'Use Help & Support in the app or email support@healix.example. Support is available daily from 08:00 to 20:00.',
                'answer_ar' => 'استخدم قسم "المساعدة والدعم" في التطبيق أو راسلنا على support@healix.example. الدعم متاح يوميًا من 08:00 حتى 20:00.',
            ],
            [
                'question' => 'Is Healix for emergencies?',
                'question_ar' => 'هل Healix مخصص لحالات الطوارئ؟',
                'answer' => 'No. For symptoms like chest pain, stroke signs, severe bleeding, or breathing difficulty, call your local emergency number immediately.',
                'answer_ar' => 'لا. في حال ظهور أعراض مثل ألم الصدر، أو علامات السكتة الدماغية، أو النزيف الشديد، أو صعوبة التنفس، اتصل فورًا برقم الطوارئ المحلي.',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
