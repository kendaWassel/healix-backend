<?php

return [

    /*
    |--------------------------------------------------------------------------
    | وحدة الوصفات الطبية والطلبات والصيدليات
    |--------------------------------------------------------------------------
    */

    /*
    | الوصفات الطبية
    */

    'prescription_created' => 'تم إنشاء الوصفة الطبية',
    'prescription_uploaded' => 'تم رفع الوصفة الطبية بنجاح',
    'prescription_sent' => 'تم إرسال الوصفة الطبية إلى الصيدلية',
    'prescription_accepted' => 'تم قبول الوصفة الطبية بنجاح',
    'prescription_rejected' => 'تم رفض الوصفة الطبية بنجاح',
    'prescription_not_found' => 'الوصفة الطبية غير موجودة.',
    'prescription_retrieved' => 'تم جلب الوصفة الطبية بنجاح.',
    'prescriptions_retrieved' => 'تم جلب الوصفات الطبية بنجاح.',

    'prescription_accept_failed' => 'فشل قبول الوصفة الطبية.',
    'prescription_reject_failed' => 'فشل رفض الوصفة الطبية.',
    'prescription_cannot_accept' => 'لا يمكن قبول الوصفة الطبية. الحالة الحالية: :status',
    'prescription_cannot_reject' => 'لا يمكن رفض الوصفة الطبية. الحالة الحالية: :status',
    'prescription_not_authorized_accept' => 'غير مصرح لك بقبول هذه الوصفة الطبية.',
    'prescription_not_authorized_reject' => 'غير مصرح لك برفض هذه الوصفة الطبية.',
    'prescription_not_authorized' => 'هذه الوصفة الطبية غير مصرح بها لهذا الصيدلاني.',

    'no_prescriptions' => 'لا توجد وصفات طبية.',
    'no_prescriptions_for_patient' => 'لا توجد وصفات طبية لهذا المريض.',
    'no_prescriptions_with_pricing' => 'لا توجد وصفات طبية مُسعّرة.',

    /*
    | التسعير
    */

    'prices_added' => 'تمت إضافة الأسعار بنجاح',
    'prices_add_failed' => 'فشلت إضافة الأسعار.',
    'already_priced' => 'تم تسعير الوصفة الطبية مسبقًا.',
    'must_accept_before_pricing' => 'يجب قبول الوصفة الطبية قبل إضافة الأسعار.',

    /*
    | الطلبات
    */

    'order_not_found' => 'الطلب غير موجود',
    'order_not_accessible' => 'الطلب غير موجود أو لا يمكن الوصول إليه.',
    'order_not_available' => 'الطلب غير متاح',
    'order_ready' => 'تم تعليم الطلب كجاهز للتوصيل',
    'order_ready_failed' => 'فشل تعليم الطلب كجاهز للتوصيل',
    'order_must_be_accepted' => 'يجب قبول الطلب قبل تعليمه كجاهز للتوصيل',
    'orders_retrieved' => 'تم جلب الطلبات بنجاح.',

    /*
    | الصيدليات
    */

    'pharmacies_retrieved' => 'تم جلب الصيدليات بنجاح.',
    'pharmacy_retrieved' => 'تم جلب تفاصيل الصيدلية بنجاح.',
    'pharmacy_closed' => 'الصيدلية المحددة مغلقة حاليًا، يُرجى اختيار صيدلية أخرى.',
    'no_order_for_prescription' => 'لا يوجد طلب مرتبط بهذه الوصفة الطبية',

    /*
    | قواعد الوصفات من جانب الطبيب (DoctorService)
    */

    'only_doctors_create_prescriptions' => 'غير مصرح — يمكن للأطباء فقط إنشاء الوصفات الطبية.',
    'only_completed_consultations' => 'يمكن إصدار وصفات طبية للاستشارات المكتملة فقط.',

];
