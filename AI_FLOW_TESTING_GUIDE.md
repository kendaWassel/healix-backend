# Testing the Laravel → FastAPI AI Flow (Login → Speech-to-Text → Symptom Extraction)

This guide documents the full, verified request chain from login to the last AI
service call, based on the current implementation. Every step below was tested
live end-to-end against the running services (200 OK, correct transcription,
symptoms persisted).

## 0. Before you start

| Service | Port | How to check it's up |
|---|---|---|
| Laravel | `8000` | `curl http://127.0.0.1:8000/api/faqs` |
| FastAPI | `8001` | `curl http://127.0.0.1:8001/` → `{"status":"online",...}` |

Environment (`.env`, single source of truth — `config/services.php`'s `'ai'`
key reads these; there is no other AI-service config path anymore):
```
AI_SERVICE_URL=http://127.0.0.1:8001
AI_SERVICE_TIMEOUT=60
AI_SERVICE_RETRIES=3
```

Laravel hands FastAPI an **absolute local filesystem path** to the audio file
(`audio_path`), not a URL — both services run on the same machine and share a
filesystem, so this avoids network hops entirely (no `APP_URL`/ngrok/loopback
dependency, no download step on FastAPI's side).

## 1. Login → get a Bearer token

```
POST http://127.0.0.1:8000/api/auth/login
Content-Type: application/json

{
  "email": "your-patient-account@example.com",
  "password": "your-password"
}
```

Response:
```json
{
  "token": "3|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "role": "patient",
  "email_verified": true
}
```

The account must have `role = patient` (checked by `role:patient` middleware
on the routes below) and a verified email (`verified` middleware).

Save the `token` value — every following request needs:
```
Authorization: Bearer <token>
Accept: application/json
```

## 2. Create a conversation

```
POST http://127.0.0.1:8000/api/patient/conversations
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "My symptoms"
}
```

Response (`201`):
```json
{
  "success": true,
  "message": "Conversation created successfully.",
  "data": {
    "id": 2,
    "patient_id": 1,
    "title": "My symptoms",
    "started_at": "2026-07-01T22:08:10.000000Z",
    "ended_at": null
  }
}
```

Save `data.id` — this is `conversation_id` for the next step.

## 3. Send audio → speech-to-text → symptom extraction (the main flow)

```
POST http://127.0.0.1:8000/api/speech-to-text
Authorization: Bearer <token>
Accept: application/json
Content-Type: multipart/form-data
```

Postman → Body → **form-data**:

| Key | Type | Value |
|---|---|---|
| `conversation_id` | Text | `2` (the id from step 2) |
| `audio` | File | pick a `.wav`/`.mp3`/`.m4a`/`.ogg`/`.webm` file, max 10MB |

This is **not** JSON — `TranscribeSpeechRequest` requires a real uploaded
file (`app/Http/Requests/TranscribeSpeechRequest.php`). There is no
`audio_path`/`audio_url` field on the Laravel side — those are internal,
FastAPI-facing values Laravel builds for you after storing the file.

Response (`200`, ~25-35s later — Whisper runs on CPU, this is expected):
```json
{
  "success": true,
  "message": "Speech converted successfully.",
  "message_id": 9,
  "text": "I have a headache and a fever since yesterday",
  "detected_symptoms": ["headache"]
}
```

Both `text` and `detected_symptoms` are also persisted on the `messages` row
(`transcribed_text`, `detected_symptoms` columns) — check via:
```
GET http://127.0.0.1:8000/api/patient/conversations/2/messages
Authorization: Bearer <token>
```

## 4. Complete request flow (as implemented)

```
Postman/Flutter
   → POST /api/speech-to-text  (multipart: conversation_id, audio file)
   → SpeechController::transcribe
       → stores file → storage/app/public/chat-audio/*.wav
       → creates Message (status=uploaded)
       → resolves absolute local path via Storage::disk('public')->path()
       → AIService::speechToText($absoluteAudioPath)
           → FastApiClient::post('/api/speech-to-text', {audio_path})
           → FastAPI reads the file directly off disk, runs Whisper
           → returns {success, text}
       → AIService::extractSymptoms($transcribedText)
           → FastApiClient::post('/api/symptoms/extract', {text})
           → FastAPI runs the MARBERT model
           → returns {success, detected_symptoms}
       → Message::update(transcribed_text, detected_symptoms, status=transcribed)
   → JSON response back to caller
```

## 5. History of fixes applied to this flow

Verified live at each step, not just read from code:

1. `AIService::speechToText()` originally sent both `audio_path` and
   `audio_url` in every request. FastAPI's schema explicitly rejects that
   combination (`422 "Provide either audio_path or audio_url, not both"`).
   Fixed to send exactly one field.
2. The `public/storage` symlink didn't exist, so any URL-based approach
   403'd. Ran `php artisan storage:link` (standard Laravel setup).
3. PHP's default `max_execution_time` (60s) is shorter than a real Whisper
   CPU transcription + symptom-extraction round trip. Added
   `set_time_limit(180)` at the top of `SpeechController::transcribe()`.
4. A URL-based approach (`audio_url` pointing at Laravel's own `/storage/...`)
   was tried and found unreliable in this environment — FastAPI's outbound
   HTTP download of that URL intermittently timed out even though the same
   URL was instantly reachable via `curl`, both through the project's ngrok
   tunnel and through plain loopback. Since Laravel and FastAPI run on the
   same machine, switched to `audio_path` (a direct filesystem read, no
   network hop, no timeout dependency) — this is the current, final,
   verified-working implementation.
5. `config/services.php` had two duplicate config trees (`services.fastapi.*`
   and `services.ai.*`) resolving to the same value through three different
   env vars (`FASTAPI_URL` dead, `FASTAPI_BASE_URL`, `AI_SERVICE_URL`).
   Consolidated to a single `services.ai.*` key backed by `AI_SERVICE_URL`/
   `AI_SERVICE_TIMEOUT`/`AI_SERVICE_RETRIES` only.

## 6. Error responses you may see

| Status | Body | Cause |
|---|---|---|
| `401` | `{"error":"Invalid credentials"}` | wrong login |
| `422` | validation errors | missing `conversation_id`/`audio`, bad file type, or conversation doesn't exist |
| `403` | `"You are not authorized to send messages in this conversation."` | `conversation_id` belongs to a different patient |
| `502` | `"AI service request failed with status 502."` | FastAPI couldn't read the audio file off disk (permissions, or the two services don't actually share a filesystem — e.g. different containers) |
| `500` | `"An unexpected error occurred during speech processing."` | unexpected exception — check `storage/logs/laravel.log` |

**Note:** the `audio_path` approach assumes Laravel and FastAPI run on the
same host (or a shared volume, e.g. in Docker Compose) so FastAPI's process
can read the path Laravel writes. If you ever deploy them on separate hosts
without a shared filesystem, you'll need to switch back to the `audio_url`
approach and solve the network-reachability issue described in fix #4 above
(e.g. a stable internal DNS name instead of a flaky tunnel).
