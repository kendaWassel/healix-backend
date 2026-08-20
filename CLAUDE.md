# Healix Backend (Laravel)

Laravel API backend for Healix — a medical platform with patients, doctors,
and an AI triage assistant. Consumes the separate Python/FastAPI Healix AI
service (`healix-ai-medical-assistant`) over HTTP via `HealixAiClient`/
`HealixAiService`/`HealixConversationService` (`app/Services/Healix/`).

This file is a running log of things a session should know before touching
related code — not a full architecture doc. Add to it; don't let findings
like the one below live only in a chat transcript.

## Pending coordination — needs the Flutter client owner before production

**2026-08-20 — `GET /api/medical-records/attachments/{id}/download` moved from fully public to `auth:sanctum`-gated.**

This endpoint (`MedicalRecordController::downloadAttachment`, route in
`routes/api.php`) used to be registered outside every auth group — anyone
with an `Upload` id could download any medical-record attachment, no
authentication at all. Fixed in this session alongside wiring a real
authorization check into it and into `GET /patients/{patient_id}/view-details`
(`MedicalRecordPolicy::view`, extended to also allow a doctor with an actual
`Consultation` for the patient, not just the record's own authoring doctor).

**Flagging for Aya specifically, since the real client here is Flutter (per
`InterviewChatController`'s own docblock and the README's documented
`Authorization: Bearer {token}` auth) and she may know its current behavior:**
if the Flutter app currently calls this download URL without attaching its
bearer token — plausible, since it used to work with zero auth — it will
start receiving 401s on this one endpoint until the client is updated to
send the token on this specific request. This needs confirming/coordinating
with whoever owns the Flutter client before this change reaches production;
it isn't something verifiable from this repo alone.

Everywhere else the token is already expected (the rest of `routes/api.php`
was already `auth:sanctum`-gated), so this is scoped to just this one
previously-public URL.
