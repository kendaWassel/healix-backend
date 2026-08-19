<?php

return [

    /*
    |--------------------------------------------------------------------------
    | وحدة الإدارة
    |--------------------------------------------------------------------------
    */

    'dashboard_retrieved' => 'تم جلب بيانات لوحة التحكم بنجاح.',
    'services_retrieved' => 'تم جلب الخدمات بنجاح.',
    'users_retrieved' => 'تم جلب المستخدمين بنجاح.',
    'attachments_retrieved' => 'تم جلب المرفقات بنجاح.',

    'user_created' => 'تم إنشاء الحساب بنجاح',
    'user_updated' => 'تم تحديث الحساب بنجاح',
    'user_deleted' => 'تم حذف الحساب بنجاح',
    'user_approved' => 'تمت الموافقة على الحساب وتفعيله',
    'user_rejected' => 'تم رفض الحساب بنجاح',
    'user_already_approved' => 'تمت الموافقة على الحساب مسبقًا',

    'only_active_can_be_edited' => 'يمكن تعديل الحسابات النشطة فقط.',
    'only_active_can_be_deleted' => 'يمكن حذف الحسابات النشطة فقط.',

    'not_available' => 'غير متوفر',

    /*
    | أسماء الخدمات المعروضة بقائمة الخدمات/التقييمات لدى الأدمن (services())
    */
    'service_names' => [
        'consultation' => 'استشارة',
        'home_visit_nurse' => 'زيارة منزلية - تمريض',
        'home_visit_physiotherapist' => 'زيارة منزلية - علاج فيزيائي',
        'medication_delivery' => 'توصيل أدوية',
    ],

    /*
    | تسميات العرض لأقسام إحصائيات لوحة التحكم dashboard() — الفرونت كان
    | يعرض مفاتيح الـ JSON الخام (متل "patients"، "delivery_agents") كنص
    | مباشر بدون قاموس ترجمة عنده. مضافة إضافياً كـ response.labels،
    | بنفس شكل response.data — مفاتيح/أرقام data نفسها ما تغيّرت.
    */
    'dashboard_labels' => [
        'users' => 'المستخدمون',
        'users_patients' => 'المرضى',
        'users_doctors' => 'الأطباء',
        'users_pharmacists' => 'الصيادلة',
        'users_nurse' => 'الممرضون',
        'users_physiotherapist' => 'أخصائيو العلاج الطبيعي',
        'users_delivery_agents' => 'مندوبو التوصيل',

        'consultations' => 'الاستشارات',
        'consultations_total' => 'الإجمالي',
        'consultations_completed' => 'مكتملة',
        'consultations_cancelled' => 'ملغاة',

        'orders' => 'الطلبات',
        'orders_total' => 'الإجمالي',
        'orders_delivered' => 'تم التوصيل',
        'orders_pending' => 'قيد الانتظار',

        'revenue' => 'الإيرادات',
        'revenue_total' => 'إجمالي الإيرادات',

        'pending_documents' => 'المستندات المعلّقة',

        'top_providers' => 'أفضل مقدمي الخدمة',
        'top_providers_total_consultations' => 'إجمالي الاستشارات',
    ],

];
