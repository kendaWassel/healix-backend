<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enum / Constant Display Labels
    |--------------------------------------------------------------------------
    |
    | The raw enum values stored in the database are internal identifiers and
    | are NEVER translated — they keep flowing through the API unchanged so
    | existing clients continue to work. These labels are the human-readable
    | counterparts, exposed additively as `*_label` fields by API Resources.
    |
    */

    'role' => [
        'patient' => 'Patient',
        'doctor' => 'Doctor',
        'pharmacist' => 'Pharmacist',
        'care_provider' => 'Care provider',
        'nurse' => 'Nurse',
        'physiotherapist' => 'Physiotherapist',
        'delivery' => 'Delivery driver',
        'admin' => 'Administrator',
    ],

    'gender' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],

    'account_status' => [
        'pending' => 'Pending approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'consultation_type' => [
        'call_now' => 'Immediate call',
        'schedule' => 'Scheduled',
    ],

    'consultation_status' => [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'home_visit_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'rescheduled' => 'Rescheduled',
    ],

    'service_type' => [
        'nurse' => 'Nursing',
        'physiotherapist' => 'Physiotherapy',
    ],

    'prescription_source' => [
        'doctor_written' => 'Written by doctor',
        'patient_uploaded' => 'Uploaded by patient',
    ],

    'prescription_status' => [
        'created' => 'Created',
        'sent_to_pharmacy' => 'Sent to pharmacy',
        'pending' => 'Being processed',
        'accepted' => 'Accepted',
        'priced' => 'Priced',
        'rejected' => 'Rejected',
    ],

    'order_status' => [
        'pending' => 'Pending',
        'sent_to_pharmacy' => 'Sent to pharmacy',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'ready_for_delivery' => 'Ready for delivery',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
    ],

    'delivery_task_status' => [
        'pending' => 'Pending',
        'picking_up_the_order' => 'Picking up the order',
        'on_the_way' => 'On the way',
        'delivered' => 'Delivered',
    ],

    'delivery_candidate_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ],

    'payment_status' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'message_sender' => [
        'patient' => 'Patient',
        'assistant' => 'Assistant',
    ],

    'message_type' => [
        'text' => 'Text',
        'voice' => 'Voice',
    ],

    'message_status' => [
        'uploaded' => 'Uploaded',
        'transcribed' => 'Transcribed',
        'failed' => 'Failed',
    ],

    'lab_severity' => [
        'normal' => 'Normal',
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
        'critical' => 'Critical',
    ],

    'triage' => [
        'High' => 'High priority',
        'Medium' => 'Medium priority',
        'Low' => 'Low priority',
    ],

    // Interaction severity reported by the DDI microservice. The raw value is
    // passed through untranslated; this is only the display label.
    'ddi_severity' => [
        'none' => 'No known interaction',
        'minor' => 'Minor',
        'moderate' => 'Moderate',
        'major' => 'Major',
        'contraindicated' => 'Contraindicated',
        'unknown' => 'Unknown',
        // Capitalised keys match the exact casing the DDI service returns.
        'Minor' => 'Minor',
        'Moderate' => 'Moderate',
        'Major' => 'Major',
    ],

    // Confidence in the predicted interaction severity (DDI service values).
    'ddi_confidence' => [
        'LOW' => 'Low confidence',
        'MEDIUM' => 'Medium confidence',
        'HIGH' => 'High confidence',
        'UNCERTAIN' => 'Uncertain',
        'OVERRIDE' => 'Clinically verified',
    ],

    // How an allergy conflict was detected (DDI service values).
    'ddi_detected_by' => [
        'direct_match' => 'Direct allergy',
        'pharmacophore_class' => 'Same drug class',
        'structural_similarity' => 'Structural similarity',
        'atc_class' => 'Same ATC class',
        'cross_reactivity' => 'Cross-reactivity',
    ],

    // Risk level of an allergy / cross-reactivity finding.
    'ddi_risk' => [
        'CRITICAL' => 'Critical',
        'HIGH' => 'High',
        'MODERATE' => 'Moderate',
        'LOW' => 'Low',
    ],

    // Pregnancy risk category (US FDA letter categories).
    'ddi_pregnancy_category' => [
        'X' => 'Category X — contraindicated',
        'D' => 'Category D — positive evidence of risk',
        'D*' => 'Category D* — avoid in late pregnancy',
        'C' => 'Category C — use with caution',
        'B' => 'Category B — relatively safe',
        'A' => 'Category A — safe',
        'N/A' => 'Not available',
        'Unknown' => 'Unknown',
    ],

    // Hand-curated pregnancy warnings for a limited set of common active
    // ingredients (source: the DDI service's clinical pregnancy database).
    // Any ingredient not listed here keeps its original raw text as returned
    // by the service — a free-text clinical note is never invented.
    'ddi_pregnancy_warning' => [
        'acetaminophen' => 'Generally safe at recommended doses',
        'alprazolam' => 'Avoid — risk of neonatal withdrawal',
        'amlodipine' => 'Use with caution — limited human data',
        'amoxicillin' => 'Generally safe in pregnancy — penicillins considered low risk',
        'atenolol' => 'Avoid if possible — associated with fetal growth restriction',
        'atorvastatin' => 'Statins contraindicated in pregnancy',
        'azithromycin' => 'Generally safe in pregnancy',
        'betamethasone' => 'Used to accelerate fetal lung maturity — acceptable',
        'bisoprolol' => 'Use with caution — beta-blockers may cause fetal bradycardia',
        'caffeine' => 'Limit intake in pregnancy — high doses associated with fetal growth restriction',
        'captopril' => 'ACE inhibitors — contraindicated in 2nd/3rd trimester',
        'carbamazepine' => 'Teratogenic risk — neural tube defects',
        'cefixime' => 'Cephalosporins generally safe in pregnancy',
        'cephalexin' => 'Cephalosporins generally safe in pregnancy',
        'cetirizine' => 'Generally safe in pregnancy',
        'cholecalciferol' => 'Safe at recommended doses — important in pregnancy',
        'ciprofloxacin' => 'Use with caution — avoid if alternatives available',
        'clarithromycin' => 'Use with caution — animal studies show risk, limited human data',
        'clindamycin' => 'Generally safe in pregnancy',
        'clopidogrel' => 'Limited data — use only if clearly needed',
        'codeine' => 'Use with caution — avoid near term, risk of neonatal withdrawal',
        'dexamethasone' => 'Use with caution — used for fetal lung maturity',
        'diazepam' => 'Avoid — risk of neonatal withdrawal and floppy infant',
        'doxycycline' => 'Avoid — may cause fetal bone and tooth development issues',
        'doxylamine' => 'Safe — specifically indicated for pregnancy nausea',
        'enalapril' => 'ACE inhibitors — contraindicated in 2nd/3rd trimester',
        'enoxaparin' => 'Low molecular weight heparin — safe in pregnancy',
        'esomeprazole' => 'Use with caution — limited human data',
        'fexofenadine' => 'Use with caution — limited human data',
        'fluconazole' => 'Single dose may be acceptable, prolonged use avoid',
        'fluoxetine' => 'Use with caution — risk of neonatal adaptation syndrome',
        'folic acid' => 'Essential in pregnancy — prevents neural tube defects',
        'furosemide' => 'Use with caution — may reduce placental blood flow',
        'heparin' => 'Safe in pregnancy — does not cross placenta, preferred over warfarin',
        'hydralazine' => 'Used for severe hypertension in pregnancy',
        'hydrocortisone' => 'Use with caution — short term use generally acceptable',
        'insulin glargine' => 'Use with caution — insulin analogs have limited pregnancy data',
        'insulin lispro' => 'Generally safe — widely used in pregnancy',
        'irbesartan' => 'ARBs — contraindicated in 2nd/3rd trimester',
        'isotretinoin' => 'Contraindicated — severe teratogen',
        'labetalol' => 'Used for hypertension in pregnancy — generally acceptable',
        'lactulose' => 'Generally safe in pregnancy',
        'levofloxacin' => 'Use with caution — avoid if alternatives available',
        'levothyroxine' => 'Safe — required for maternal and fetal thyroid function',
        'lisinopril' => 'ACE inhibitors — contraindicated in 2nd/3rd trimester, risk of fetal renal damage',
        'loperamide' => 'Generally safe — limited absorption',
        'loratadine' => 'Generally safe — preferred antihistamine in pregnancy',
        'losartan' => 'ARBs — contraindicated in 2nd/3rd trimester, risk of fetal renal damage',
        'metformin' => 'Generally considered safe — no clear evidence of fetal harm',
        'methimazole' => 'Avoid in first trimester — risk of fetal abnormalities',
        'methotrexate' => 'Contraindicated — causes fetal death and malformations',
        'methyldopa' => 'Drug of choice for hypertension in pregnancy — well established safety',
        'metoclopramide' => 'Generally safe for pregnancy nausea',
        'metoprolol' => 'Use with caution — beta-blockers may cause fetal bradycardia',
        'metronidazole' => 'Generally safe — avoid in first trimester if possible',
        'misoprostol' => 'Contraindicated — causes uterine contractions and abortion',
        'morphine' => 'Use with caution — avoid near term, risk of neonatal withdrawal',
        'nifedipine' => 'Commonly used in pregnancy for hypertension — generally considered safe',
        'nitrofurantoin' => 'Generally safe — avoid near term (38+ weeks)',
        'omeprazole' => 'Use with caution — limited human data',
        'ondansetron' => 'Commonly used for pregnancy nausea — generally considered safe',
        'prednisolone' => 'Use with caution — short term use generally acceptable',
        'promethazine' => 'Use with caution — commonly used for pregnancy nausea',
        'propranolol' => 'Use with caution — may cause neonatal bradycardia',
        'propylthiouracil' => 'Use with caution — preferred over methimazole in first trimester',
        'ramipril' => 'ACE inhibitors — contraindicated in 2nd/3rd trimester',
        'ranitidine' => 'Generally safe in pregnancy',
        'rosuvastatin' => 'Statins contraindicated in pregnancy',
        'sertraline' => 'Use with caution — risk of neonatal adaptation syndrome',
        'simvastatin' => 'Statins contraindicated in pregnancy',
        'sitagliptin' => 'Limited data — insulin preferred in pregnancy',
        'spironolactone' => 'Avoid — anti-androgenic effects on fetus',
        'thalidomide' => 'Contraindicated — severe teratogen',
        'tramadol' => 'Use with caution — avoid near term, risk of neonatal withdrawal',
        'trimethoprim' => 'Use with caution — avoid in first trimester',
        'valsartan' => 'ARBs — contraindicated in 2nd/3rd trimester',
        'warfarin' => 'Contraindicated in pregnancy — risk of fetal hemorrhage and malformation',
    ],

    // The picker only sends 45 fixed values, but DrugCentral matches by
    // substring containment (see check_condition_contraindications in the DDI
    // engine), so a picker value like "Diabetes mellitus" or "Glaucoma" can
    // come back as a more specific DrugCentral condition name (e.g. "Gestational
    // diabetes mellitus"). This dictionary covers every condition_name that can
    // actually be reached from the 45 picker values, not just the picker values
    // themselves — computed against Data/contraindications.csv, not guessed.
    'ddi_condition' => [
        'Disease of liver' => 'Liver disease',
        'Inflammatory disease of liver' => 'Inflammatory liver disease',
        'Kidney disease' => 'Kidney disease',
        'Diabetes mellitus' => 'Diabetes mellitus',
        'Diabetes mellitus type 1' => 'Type 1 diabetes mellitus',
        'Diabetes mellitus type 2' => 'Type 2 diabetes mellitus',
        'Gestational diabetes mellitus' => 'Gestational diabetes',
        'Ketoacidosis in diabetes mellitus' => 'Diabetic ketoacidosis',
        'Severe Diabetes Mellitus' => 'Severe diabetes mellitus',
        'Hypertensive disorder' => 'Hypertensive disorder',
        'Asthma' => 'Asthma',
        'Acute exacerbation of asthma' => 'Acute asthma exacerbation',
        'Eosinophilic asthma' => 'Eosinophilic asthma',
        'Exacerbation of asthma' => 'Asthma exacerbation',
        'Refractory Extrinsic Asthma' => 'Refractory extrinsic asthma',
        'Chronic heart failure' => 'Chronic heart failure',
        'Chronic Heart Failure Following Myocardial Infarction' => 'Chronic heart failure following a heart attack',
        'Decompensated chronic heart failure' => 'Decompensated chronic heart failure',
        'Heart failure' => 'Heart failure',
        'Worsening of Chronic Heart Failure' => 'Worsening chronic heart failure',
        'Myocardial infarction' => 'Myocardial infarction',
        'Myocardial infarction in recovery phase' => 'Myocardial infarction (recovery phase)',
        'Non-Q wave myocardial infarction' => 'Non-Q wave myocardial infarction',
        'Disorder of coronary artery' => 'Coronary artery disease',
        'Angina pectoris' => 'Angina pectoris',
        'Progressive Angina Pectoris' => 'Progressive angina',
        'Conduction disorder of the heart' => 'Cardiac conduction disorder',
        'Bradycardia' => 'Bradycardia',
        'Sinus bradycardia' => 'Sinus bradycardia',
        'Syncope due to Bradycardia' => 'Fainting due to slow heart rate',
        'Vagal Reflex Bradycardia' => 'Vagal reflex bradycardia',
        'Low blood pressure' => 'Low blood pressure',
        'Cerebrovascular accident' => 'Cerebrovascular accident',
        'Prolonged QT interval' => 'Prolonged QT interval',
        'Hepatic failure' => 'Hepatic failure',
        'Acute hepatic failure' => 'Acute liver failure',
        'Impaired renal function disorder' => 'Impaired renal function',
        'Disorder of gallbladder' => 'Gallbladder disease',
        'Peptic ulcer' => 'Peptic ulcer',
        'Acute peptic ulcer' => 'Acute peptic ulcer',
        'Acute peptic ulcer with hemorrhage' => 'Acute peptic ulcer with bleeding',
        'Chronic peptic ulcer' => 'Chronic peptic ulcer',
        'Gastrointestinal ulcer' => 'Gastrointestinal ulcer',
        'Ulcerative colitis' => 'Ulcerative colitis',
        'Colitis' => 'Colitis',
        'Severe chronic ulcerative colitis' => 'Severe chronic ulcerative colitis',
        'Acute pancreatitis' => 'Acute pancreatitis',
        'Pancreatitis' => 'Pancreatitis',
        'Chronic idiopathic constipation' => 'Chronic idiopathic constipation',
        'Constipation' => 'Constipation',
        'Hyperthyroidism' => 'Hyperthyroidism',
        'Hypothyroidism' => 'Hypothyroidism',
        'Hypercholesterolemia' => 'Hypercholesterolemia',
        'Familial hypercholesterolemia - homozygous' => 'Familial hypercholesterolemia (homozygous)',
        'Obesity' => 'Obesity',
        'Morbid obesity' => 'Morbid obesity',
        'Gout' => 'Gout',
        'Uric Acid Nephropathy Gout' => 'Gout with uric acid nephropathy',
        'Anemia' => 'Anemia',
        'Acquired hemolytic anemia' => 'Acquired hemolytic anemia',
        'Anemia due to enzyme deficiency' => 'Anemia due to enzyme deficiency',
        'Aplastic anemia' => 'Aplastic anemia',
        'Aplastic anemia due to drugs' => 'Drug-induced aplastic anemia',
        'Autoimmune hemolytic anemia' => 'Autoimmune hemolytic anemia',
        'Congenital hypoplastic anemia' => 'Congenital hypoplastic anemia',
        'Constitutional aplastic anemia' => 'Constitutional aplastic anemia',
        "Fanconi's anemia" => 'Fanconi anemia',
        'Glucose-6-phosphate dehydrogenase deficiency anemia' => 'G6PD deficiency anemia',
        'Hemolytic anemia' => 'Hemolytic anemia',
        'Hypoplastic anemia' => 'Hypoplastic anemia',
        'Iron deficiency anemia' => 'Iron deficiency anemia',
        'Megaloblastic anemia' => 'Megaloblastic anemia',
        'Megaloblastic anemia due to folate deficiency' => 'Megaloblastic anemia due to folate deficiency',
        'Pernicious anemia' => 'Pernicious anemia',
        'Sideroblastic anemia' => 'Sideroblastic anemia',
        'Blood coagulation disorder' => 'Blood coagulation disorder',
        'Thrombocytopenic disorder' => 'Thrombocytopenic disorder',
        'Epilepsy' => 'Epilepsy',
        'Uncontrolled Epilepsy' => 'Uncontrolled epilepsy',
        'Seizure disorder' => 'Seizure disorder',
        'Depressive disorder' => 'Depressive disorder',
        'Psychotic disorder' => 'Psychotic disorder',
        'Myasthenia gravis' => 'Myasthenia gravis',
        // "Asthenia" (general weakness) is not myasthenia gravis, but the DDI
        // engine's substring match ("asthenia" inside "myasthenia") returns it
        // for these patients too — translated so it displays correctly if it does.
        'Asthenia' => 'Asthenia (general weakness)',
        'Glaucoma' => 'Glaucoma',
        'Aggravated Glaucoma' => 'Aggravated glaucoma',
        // Same substring-match quirk as Asthenia above: "coma" is contained in
        // "glaucoma", so it can surface for glaucoma patients too.
        'Coma' => 'Coma',
        'Open-angle glaucoma' => 'Open-angle glaucoma',
        'Predisposition to Glaucoma' => 'Predisposition to glaucoma',
        'Primary Closed Angle Glaucoma' => 'Primary angle-closure glaucoma',
        'Pupillary Block Glaucoma' => 'Pupillary block glaucoma',
        'Angle-closure glaucoma' => 'Angle-closure glaucoma',
        'Secondary angle-closure glaucoma' => 'Secondary angle-closure glaucoma',
        'Secondary glaucoma' => 'Secondary glaucoma',
        'Benign prostatic hyperplasia' => 'Benign prostatic hyperplasia',
        'Retention of urine' => 'Urinary retention',
        'Systemic lupus erythematosus' => 'Systemic lupus erythematosus',
        'Porphyria' => 'Porphyria',
        'Acute intermittent porphyria' => 'Acute intermittent porphyria',
        'Erythropoietic protoporphyria' => 'Erythropoietic protoporphyria',
        'Hepatic porphyria' => 'Hepatic porphyria',
        'Porphyria cutanea tarda' => 'Porphyria cutanea tarda',
        'Variegate porphyria' => 'Variegate porphyria',
        'Decreased respiratory function' => 'Decreased respiratory function',
        'Alcoholism' => 'Alcohol use disorder',
        'Smokes tobacco daily' => 'Daily tobacco use',
    ],

    // Honorifics used when composing a person's display name.
    'title' => [
        'doctor_prefix' => 'Dr.',
    ],

];
