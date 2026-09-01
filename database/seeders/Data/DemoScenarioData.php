<?php

namespace Database\Seeders\Data;

/**
 * Central, non-DB source of truth for demo/seed data — emails, the shared
 * demo password, and the drug names used in the DDI demo scenarios. Kept
 * separate from the seeders themselves so every seeder that needs "the
 * patient's email" or "the interaction-demo drug pair" reads the same value
 * instead of re-typing it.
 *
 * Exactly one account per role — no extra "fleet" accounts. Every scenario
 * seeder (consultations, orders, home visits, ratings, AI conversations)
 * hangs its data off these same seven accounts instead of a separate demo
 * patient/doctor/etc. per case: a real patient can plausibly have several
 * consultations, orders, and home visits over time, so collapsing "one
 * scenario = one account" onto "one account, several scenarios" loses
 * nothing a real tester would notice.
 *
 * No Eloquent/DB calls belong in this file — constants and static arrays
 * only.
 */
class DemoScenarioData
{
    /** Every demo account uses this password. */
    public const DEMO_PASSWORD = 'password';

    /**
     * Fixed reference point for any seeder that spreads bulk history
     * backward/forward from "today" (consultations, home visits, ...).
     * Deliberately NOT now() — a match key built from now()->subWeeks(...)
     * changes every calendar day, so updateOrCreate() stops matching
     * yesterday's rows and starts creating duplicates instead of updating
     * them. Anchoring to a fixed instant keeps every rerun idempotent,
     * regardless of what day it's run on. Update this by hand (and rerun
     * the seeders) if the demo data ever needs to look freshly relative to
     * a later date.
     */
    public const SEED_ANCHOR = '2026-08-28 12:00:00';

    public const ADMIN_EMAIL = 'admin@gmail.com';
    public const PATIENT_EMAIL = 'patient@gmail.com';
    public const DOCTOR_EMAIL = 'doctor@gmail.com';
    public const PHARMACIST_EMAIL = 'pharmacist@gmail.com';
    public const NURSE_EMAIL = 'nurse@gmail.com';
    public const PHYSIOTHERAPIST_EMAIL = 'physiotherapist@gmail.com';
    public const DELIVERY_EMAIL = 'delivery@gmail.com';

    /** role => email, for the base one-per-role accounts UserSeeder creates. */
    public const BASE_ACCOUNTS = [
        'admin' => self::ADMIN_EMAIL,
        'patient' => self::PATIENT_EMAIL,
        'doctor' => self::DOCTOR_EMAIL,
        'pharmacist' => self::PHARMACIST_EMAIL,
        'nurse' => self::NURSE_EMAIL,
        'physiotherapist' => self::PHYSIOTHERAPIST_EMAIL,
        'delivery' => self::DELIVERY_EMAIL,
    ];

    /**
     * One doctor per specialization, so every specialty in the real
     * `specializations` table (21 rows total — checked live, not assumed;
     * it's two merged lists: 10 pre-existing ones this session didn't
     * create, plus the 11 from SpecializationsTableSeeder.php) is actually
     * bookable in the demo. DOCTOR_EMAIL (Cardiology) already exists as
     * the main/original doctor account; these twenty cover the rest.
     * email => specialization name (matches the `name` column, not `name_ar`).
     */
    public const SPECIALIST_DOCTORS = [
        // Pre-existing specializations (ids 1-10 in the live DB).
        'gastroenterology.doctor@gmail.com' => 'Gastroenterology',
        'generalmedicine.doctor@gmail.com' => 'General Medicine',
        'hematology.doctor@gmail.com' => 'Hematology',
        'infectiousdisease.doctor@gmail.com' => 'Infectious Disease',
        'ent.doctor@gmail.com' => 'Otolaryngology (ENT)',
        'pulmonology.doctor@gmail.com' => 'Pulmonology',
        'rheumatology.doctor@gmail.com' => 'Rheumatology',
        'familymedicine.doctor@gmail.com' => 'Family Medicine',
        'endocrinology.doctor@gmail.com' => 'Endocrinology',
        'obgyn.doctor@gmail.com' => 'Obstetrics and Gynecology',
        // SpecializationsTableSeeder.php's own list (ids 11-21).
        'dermatology.doctor@gmail.com' => 'Dermatology',
        'orthopedics.doctor@gmail.com' => 'Orthopedics',
        'pediatrics.doctor@gmail.com' => 'Pediatrics',
        'neurology.doctor@gmail.com' => 'Neurology',
        'oncology.doctor@gmail.com' => 'Oncology',
        'ophthalmology.doctor@gmail.com' => 'Ophthalmology',
        'surgery.doctor@gmail.com' => 'General Surgery',
        'psychiatry.doctor@gmail.com' => 'Psychiatry',
        'gynecology.doctor@gmail.com' => 'Gynecology',
        'urology.doctor@gmail.com' => 'Urology',
    ];

    // ------------------------------------------------------------------
    // Drug names used in the DDI demo scenarios (database/seeders/data/…
    // not the live DDI service's own catalog). Each pairing below was
    // verified live against the DDI microservice on 127.0.0.1:8002 before
    // being written here — see docs/demo/DRUG_INTERACTION_DEMO_SCENARIOS_AR.md
    // for the exact response each one produced.
    // ------------------------------------------------------------------

    /** Scenario 1 — single drug, no pair to check (service requires >=2 meds). */
    public const SCENARIO_SINGLE_DRUG = ['Paracetamol'];

    /** Scenario 2 — confirmed live: GET /interaction -> "No interaction expected". */
    public const SCENARIO_NO_INTERACTION = ['Cholecalciferol', 'Amoxicillin'];

    /** Scenario 3 — confirmed live: Major, severity_confidence=OVERRIDE. */
    public const SCENARIO_MAJOR_INTERACTION = ['Warfarin', 'Ibuprofen'];

    /** Scenario 4 — confirmed live via POST /screen: 10/10 pairs flagged, Major->Minor spread. */
    public const SCENARIO_MULTI_DRUG = ['Warfarin', 'Ibuprofen', 'Metformin', 'Omeprazole', 'Cholecalciferol'];

    /** Scenario 5 — confirmed live: GET /resolve -> 404 "Could not resolve". */
    public const SCENARIO_UNKNOWN_DRUG = ['Xyzdrugfake123'];

    /** Scenario 6 — confirmed live: both resolve to RxCUI 161 (brand_name vs ingredient). */
    public const SCENARIO_BRAND_VS_GENERIC = ['Tylenol', 'Acetaminophen'];
}
