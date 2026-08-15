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

    // تحذيرات حمل منسّقة يدويًا لعدد محدود من المواد الفعّالة الشائعة
    // (المصدر: قاعدة بيانات الحمل السريرية بخدمة تداخلات الأدوية). أي مادة
    // فعّالة غير مذكورة هنا يبقى نصّها الأصلي (إنكليزي) كما ورد من الخدمة —
    // لا تُخترع ترجمة لنص طبي حر لم يُراجَع.
    'ddi_pregnancy_warning' => [
        'acetaminophen' => 'آمن بشكل عام بالجرعات الموصى بها',
        'alprazolam' => 'تجنّب — خطر انسحاب عند الوليد',
        'amlodipine' => 'استخدم بحذر — بيانات بشرية محدودة',
        'amoxicillin' => 'آمن بشكل عام أثناء الحمل — البنسلينات تُعتبر منخفضة الخطورة',
        'atenolol' => 'تجنّب إن أمكن — مرتبط بتقييد نمو الجنين',
        'atorvastatin' => 'الستاتينات ممنوعة أثناء الحمل',
        'azithromycin' => 'آمن بشكل عام أثناء الحمل',
        'betamethasone' => 'يُستخدم لتسريع نضج رئة الجنين — مقبول',
        'bisoprolol' => 'استخدم بحذر — حاصرات بيتا قد تسبب بطء قلب الجنين',
        'caffeine' => 'قلّل الكمية أثناء الحمل — الجرعات العالية مرتبطة بتقييد نمو الجنين',
        'captopril' => 'مثبطات ACE — ممنوعة بالثلث الثاني والثالث من الحمل',
        'carbamazepine' => 'خطر تشوّهات — عيوب الأنبوب العصبي',
        'cefixime' => 'السيفالوسبورينات آمنة بشكل عام أثناء الحمل',
        'cephalexin' => 'السيفالوسبورينات آمنة بشكل عام أثناء الحمل',
        'cetirizine' => 'آمن بشكل عام أثناء الحمل',
        'cholecalciferol' => 'آمن بالجرعات الموصى بها — مهم أثناء الحمل',
        'ciprofloxacin' => 'استخدم بحذر — يُتجنّب إذا توفّرت بدائل',
        'clarithromycin' => 'استخدم بحذر — دراسات حيوانية تُظهر خطرًا، وبيانات بشرية محدودة',
        'clindamycin' => 'آمن بشكل عام أثناء الحمل',
        'clopidogrel' => 'بيانات محدودة — يُستخدم فقط عند الضرورة الواضحة',
        'codeine' => 'استخدم بحذر — يُتجنّب قرب موعد الولادة، خطر انسحاب عند الوليد',
        'dexamethasone' => 'استخدم بحذر — يُستخدم لنضج رئة الجنين',
        'diazepam' => 'تجنّب — خطر انسحاب عند الوليد وارتخاء الرضيع',
        'doxycycline' => 'تجنّب — قد يؤثر على تكوّن عظام وأسنان الجنين',
        'doxylamine' => 'آمن — يُستخدم تحديدًا لغثيان الحمل',
        'enalapril' => 'مثبطات ACE — ممنوعة بالثلث الثاني والثالث من الحمل',
        'enoxaparin' => 'هيبارين منخفض الوزن الجزيئي — آمن أثناء الحمل',
        'esomeprazole' => 'استخدم بحذر — بيانات بشرية محدودة',
        'fexofenadine' => 'استخدم بحذر — بيانات بشرية محدودة',
        'fluconazole' => 'الجرعة الواحدة قد تكون مقبولة، ويُتجنّب الاستخدام المطوّل',
        'fluoxetine' => 'استخدم بحذر — خطر متلازمة التأقلم عند الوليد',
        'folic acid' => 'ضروري أثناء الحمل — يمنع عيوب الأنبوب العصبي',
        'furosemide' => 'استخدم بحذر — قد يقلّل تدفق الدم إلى المشيمة',
        'heparin' => 'آمن أثناء الحمل — لا يعبر المشيمة، ويُفضَّل على الوارفارين',
        'hydralazine' => 'يُستخدم لارتفاع ضغط الدم الشديد أثناء الحمل',
        'hydrocortisone' => 'استخدم بحذر — الاستخدام قصير المدى مقبول بشكل عام',
        'insulin glargine' => 'استخدم بحذر — نظائر الإنسولين لها بيانات حمل محدودة',
        'insulin lispro' => 'آمن بشكل عام — مستخدم على نطاق واسع أثناء الحمل',
        'irbesartan' => 'حاصرات مستقبلات الأنجيوتنسين — ممنوعة بالثلث الثاني والثالث',
        'isotretinoin' => 'ممنوع — مشوّه شديد للجنين',
        'labetalol' => 'يُستخدم لارتفاع ضغط الدم أثناء الحمل — مقبول بشكل عام',
        'lactulose' => 'آمن بشكل عام أثناء الحمل',
        'levofloxacin' => 'استخدم بحذر — يُتجنّب إذا توفّرت بدائل',
        'levothyroxine' => 'آمن — ضروري لوظيفة الغدة الدرقية للأم والجنين',
        'lisinopril' => 'مثبطات ACE — ممنوعة بالثلث الثاني والثالث، وخطر تلف كلى الجنين',
        'loperamide' => 'آمن بشكل عام — امتصاص محدود',
        'loratadine' => 'آمن بشكل عام — مضاد الهيستامين المفضّل أثناء الحمل',
        'losartan' => 'حاصرات مستقبلات الأنجيوتنسين — ممنوعة بالثلث الثاني والثالث، وخطر تلف كلى الجنين',
        'metformin' => 'يُعتبر آمنًا بشكل عام — لا دليل واضح على ضرر للجنين',
        'methimazole' => 'يُتجنّب بالثلث الأول — خطر تشوّهات الجنين',
        'methotrexate' => 'ممنوع — يسبب وفاة الجنين وتشوّهات',
        'methyldopa' => 'الدواء المفضّل لارتفاع ضغط الدم أثناء الحمل — أمان مثبَت جيدًا',
        'metoclopramide' => 'آمن بشكل عام لغثيان الحمل',
        'metoprolol' => 'استخدم بحذر — حاصرات بيتا قد تسبب بطء قلب الوليد',
        'metronidazole' => 'آمن بشكل عام — يُتجنّب بالثلث الأول إن أمكن',
        'misoprostol' => 'ممنوع — يسبب تقلّصات الرحم والإجهاض',
        'morphine' => 'استخدم بحذر — يُتجنّب قرب موعد الولادة، خطر انسحاب عند الوليد',
        'nifedipine' => 'يُستخدم بكثرة أثناء الحمل لارتفاع ضغط الدم — يُعتبر آمنًا بشكل عام',
        'nitrofurantoin' => 'آمن بشكل عام — يُتجنّب قرب الولادة (38 أسبوعًا فأكثر)',
        'omeprazole' => 'استخدم بحذر — بيانات بشرية محدودة',
        'ondansetron' => 'يُستخدم بكثرة لغثيان الحمل — يُعتبر آمنًا بشكل عام',
        'prednisolone' => 'استخدم بحذر — الاستخدام قصير المدى مقبول بشكل عام',
        'promethazine' => 'استخدم بحذر — يُستخدم بكثرة لغثيان الحمل',
        'propranolol' => 'استخدم بحذر — قد يسبب بطء قلب الوليد',
        'propylthiouracil' => 'استخدم بحذر — يُفضَّل على الميثيمازول بالثلث الأول',
        'ramipril' => 'مثبطات ACE — ممنوعة بالثلث الثاني والثالث من الحمل',
        'ranitidine' => 'آمن بشكل عام أثناء الحمل',
        'rosuvastatin' => 'الستاتينات ممنوعة أثناء الحمل',
        'sertraline' => 'استخدم بحذر — خطر متلازمة التأقلم عند الوليد',
        'simvastatin' => 'الستاتينات ممنوعة أثناء الحمل',
        'sitagliptin' => 'بيانات محدودة — الإنسولين مفضَّل أثناء الحمل',
        'spironolactone' => 'تجنّب — تأثيرات مضادة للأندروجين على الجنين',
        'thalidomide' => 'ممنوع — مشوّه شديد للجنين',
        'tramadol' => 'استخدم بحذر — يُتجنّب قرب موعد الولادة، خطر انسحاب عند الوليد',
        'trimethoprim' => 'استخدم بحذر — يُتجنّب بالثلث الأول',
        'valsartan' => 'حاصرات مستقبلات الأنجيوتنسين — ممنوعة بالثلث الثاني والثالث',
        'warfarin' => 'ممنوع أثناء الحمل — خطر نزيف وتشوّهات الجنين',
    ],

    'ddi_condition' => [
        'Disease of liver' => 'أمراض الكبد',
        'Kidney disease' => 'أمراض الكلى',
        'Diabetes mellitus' => 'السكري',
        'Diabetes mellitus type 1' => 'السكري من النمط الأول',
        'Diabetes mellitus type 2' => 'السكري من النمط الثاني',
        'Gestational diabetes mellitus' => 'سكري الحمل',
        'Ketoacidosis in diabetes mellitus' => 'الحماض الكيتوني السكري',
        'Severe Diabetes Mellitus' => 'السكري الشديد',
        'Hypertensive disorder' => 'ارتفاع ضغط الدم',
        'Asthma' => 'الربو',
        'Acute exacerbation of asthma' => 'نوبة ربو حادة',
        'Eosinophilic asthma' => 'الربو اليوزيني',
        'Exacerbation of asthma' => 'تفاقم الربو',
        'Refractory Extrinsic Asthma' => 'الربو التحسسي المقاوم للعلاج',
        'Chronic heart failure' => 'قصور القلب المزمن',
        'Chronic Heart Failure Following Myocardial Infarction' => 'قصور القلب المزمن بعد احتشاء عضلة القلب',
        'Decompensated chronic heart failure' => 'قصور القلب المزمن اللا معاوض',
        'Heart failure' => 'قصور القلب',
        'Worsening of Chronic Heart Failure' => 'تدهور قصور القلب المزمن',
        'Myocardial infarction' => 'احتشاء عضلة القلب (سابق)',
        'Myocardial infarction in recovery phase' => 'احتشاء عضلة القلب (مرحلة التعافي)',
        'Non-Q wave myocardial infarction' => 'احتشاء عضلة القلب بدون موجة Q',
        'Disorder of coronary artery' => 'مرض الشريان التاجي',
        'Angina pectoris' => 'الذبحة الصدرية',
        'Progressive Angina Pectoris' => 'الذبحة الصدرية المتفاقمة',
        'Conduction disorder of the heart' => 'اضطراب التوصيل القلبي',
        'Bradycardia' => 'بطء ضربات القلب',
        'Sinus bradycardia' => 'بطء القلب الجيبي',
        'Syncope due to Bradycardia' => 'إغماء بسبب بطء ضربات القلب',
        'Vagal Reflex Bradycardia' => 'بطء القلب المنعكس المبهمي',
        'Low blood pressure' => 'انخفاض ضغط الدم',
        'Cerebrovascular accident' => 'جلطة دماغية (سابقة)',
        'Prolonged QT interval' => 'إطالة فترة QT',
        'Hepatic failure' => 'قصور كبدي',
        'Acute hepatic failure' => 'قصور كبدي حاد',
        'Impaired renal function disorder' => 'قصور وظائف الكلى',
        'Disorder of gallbladder' => 'أمراض المرارة',
        'Peptic ulcer' => 'قرحة المعدة',
        'Acute peptic ulcer' => 'قرحة معدية حادة',
        'Acute peptic ulcer with hemorrhage' => 'قرحة معدية حادة مع نزيف',
        'Chronic peptic ulcer' => 'قرحة معدية مزمنة',
        'Gastrointestinal ulcer' => 'قرحة الجهاز الهضمي',
        'Ulcerative colitis' => 'التهاب القولون التقرحي',
        'Colitis' => 'التهاب القولون',
        'Severe chronic ulcerative colitis' => 'التهاب القولون التقرحي المزمن الشديد',
        'Acute pancreatitis' => 'التهاب البنكرياس',
        'Pancreatitis' => 'التهاب البنكرياس',
        'Chronic idiopathic constipation' => 'إمساك مزمن',
        'Constipation' => 'الإمساك',
        'Hyperthyroidism' => 'فرط نشاط الغدة الدرقية',
        'Hypothyroidism' => 'قصور الغدة الدرقية',
        'Hypercholesterolemia' => 'ارتفاع الكوليسترول',
        'Familial hypercholesterolemia - homozygous' => 'ارتفاع الكوليسترول الوراثي (متماثل الزيجوت)',
        'Obesity' => 'السمنة',
        'Morbid obesity' => 'السمنة المفرطة',
        'Gout' => 'النقرس',
        'Uric Acid Nephropathy Gout' => 'النقرس مع اعتلال الكلى بحمض اليوريك',
        'Anemia' => 'فقر الدم',
        'Acquired hemolytic anemia' => 'فقر الدم الانحلالي المكتسب',
        'Anemia due to enzyme deficiency' => 'فقر الدم بسبب نقص إنزيمي',
        'Aplastic anemia' => 'فقر الدم اللاتنسّجي',
        'Aplastic anemia due to drugs' => 'فقر الدم اللاتنسّجي الناجم عن الأدوية',
        'Autoimmune hemolytic anemia' => 'فقر الدم الانحلالي المناعي الذاتي',
        'Congenital hypoplastic anemia' => 'فقر الدم الناقص التنسّج الخلقي',
        'Constitutional aplastic anemia' => 'فقر الدم اللاتنسّجي الدستوري',
        "Fanconi's anemia" => 'فقر دم فانكوني',
        'Glucose-6-phosphate dehydrogenase deficiency anemia' => 'فقر الدم بسبب نقص إنزيم G6PD',
        'Hemolytic anemia' => 'فقر الدم الانحلالي',
        'Hypoplastic anemia' => 'فقر الدم الناقص التنسّج',
        'Iron deficiency anemia' => 'فقر الدم بعوز الحديد',
        'Megaloblastic anemia' => 'فقر الدم الضخم الأرومات',
        'Megaloblastic anemia due to folate deficiency' => 'فقر الدم الضخم الأرومات بسبب نقص حمض الفوليك',
        'Pernicious anemia' => 'فقر الدم الخبيث',
        'Sideroblastic anemia' => 'فقر الدم الحديدي الأرومي',
        'Blood coagulation disorder' => 'اضطراب تخثّر الدم',
        'Thrombocytopenic disorder' => 'نقص الصفيحات الدموية',
        'Epilepsy' => 'الصرع',
        'Uncontrolled Epilepsy' => 'الصرع غير المسيطر عليه',
        'Seizure disorder' => 'اضطراب النوبات',
        'Depressive disorder' => 'الاكتئاب',
        'Psychotic disorder' => 'اضطراب ذهاني',
        'Myasthenia gravis' => 'الوهن العضلي الوبيل',
        // "Asthenia" (وهن عام) ليست الوهن العضلي الوبيل، لكن مطابقة الـsubstring
        // بمحرك التداخلات ("asthenia" ضمن "myasthenia") ممكن ترجعها لنفس المريض.
        'Asthenia' => 'الوهن العام',
        'Glaucoma' => 'الجلوكوما (المياه الزرقاء)',
        'Aggravated Glaucoma' => 'تفاقم الجلوكوما',
        // نفس ملاحظة الـsubstring أعلاه: "coma" محتواة ضمن "glaucoma".
        'Coma' => 'الغيبوبة',
        'Open-angle glaucoma' => 'جلوكوما الزاوية المفتوحة',
        'Predisposition to Glaucoma' => 'الاستعداد للإصابة بالجلوكوما',
        'Primary Closed Angle Glaucoma' => 'جلوكوما مغلقة الزاوية الأولية',
        'Pupillary Block Glaucoma' => 'جلوكوما الانسداد الحدقي',
        'Angle-closure glaucoma' => 'جلوكوما مغلقة الزاوية',
        'Secondary angle-closure glaucoma' => 'جلوكوما مغلقة الزاوية الثانوية',
        'Secondary glaucoma' => 'الجلوكوما الثانوية',
        'Benign prostatic hyperplasia' => 'تضخم البروستاتا الحميد',
        'Retention of urine' => 'احتباس البول',
        'Systemic lupus erythematosus' => 'الذئبة الحمامية الجهازية',
        'Porphyria' => 'البورفيريا',
        'Acute intermittent porphyria' => 'البورفيريا المتقطعة الحادة',
        'Erythropoietic protoporphyria' => 'البورفيريا الحمراء البروتوبورفيرينية',
        'Hepatic porphyria' => 'البورفيريا الكبدية',
        'Porphyria cutanea tarda' => 'البورفيريا الجلدية المتأخرة',
        'Variegate porphyria' => 'البورفيريا المتنوعة',
        'Decreased respiratory function' => 'قصور تنفسي',
        'Alcoholism' => 'إدمان الكحول',
        'Smokes tobacco daily' => 'التدخين اليومي',
    ],

    // الألقاب المستخدمة عند تكوين اسم العرض.
    'title' => [
        'doctor_prefix' => 'د.',
    ],

];
