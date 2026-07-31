<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق الخاصة بكل طلب
    |--------------------------------------------------------------------------
    |
    | كل FormRequest يحتفظ بصياغته الخاصة، لذلك تم تصنيف المفاتيح حسب الطلب
    | بدلًا من وضعها في validation.custom، لأن عدة طلبات تستخدم صياغات مختلفة
    | لنفس الحقل والقاعدة (مثلًا password.min تساوي 6 عند الدخول و 8 عند التسجيل).
    |
    */

    'login' => [
        'email_required' => 'البريد الإلكتروني مطلوب',
        'email_email' => 'يُرجى إدخال بريد إلكتروني صحيح',
        'email_max' => 'يجب ألا يتجاوز البريد الإلكتروني 255 حرفًا',
        'password_required' => 'كلمة المرور مطلوبة',
        'password_min' => 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل',
    ],

    'register' => [
        'email_unique' => 'البريد الإلكتروني مستخدم بالفعل',
        'phone_unique' => 'رقم الهاتف مستخدم بالفعل',
        'password_min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل',
        'role_in' => 'الدور المحدد غير صالح',
        'gender_in' => 'يجب أن يكون الجنس ذكرًا أو أنثى',
        'type_in' => 'يجب أن يكون نوع مقدم الرعاية ممرضًا أو أخصائي علاج طبيعي',
        'is_pregnant_in' => 'يجب أن تكون إجابة الحمل بنعم أو لا.',
    ],

    'forgot_password' => [
        'email_required' => 'البريد الإلكتروني مطلوب',
        'email_email' => 'يُرجى إدخال بريد إلكتروني صحيح',
        'email_max' => 'يجب ألا يتجاوز البريد الإلكتروني 255 حرفًا',
    ],

    'verify_reset_otp' => [
        'email_required' => 'البريد الإلكتروني مطلوب',
        'email_email' => 'يُرجى إدخال بريد إلكتروني صحيح',
        'otp_required' => 'رمز التحقق مطلوب.',
        'otp_digits' => 'يجب أن يتكون رمز التحقق من :digits أرقام.',
    ],

    'reset_password' => [
        'token_required' => 'رمز إعادة التعيين مطلوب.',
        'email_required' => 'البريد الإلكتروني مطلوب',
        'email_email' => 'يُرجى إدخال بريد إلكتروني صحيح',
        'password_required' => 'كلمة المرور مطلوبة',
        'password_min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل',
        'password_confirmed' => 'تأكيد كلمة المرور غير متطابق.',
    ],

    'book_consultation' => [
        'doctor_id_required' => 'يجب تحديد طبيب لحجز الاستشارة.',
        'doctor_id_exists' => 'الطبيب المحدد غير موجود.',
        'call_type_required' => 'نوع المكالمة مطلوب.',
        'call_type_in' => 'يجب أن يكون نوع المكالمة call_now أو schedule.',
        'scheduled_at_date' => 'يجب أن يكون الموعد المحدد بصيغة تاريخ ووقت صحيحة.',
    ],

    'create_prescription' => [
        'medicines_required' => 'يجب إدخال دواء واحد على الأقل.',
        'medicines_array' => 'يجب أن تكون الأدوية على شكل قائمة.',
    ],

    'delivery_location' => [
        'task_id_required' => 'معرّف المهمة مطلوب.',
        'task_id_integer' => 'يجب أن يكون معرّف المهمة رقمًا صحيحًا.',
        'task_id_exists' => 'مهمة التوصيل المحددة غير موجودة.',
        'latitude_required' => 'خط العرض مطلوب.',
        'latitude_numeric' => 'يجب أن يكون خط العرض قيمة رقمية.',
        'latitude_between' => 'يجب أن يكون خط العرض بين -90 و 90.',
        'longitude_required' => 'خط الطول مطلوب.',
        'longitude_numeric' => 'يجب أن يكون خط الطول قيمة رقمية.',
        'longitude_between' => 'يجب أن يكون خط الطول بين -180 و 180.',
    ],

    'rate' => [
        'stars_required' => 'عدد نجوم التقييم مطلوب.',
        'stars_integer' => 'يجب أن يكون عدد النجوم رقمًا صحيحًا.',
        'stars_min' => 'يجب أن يكون عدد النجوم 1 على الأقل.',
        'stars_max' => 'يجب ألا يزيد عدد النجوم عن 5.',
    ],

    'conversation' => [
        'title_required' => 'عنوان المحادثة مطلوب.',
        'title_string' => 'يجب أن يكون عنوان المحادثة نصًا.',
        'title_max' => 'يجب ألا يتجاوز عنوان المحادثة 255 حرفًا.',
    ],

    'message' => [
        'message_required' => 'محتوى الرسالة مطلوب.',
        'message_string' => 'يجب أن تكون الرسالة نصًا.',
        'message_max' => 'يجب ألا تتجاوز الرسالة 5000 حرف.',
    ],

    'speech' => [
        'conversation_id_required' => 'معرّف المحادثة مطلوب.',
        'conversation_id_exists' => 'المحادثة المحددة غير موجودة.',
        'audio_required' => 'الملف الصوتي مطلوب.',
        'audio_file' => 'يجب أن يكون العنصر المرفوع ملفًا صوتيًا صالحًا.',
        'audio_max' => 'يجب ألا يتجاوز حجم الملف الصوتي 10 ميغابايت.',
        'audio_mimes' => 'يجب أن يكون الملف الصوتي بأحد الصيغ: m4a، mp3، wav، ogg، webm.',
        'audio_mimetypes' => 'يجب أن يكون الملف الصوتي بصيغة مدعومة.',
    ],

    'medical_record' => [
        'diagnosis_string' => 'يجب أن يكون التشخيص نصًا.',
        'treatment_plan_string' => 'يجب أن تكون الخطة العلاجية نصًا.',
        'current_medications_string' => 'يجب أن تكون الأدوية الحالية نصًا.',
        'chronic_diseases_string' => 'يجب أن تكون الأمراض المزمنة نصًا.',
        'previous_surgeries_string' => 'يجب أن تكون العمليات الجراحية السابقة نصًا.',
        'allergies_string' => 'يجب أن تكون الحساسية نصًا.',
        'attachments_array' => 'يجب أن تكون المرفقات على شكل قائمة.',
        'attachments_integer' => 'يجب أن يكون كل معرّف مرفق رقمًا صحيحًا.',
        'attachments_exists' => 'يجب أن يكون كل مرفق موجودًا.',
    ],

    'pregnancy_info' => [
        'is_pregnant_required' => 'يُرجى تحديد ما إذا كانت المريضة حاملًا حاليًا.',
    ],

    'upload' => [
        'file_required' => 'الملف مطلوب.',
        'file_file' => 'يجب أن يكون العنصر المرفوع ملفًا.',
        'file_max' => 'يجب ألا يتجاوز حجم الملف 10 ميغابايت.',
        'image_required' => 'الصورة مطلوبة.',
        'image_image' => 'يجب أن يكون الملف صورة.',
        'image_mimes' => 'يجب أن تكون الصورة بصيغة jpeg أو png أو jpg أو gif.',
        'image_max' => 'يجب ألا يتجاوز حجم الصورة 5 ميغابايت.',
        'category_required' => 'الفئة مطلوبة.',
        'category_string' => 'يجب أن تكون الفئة نصًا.',
        'category_in' => 'يجب أن تكون الفئة إحدى القيم: certificate، report، document، prescription، profile.',
    ],

    'ddi_allergy' => [
        'drug_required' => 'اسم الدواء المسبب للحساسية مطلوب.',
    ],

    'ddi_batch' => [
        'pairs_required' => 'يجب إدخال زوج دواء واحد على الأقل.',
        'pairs_max' => 'يمكن فحص 50 زوج دواء كحد أقصى في الطلب الواحد.',
        'drug_a_required' => 'يجب أن يتضمن كل زوج اسم الدواء الأول.',
        'drug_b_required' => 'يجب أن يتضمن كل زوج اسم الدواء الثاني.',
    ],

    'ddi_interaction' => [
        'drug_a_required' => 'اسم الدواء الأول مطلوب.',
        'drug_b_required' => 'اسم الدواء الثاني مطلوب.',
        'drug_b_different' => 'يُرجى إدخال اسمي دوائين مختلفين.',
    ],

    'ddi_pregnancy' => [
        'drug_a_required' => 'اسم الدواء مطلوب.',
    ],

    'ddi_resolve' => [
        'name_required' => 'اسم الدواء مطلوب.',
    ],

    'ddi_screen' => [
        'drugs_required' => 'قائمة أسماء الأدوية مطلوبة.',
        'drugs_min' => 'يلزم وجود دوائين على الأقل لفحص التداخلات.',
        'drugs_max' => 'يمكن فحص 20 دواءً كحد أقصى في الطلب الواحد.',
        'drugs_distinct' => 'قائمة الأدوية تحتوي على أسماء مكررة.',
    ],

    'verify_draft_prescription' => [
        'patient_id_required' => 'يجب تحديد مريض.',
        'patient_id_exists' => 'المريض المحدد غير موجود.',
        'medications_required' => 'يُرجى إدخال الأدوية المراد التحقق منها.',
        'medications_min' => 'يلزم إدخال دواء واحد على الأقل.',
        'medications_max' => 'يمكن التحقق من 50 دواءً كحد أقصى في المرة الواحدة.',
        'medication_required' => 'لا يمكن ترك اسم الدواء فارغًا.',
        'medication_distinct' => 'قائمة الأدوية تحتوي على تكرارات.',
    ],

    'verify_prescription' => [
        'medications_required' => 'يُرجى إدخال الأدوية المراد التحقق منها وصرفها.',
        'medications_min' => 'يلزم إدخال دواء واحد على الأقل.',
        'medications_max' => 'يمكن إدخال 50 دواءً كحد أقصى في المرة الواحدة.',
        'medication_required' => 'لا يمكن ترك اسم الدواء فارغًا.',
        'medication_distinct' => 'قائمة الأدوية تحتوي على تكرارات.',
    ],

    'lab_analyze' => [
        'file_required' => 'ملف تحليل المختبر مطلوب.',
        'file_mimes' => 'نوع الملف غير مدعوم. الصيغ المدعومة: CSV، Excel (.xlsx، .xls)، PDF.',
        'file_max' => 'يجب ألا يتجاوز حجم ملف التحليل 10 ميغابايت.',
        'gender_in' => 'يجب أن يكون الجنس ذكرًا أو أنثى أو آخر.',
    ],

];
