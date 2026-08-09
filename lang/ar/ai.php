<?php

return [

    /*
    |--------------------------------------------------------------------------
    | وحدة الذكاء الاصطناعي (المحادثة، الصوت، تداخلات الأدوية، المختبر، المقابلة)
    |--------------------------------------------------------------------------
    |
    | مهم: هذه الرسائل هي رسائل الواجهة الموجهة للمستخدم فقط. أما المحتوى
    | السريري القادم من خدمات Python — أسماء الأدوية والأسماء العلمية
    | ومعرّفات الأدوية والرموز الطبية ومتن التقارير — فيمر كما هو دون ترجمة.
    |
    */

    /*
    | أخطاء خدمات الذكاء الاصطناعي المشتركة
    */

    'service_failed' => 'فشل طلب خدمة الذكاء الاصطناعي.',
    'service_unavailable' => 'خدمة الذكاء الاصطناعي غير متاحة.',
    'service_timeout' => 'انتهت مهلة طلب خدمة الذكاء الاصطناعي.',
    'service_invalid_response' => 'أعادت خدمة الذكاء الاصطناعي استجابة غير صالحة.',
    'service_connection_failed' => 'تعذّر الاتصال بـ :service.',
    'service_request_failed' => 'فشل طلب :service بالحالة :status.',
    'service_unavailable_named' => ':service غير متاحة بعد عدة محاولات.',
    'service_invalid_json' => 'استجابة :service ليست بصيغة JSON صالحة.',
    'service_download_failed' => 'فشل تنزيل ملف :service بالحالة :status.',

    // المفاتيح مطابقة لـ $serviceLabelKey في كل صنف يرث FastApiClient.
    'service_label_medical_assistant' => 'خدمة المساعد الطبي',
    'service_label_ddi' => 'خدمة تداخلات الأدوية',
    'service_label_lab' => 'خدمة تحليل المختبر',
    'service_label_clinical_guidance' => 'خدمة الإرشاد الطبي',

    /*
    | المحادثات
    */

    'chat_started' => 'تم بدء جلسة المحادثة بنجاح.',
    'chat_response' => 'تم توليد رد الذكاء الاصطناعي بنجاح.',
    'guidance_greeting' => 'مرحباً — أساعدك على فهم أعراضك ومعرفة متى تطلب الرعاية. هذا إرشاد عام وليس تشخيصاً.',
    'conversation_created' => 'تم إنشاء المحادثة بنجاح.',
    'conversation_deleted' => 'تم حذف المحادثة بنجاح.',
    'conversation_retrieved' => 'تم جلب المحادثة بنجاح.',
    'conversations_retrieved' => 'تم جلب المحادثات بنجاح.',
    'message_sent' => 'تم إرسال الرسالة بنجاح.',
    'messages_retrieved' => 'تم جلب الرسائل بنجاح.',
    'conversation_not_authorized' => 'غير مصرح لك بإرسال رسائل في هذه المحادثة.',
    'conversation_not_found' => 'المحادثة غير موجودة.',

    /*
    | تحويل الكلام إلى نص
    */

    'speech_converted' => 'تم تحويل الكلام بنجاح.',
    'speech_failed' => 'فشل تحويل الكلام إلى نص.',
    'speech_no_text' => 'لم تُعِد خدمة الذكاء الاصطناعي أي نص مُفرَّغ.',
    'speech_storage_failed' => 'فشل تخزين الملف الصوتي.',
    'speech_unexpected_error' => 'حدث خطأ غير متوقع أثناء معالجة الصوت.',

    /*
    | استخراج الأعراض
    */

    'symptoms_failed' => 'فشل استخراج الأعراض.',
    'symptoms_missing' => 'لم تُعِد خدمة الذكاء الاصطناعي أي أعراض مكتشفة.',

    /*
    | تداخلات الأدوية
    */

    'ddi_interaction_completed' => 'اكتمل فحص تداخل الأدوية.',
    'ddi_batch_completed' => 'اكتمل فحص تداخلات الأدوية المجمّع.',
    'ddi_screen_completed' => 'تم فحص قائمة الأدوية بنجاح.',
    'ddi_allergy_completed' => 'اكتمل فحص التفاعل التحسسي المتقاطع.',
    'ddi_pregnancy_completed' => 'اكتمل فحص سلامة الدواء أثناء الحمل.',
    'ddi_unexpected_error' => 'حدث خطأ غير متوقع أثناء فحص سلامة الأدوية.',
    'ddi_no_prediction' => 'لم تُعِد خدمة تداخلات الأدوية أي تنبؤ بالتداخل.',
    'ddi_no_batch_results' => 'لم تُعِد خدمة تداخلات الأدوية نتائج مجمّعة.',
    'ddi_no_findings' => 'لم تُعِد خدمة تداخلات الأدوية أي نتائج فحص.',
    'ddi_check_retrieved' => 'تم جلب فحص تداخل الأدوية بنجاح.',
    'ddi_checks_retrieved' => 'تم جلب فحوصات تداخل الأدوية بنجاح.',
    'ddi_check_not_found' => 'فحص تداخل الأدوية غير موجود.',
    'ddi_no_resolution' => 'لم تُعِد خدمة تداخلات الأدوية نتيجة مطابقة للدواء.',
    'ddi_no_condition_warnings' => 'لم تُعِد خدمة تداخلات الأدوية تنبيهات الحالات المزمنة.',

    /*
    | التحقق من سلامة الوصفة الطبية
    */

    'verification_completed' => 'اكتمل التحقق من الوصفة الطبية.',
    'safety_verification_completed' => 'اكتمل التحقق من سلامة الوصفة الطبية.',
    'verification_unexpected_error' => 'حدث خطأ غير متوقع أثناء التحقق من السلامة.',

    // ملاحظات ثابتة يولّدها تقرير السلامة (تُترجَم حسب لغة الطلب).
    'safety_allergy_direct_note' => 'المريض لديه حساسية مباشرة من هذا الدواء.',
    'safety_allergy_direct_match_note' => 'يحتوي على :match، وهي نفس المادة الفعّالة الموجودة في حساسية المريض المسجَّلة.',
    'safety_allergy_cross_note' => 'قد يتفاعل بشكل متصالب مع حساسية المريض تجاه :allergen.',
    'safety_interaction_alternative_note' => 'نفس تصنيف ATC الخاص بـ :reference_drug، ولا يوجد تداخل مسجّل مع :other_drug. يجب التأكد دائمًا من الصيدلي.',
    'safety_condition_contraindicated_note' => ':medication (:ingredient) ممنوع استخدامه لدى المرضى المصابين بـ :condition.',
    'safety_pregnancy_not_applicable' => 'المريضة غير مسجَّلة كحامل — تم تخطّي فحص الحمل.',

    /*
    | تحاليل المختبر
    */

    'lab_analyzed' => 'تم تحليل تقرير المختبر بنجاح.',
    'lab_analyses_retrieved' => 'تم جلب تحاليل المختبر بنجاح.',
    'lab_analysis_retrieved' => 'تم جلب تحليل المختبر بنجاح.',
    'lab_analysis_not_found' => 'تحليل المختبر غير موجود.',
    'lab_patients_only' => 'يمكن للمرضى فقط الوصول إلى تحاليل المختبر.',
    'lab_pdf_unavailable' => 'تقرير PDF غير متاح لهذا التحليل.',
    'lab_unexpected_error' => 'حدث خطأ غير متوقع أثناء تحليل المختبر.',
    'lab_no_report_id' => 'لم تُعِد خدمة تحليل المختبر معرّف تقرير.',
    'lab_service_reachable' => 'خدمة تحليل المختبر متاحة.',
    'lab_supported_tests' => 'تم جلب التحاليل المدعومة بنجاح.',
    'lab_reference_ranges' => 'تم جلب النطاقات المرجعية بنجاح.',

    /*
    | المقابلة الطبية (أخذ التاريخ المرضي)
    */

    'interview_invalid_finished' => 'لم تُعِد خدمة المقابلة قيمة صالحة لعلامة "الانتهاء".',
    'interview_no_session' => 'لم تُعِد خدمة المقابلة معرّف جلسة.',
    'interview_no_patient' => 'لا يوجد مريض في قاعدة البيانات لربط المحادثة التجريبية به.',

    /*
    | محرك التقييم (بعد انتهاء المقابلة)
    */

    'assessment_invalid_response' => 'لم تُعِد خدمة التقييم استجابة صالحة.',
    'assessment_unavailable_notice' => 'انتهت المقابلة وتمّ تسجيل إجاباتك، لكن تعذّر إتمام التقييم النهائي حالياً. يرجى مراجعة طبيب لتقييم الأعراض.',
    'assessment_summary_title' => 'ملخّص التقييم الأولي',
    'assessment_urgency_line' => 'درجة الاستعجال: :level',
    'assessment_specialty_line' => 'التخصّص المقترح: :specialty',

    'urgency_level_emergency' => 'طارئة',
    'urgency_level_urgent' => 'عاجلة',
    'urgency_level_semi_urgent' => 'شبه عاجلة',
    'urgency_level_non_urgent' => 'غير عاجلة',

];
