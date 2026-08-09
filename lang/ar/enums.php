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
        // مفاتيح بأحرف كبيرة تطابق ما تُعيده خدمة تداخلات الأدوية تمامًا.
        'Minor' => 'طفيف',
        'Moderate' => 'متوسط',
        'Major' => 'شديد',
    ],

    // درجة الثقة في شدة التداخل المتوقّعة.
    'ddi_confidence' => [
        'LOW' => 'ثقة منخفضة',
        'MEDIUM' => 'ثقة متوسطة',
        'HIGH' => 'ثقة عالية',
        'UNCERTAIN' => 'غير مؤكّدة',
        'OVERRIDE' => 'مؤكّدة سريريًا',
    ],

    // طريقة اكتشاف تعارض الحساسية.
    'ddi_detected_by' => [
        'direct_match' => 'حساسية مباشرة',
        'pharmacophore_class' => 'نفس الزمرة الدوائية',
        'structural_similarity' => 'تشابه بنيوي',
        'atc_class' => 'نفس تصنيف ATC',
        'cross_reactivity' => 'تفاعل متصالب',
    ],

    // مستوى خطورة نتيجة الحساسية / التفاعل المتصالب.
    'ddi_risk' => [
        'CRITICAL' => 'حرج',
        'HIGH' => 'مرتفع',
        'MODERATE' => 'متوسط',
        'LOW' => 'منخفض',
    ],

    // فئة خطورة الحمل (فئات FDA الحرفية).
    'ddi_pregnancy_category' => [
        'X' => 'الفئة X — ممنوع في الحمل',
        'D' => 'الفئة D — دليل مؤكّد على الخطر',
        'D*' => 'الفئة *D — يُتجنّب في أواخر الحمل',
        'C' => 'الفئة C — يُستخدم بحذر',
        'B' => 'الفئة B — آمن نسبيًا',
        'A' => 'الفئة A — آمن',
        'N/A' => 'غير متوفّر',
        'Unknown' => 'غير معروف',
    ],

    'ddi_condition' => [
        'Disease of liver' => 'أمراض الكبد',
        'Kidney disease' => 'أمراض الكلى',
        'Diabetes mellitus' => 'السكري',
        'Diabetes mellitus type 1' => 'السكري من النمط الأول',
        'Diabetes mellitus type 2' => 'السكري من النمط الثاني',
        'Hypertensive disorder' => 'ارتفاع ضغط الدم',
        'Asthma' => 'الربو',
        'Chronic heart failure' => 'قصور القلب المزمن',
        'Myocardial infarction' => 'احتشاء عضلة القلب (سابق)',
        'Disorder of coronary artery' => 'مرض الشريان التاجي',
        'Angina pectoris' => 'الذبحة الصدرية',
        'Conduction disorder of the heart' => 'اضطراب التوصيل القلبي',
        'Bradycardia' => 'بطء ضربات القلب',
        'Low blood pressure' => 'انخفاض ضغط الدم',
        'Cerebrovascular accident' => 'جلطة دماغية (سابقة)',
        'Prolonged QT interval' => 'إطالة فترة QT',
        'Hepatic failure' => 'قصور كبدي',
        'Impaired renal function disorder' => 'قصور وظائف الكلى',
        'Disorder of gallbladder' => 'أمراض المرارة',
        'Peptic ulcer' => 'قرحة المعدة',
        'Gastrointestinal ulcer' => 'قرحة الجهاز الهضمي',
        'Ulcerative colitis' => 'التهاب القولون التقرحي',
        'Acute pancreatitis' => 'التهاب البنكرياس',
        'Chronic idiopathic constipation' => 'إمساك مزمن',
        'Hyperthyroidism' => 'فرط نشاط الغدة الدرقية',
        'Hypothyroidism' => 'قصور الغدة الدرقية',
        'Hypercholesterolemia' => 'ارتفاع الكوليسترول',
        'Obesity' => 'السمنة',
        'Gout' => 'النقرس',
        'Anemia' => 'فقر الدم',
        'Blood coagulation disorder' => 'اضطراب تخثّر الدم',
        'Thrombocytopenic disorder' => 'نقص الصفيحات الدموية',
        'Epilepsy' => 'الصرع',
        'Seizure disorder' => 'اضطراب النوبات',
        'Depressive disorder' => 'الاكتئاب',
        'Psychotic disorder' => 'اضطراب ذهاني',
        'Myasthenia gravis' => 'الوهن العضلي الوبيل',
        'Glaucoma' => 'الجلوكوما (المياه الزرقاء)',
        'Angle-closure glaucoma' => 'جلوكوما مغلقة الزاوية',
        'Benign prostatic hyperplasia' => 'تضخم البروستاتا الحميد',
        'Retention of urine' => 'احتباس البول',
        'Systemic lupus erythematosus' => 'الذئبة الحمامية الجهازية',
        'Porphyria' => 'البورفيريا',
        'Decreased respiratory function' => 'قصور تنفسي',
        'Alcoholism' => 'إدمان الكحول',
        'Smokes tobacco daily' => 'التدخين اليومي',
    ],

    // الألقاب المستخدمة عند تكوين اسم العرض.
    'title' => [
        'doctor_prefix' => 'د.',
    ],

];
