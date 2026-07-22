<?php

return [

    /*
    |--------------------------------------------------------------------------
    | تسميات العرض للقيم الثابتة (Enums)
    |--------------------------------------------------------------------------
    |
    | القيم الخام المخزنة في قاعدة البيانات هي معرّفات داخلية ولا تُترجم أبدًا —
    | فهي تستمر بالمرور عبر الـ API كما هي حتى لا تتأثر التطبيقات الحالية.
    | هذه التسميات هي المقابل المقروء للمستخدم، وتُضاف كحقول `*_label`.
    |
    */

    'role' => [
        'patient' => 'مريض',
        'doctor' => 'طبيب',
        'pharmacist' => 'صيدلاني',
        'care_provider' => 'مقدم رعاية',
        'nurse' => 'ممرض',
        'physiotherapist' => 'أخصائي علاج طبيعي',
        'delivery' => 'مندوب توصيل',
        'admin' => 'مدير النظام',
    ],

    'gender' => [
        'male' => 'ذكر',
        'female' => 'أنثى',
        'other' => 'آخر',
    ],

    'account_status' => [
        'pending' => 'بانتظار الموافقة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
    ],

    'consultation_type' => [
        'call_now' => 'مكالمة فورية',
        'schedule' => 'موعد مجدول',
    ],

    'consultation_status' => [
        'pending' => 'قيد الانتظار',
        'in_progress' => 'جارية',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
    ],

    'home_visit_status' => [
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبولة',
        'in_progress' => 'جارية',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
        'canceled' => 'ملغاة',
        'rescheduled' => 'أُعيد جدولتها',
    ],

    'service_type' => [
        'nurse' => 'تمريض',
        'physiotherapist' => 'علاج طبيعي',
    ],

    'prescription_source' => [
        'doctor_written' => 'مكتوبة من الطبيب',
        'patient_uploaded' => 'مرفوعة من المريض',
    ],

    'prescription_status' => [
        'created' => 'تم إنشاؤها',
        'sent_to_pharmacy' => 'أُرسلت إلى الصيدلية',
        'pending' => 'قيد المعالجة',
        'accepted' => 'مقبولة',
        'priced' => 'مُسعّرة',
        'rejected' => 'مرفوضة',
    ],

    'order_status' => [
        'pending' => 'قيد الانتظار',
        'sent_to_pharmacy' => 'أُرسل إلى الصيدلية',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'ready_for_delivery' => 'جاهز للتوصيل',
        'out_for_delivery' => 'في الطريق',
        'delivered' => 'تم التسليم',
    ],

    'delivery_task_status' => [
        'pending' => 'قيد الانتظار',
        'picking_up_the_order' => 'جارٍ استلام الطلب',
        'on_the_way' => 'في الطريق',
        'delivered' => 'تم التسليم',
    ],

    'delivery_candidate_status' => [
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'expired' => 'منتهي الصلاحية',
    ],

    'payment_status' => [
        'pending' => 'قيد الانتظار',
        'paid' => 'مدفوع',
        'failed' => 'فشل',
        'cancelled' => 'ملغى',
    ],

    'message_sender' => [
        'patient' => 'المريض',
        'assistant' => 'المساعد',
    ],

    'message_type' => [
        'text' => 'نص',
        'voice' => 'صوت',
    ],

    'message_status' => [
        'uploaded' => 'تم الرفع',
        'transcribed' => 'تم التفريغ',
        'failed' => 'فشل',
    ],

    'lab_severity' => [
        'normal' => 'طبيعي',
        'mild' => 'خفيف',
        'moderate' => 'متوسط',
        'severe' => 'شديد',
        'critical' => 'حرج',
    ],

    'triage' => [
        'High' => 'أولوية عالية',
        'Medium' => 'أولوية متوسطة',
        'Low' => 'أولوية منخفضة',
    ],

    // شدة التداخل الدوائي القادمة من خدمة DDI. القيمة الخام تمر دون ترجمة،
    // وهذه تسمية العرض فقط.
    'ddi_severity' => [
        'none' => 'لا يوجد تداخل معروف',
        'minor' => 'طفيف',
        'moderate' => 'متوسط',
        'major' => 'شديد',
        'contraindicated' => 'ممنوع الاستخدام معًا',
        'unknown' => 'غير معروف',
    ],

    // الألقاب المستخدمة عند تكوين اسم العرض.
    'title' => [
        'doctor_prefix' => 'د.',
    ],

];
