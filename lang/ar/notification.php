<?php

return [

    /*
    |--------------------------------------------------------------------------
    | الإشعارات (داخل التطبيق، البريد، الرسائل النصية، واتساب)
    |--------------------------------------------------------------------------
    */

    'retrieved' => 'تم جلب الإشعارات بنجاح.',
    'unread_retrieved' => 'تم جلب الإشعارات غير المقروءة بنجاح.',
    'unread_count' => 'تم جلب عدد الإشعارات غير المقروءة بنجاح.',
    'marked_read' => 'تم تعليم الإشعار كمقروء',
    'all_marked_read' => 'تم تعليم جميع الإشعارات كمقروءة',
    'deleted' => 'تم حذف الإشعار',
    'not_found' => 'الإشعار غير موجود',

    /*
    | إشعارات الاستشارات
    */

    'consultation_booked' => 'تم حجز استشارة جديدة من قبل :name',
    'consultation_requested' => 'تم طلب استشارة جديدة من قبل :name',
    'consultation_requested_subject' => 'طلب استشارة جديدة',
    'consultation_requested_title' => 'طلب استشارة جديدة',

    'consultation_reminder_subject' => 'تذكير بموعد استشارة',
    'consultation_reminder_title' => 'تذكير بموعد استشارة',
    'reminder_mail_patient' => 'تذكير: لديك استشارة مع د. :name مجدولة في :time',
    'reminder_mail_doctor' => 'تذكير: لديك استشارة مع :name مجدولة في :time',
    'reminder_db_patient' => 'لديك استشارة مع د. :name مجدولة في :time',
    'reminder_db_doctor' => 'لديك استشارة مع :name مجدولة في :time',
    'reminder_be_ready' => 'يُرجى الاستعداد للاستشارة.',
    'reminder_thanks' => 'شكرًا لاستخدامك منصتنا!',
    'salutation' => 'فريق Healix',
    'time_now' => 'الآن',

    'sms_consultation_requested' => 'طلب استشارة جديدة من قبل :name.',
    'sms_scheduled_at' => ' الموعد: :time',
    'sms_call_type' => ' النوع: :type',
    'time_immediately' => 'فوراً',

    /*
    | واتساب/رسائل نصية لحجز استشارة (ConsultationService — ترسل للطبيب)
    */

    'wa_booked_message' => 'مرحباً :name، لديك استشارة جديدة محجوزة.',
    'wa_booked_short' => 'تم حجز استشارة جديدة مع :name بتاريخ :time',
    'meet_link_line' => 'رابط Google Meet: :link',
    'sms_booked_hello' => 'مرحباً :name',
    'sms_booked_intro' => 'لديك استشارة جديدة محجوزة.',
    'sms_booked_patient_name' => 'اسم المريض: :name',
    'sms_booked_type' => 'النوع: :type',
    'sms_booked_time' => 'الوقت: :time',

    /*
    | تذكير استشارة عبر رسائل نصية/واتساب (أمر SendConsultationReminders)
    */

    'reminder_sms_patient' => 'تذكير بموعد استشارة: لديك استشارة مجدولة مع د. :name في :time. يُرجى الاستعداد.',
    'reminder_sms_doctor' => 'تذكير بموعد استشارة: لديك استشارة مجدولة مع :name في :time. يُرجى الاستعداد.',

    /*
    | إشعارات التوصيل
    */

    'delivery_task_offer' => 'تتوفر مهمة توصيل جديدة بالقرب منك.',
    'delivery_task_assigned' => 'تم إسناد مهمة التوصيل إليك.',

    /*
    | إشعارات الوصفات
    */

    'prescription_accepted_title' => 'تم قبول وصفتك',
    'prescription_accepted_body' => 'قبلت الصيدلية وصفتك الطبية، يمكنك متابعة التفاصيل.',

    'prescription_rejected_title' => 'تم رفض الوصفة',
    'prescription_rejected_body' => 'رفضت الصيدلية وصفتك للسبب التالي: :reason',

    /*
    | إشعارات السجل الطبي
    */

    'medical_report_edit_title' => 'تم تعديل السجل الطبي',
    'medical_report_edit_body' => 'تم تعديل السجل الطبي من قبل الطبيب.',

    /*
    | إشعارات التوصيل
    */

    'delivery_driver_nearby_title' => 'المندوب اقترب منك',
    'delivery_driver_nearby_body' => 'مندوب التوصيل اقترب من موقعك.',

    /*
    | إشعارات السلامة الدوائية
    */

    'critical_warning_title' => 'تحذير: تفاعل دوائي حرج',
    'critical_warning_body' => 'تم حفظ وصفة تحتوي تحذيراً حرجاً — يُرجى المراجعة.',

    /*
    | بريد التحقق من الحساب
    */

    'verify_subject' => 'تأكيد البريد الإلكتروني',
    'verify_greeting' => 'مرحبًا بك في Healix!',
    'verify_hello' => 'مرحبًا :name،',
    'verify_body' => 'شكرًا لتسجيلك في Healix. لإكمال تسجيلك والبدء باستخدام المنصة، يُرجى تأكيد بريدك الإلكتروني بالضغط على الزر أدناه:',
    'verify_action' => 'تأكيد البريد الإلكتروني',
    'verify_fallback' => 'إذا لم يعمل الزر، يمكنك نسخ هذا الرابط ولصقه في متصفحك:',
    'verify_footer' => 'إذا لم تقم بإنشاء حساب، فلا حاجة لاتخاذ أي إجراء.',

    /*
    | بريد رمز إعادة تعيين كلمة المرور
    */

    'otp_subject' => 'رمز إعادة تعيين كلمة المرور في Healix',
    'otp_greeting' => 'رمز إعادة تعيين كلمة المرور',
    'otp_hello' => 'مرحبًا :name،',
    'otp_body' => 'تلقّينا طلبًا لإعادة تعيين كلمة المرور الخاصة بحسابك في Healix. أدخل الرمز أدناه في التطبيق للمتابعة.',
    'otp_expiry' => 'ستنتهي صلاحية هذا الرمز خلال :count دقيقة.',
    'otp_warning' => 'لا تشارك هذا الرمز مع أي شخص. لن يطلبه منك موظفو Healix أبدًا.',
    'otp_footer' => 'إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذا البريد بأمان وستبقى كلمة مرورك دون تغيير.',

    /*
    | بوابات المراسلة
    */

    'sms_failed' => 'فشل إرسال الرسالة النصية.',
    'whatsapp_not_configured' => 'خدمة UltraMsg غير مُعدّة',
    'whatsapp_invalid_phone' => 'صيغة رقم الهاتف غير صحيحة',
    'whatsapp_failed' => 'فشل إرسال الرسالة',
    'whatsapp_sent' => 'تم إرسال الرسالة بنجاح',

    /*
    | بريد تأكيد حجز الاستشارة (emails.notifications.consultation-booked)
    */

    'booked_mail_hello' => 'مرحبًا :name',
    'booked_mail_intro' => 'لديك استشارة جديدة محجوزة.',
    'booked_mail_patient' => 'اسم المريض: :name',
    'booked_mail_type' => 'نوع الاستشارة: :type',
    'booked_mail_scheduled' => 'تاريخ ووقت الموعد: :time',
    'booked_mail_outro' => 'تفقّد موقعنا لمزيد من التفاصيل',
    'booked_mail_thanks' => 'شكرًا لك،',

];
