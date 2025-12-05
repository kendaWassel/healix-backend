# Testing Real-Time Notification System

This guide provides comprehensive instructions for testing the notification system using Postman and the frontend.

## Prerequisites

1. **Pusher Account Setup**
   - Sign up at https://pusher.com
   - Create a new app
   - Get your App ID, Key, Secret, and Cluster
   - Add to `.env` file:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```

2. **Queue Configuration**
   - Make sure queue is configured (database driver recommended for testing)
   - Run migrations: `php artisan queue:table` and `php artisan migrate`
   - Start queue worker: `php artisan queue:work`

3. **Scheduler Setup**
   - Add to crontab: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`
   - Or run manually: `php artisan schedule:work` (for testing)

## Backend Testing with Postman

### 1. Test Consultation Booking (Triggers Real-Time Event)

**Endpoint:** `POST /api/patient/consultations/book`

**Headers:**
```
Authorization: Bearer {patient_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "doctor_id": 1,
    "call_type": "schedule",
    "scheduled_at": "2024-12-20 14:00:00"
}
```

**Expected Response:**
```json
{
    "status": "success",
    "message": "Consultation created",
    "data": {
        "consultation_id": 1,
        "doctor_id": 1,
        "patient_id": 1,
        "call_type": "schedule",
        "scheduled_at": "2024-12-20T14:00:00.000000Z",
        "status": "scheduled"
    }
}
```

**What to Check:**
- ✅ Response returns 201 status
- ✅ Consultation is created in database
- ✅ Doctor receives database notification
- ✅ `ConsultationBooked` event is broadcast to doctor's private channel

### 2. Test Broadcasting Authentication

**Endpoint:** `POST /broadcasting/auth`

**Headers:**
```
Authorization: Bearer {doctor_token}
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
    "socket_id": "123.456",
    "channel_name": "private-doctor.1"
}
```

**Expected Response:**
```json
{
    "auth": "pusher_app_key:signature",
    "channel_data": null
}
```

**What to Check:**
- ✅ Returns 200 status with auth token
- ✅ Only doctor with matching ID can authenticate
- ✅ Other users get 403 Forbidden

### 3. Test Channel Authorization

Test different channel access scenarios:

**Test 1: Doctor accessing own channel**
```
POST /broadcasting/auth
Channel: private-doctor.1
User: Doctor with doctor.id = 1
Expected: ✅ Success
```

**Test 2: Doctor accessing another doctor's channel**
```
POST /broadcasting/auth
Channel: private-doctor.2
User: Doctor with doctor.id = 1
Expected: ❌ 403 Forbidden
```

**Test 3: Patient accessing doctor channel**
```
POST /broadcasting/auth
Channel: private-doctor.1
User: Patient
Expected: ❌ 403 Forbidden
```

**Test 4: User accessing own user channel**
```
POST /broadcasting/auth
Channel: private-user.1
User: User with id = 1
Expected: ✅ Success
```

### 4. Test Reminder Notifications

**Manual Test:**
```bash
# Run reminder command manually
php artisan consultations:send-reminders --minutes=10 --window=5
```

**What to Check:**
- ✅ Consultations scheduled 10 minutes from now receive reminders
- ✅ Both patient and doctor receive notifications
- ✅ Notifications are stored in database
- ✅ Notifications are broadcast in real-time

**Create Test Consultation:**
```sql
-- Create a consultation scheduled 10 minutes from now
INSERT INTO consultations (patient_id, doctor_id, type, status, scheduled_at, created_at, updated_at)
VALUES (
    1, 
    1, 
    'schedule', 
    'scheduled', 
    DATE_ADD(NOW(), INTERVAL 10 MINUTE),
    NOW(),
    NOW()
);
```

Then run the command and verify notifications are sent.

### 5. Test Arrival Notifications

**Manual Test:**
```bash
# Run arrival notification command
php artisan consultations:send-arrival-notifications --window=5
```

**Create Test Consultation:**
```sql
-- Create a consultation scheduled now
INSERT INTO consultations (patient_id, doctor_id, type, status, scheduled_at, created_at, updated_at)
VALUES (
    1, 
    1, 
    'schedule', 
    'scheduled', 
    NOW(),
    NOW(),
    NOW()
);
```

**What to Check:**
- ✅ Consultations scheduled within ±5 minutes receive arrival notifications
- ✅ Both patient and doctor receive notifications
- ✅ Notifications are stored and broadcast

## Frontend Testing

### 1. Setup Frontend

1. Install dependencies:
```bash
npm install laravel-echo pusher-js
```

2. Configure Echo (see FRONTEND_NOTIFICATION_SETUP.md)

3. Add NotificationBell component to your app

### 2. Test Real-Time Events

**Step 1: Login as Doctor**
- Login and get authentication token
- Frontend should automatically connect to Pusher
- Check browser console for connection status

**Step 2: Book Consultation (as Patient)**
- Open another browser/incognito window
- Login as patient
- Book a consultation for the doctor

**Step 3: Verify Real-Time Notification**
- In doctor's browser, you should see:
  - ✅ Notification appears immediately (without page refresh)
  - ✅ Unread count increases
  - ✅ Console shows event received

**Expected Console Output:**
```
Echo connected
Consultation booked: {consultation_id: 1, patient_name: "John Doe", ...}
```

### 3. Test Notification UI

**Test Cases:**
1. ✅ Click notification bell - dropdown opens
2. ✅ Unread notifications show blue dot
3. ✅ Click notification - marks as read
4. ✅ "Mark all as read" button works
5. ✅ Notification list scrolls if many notifications
6. ✅ Empty state shows when no notifications

### 4. Test Multiple Users

**Scenario: Multiple Doctors**
1. Login as Doctor 1
2. Login as Doctor 2 (different browser)
3. Book consultation for Doctor 1
4. Verify: Only Doctor 1 receives notification

**Scenario: Patient and Doctor**
1. Login as Patient
2. Login as Doctor (different browser)
3. Book consultation
4. Verify: Doctor receives notification immediately
5. Wait for reminder time
6. Verify: Both receive reminder notification

## Testing Checklist

### Backend
- [ ] Pusher credentials configured correctly
- [ ] BroadcastServiceProvider enabled
- [ ] Queue worker running
- [ ] Scheduler running (or manual testing)
- [ ] Channel authorization works
- [ ] ConsultationBooked event fires
- [ ] Notifications stored in database
- [ ] Notifications broadcast in real-time

### Frontend
- [ ] Echo connects to Pusher
- [ ] Private channel authentication works
- [ ] ConsultationBooked event received
- [ ] Notification UI displays correctly
- [ ] Mark as read functionality works
- [ ] Unread count updates correctly
- [ ] Multiple users receive correct notifications

## Debugging Tips

### 1. Check Pusher Dashboard
- Go to https://dashboard.pusher.com
- Check "Debug Console" tab
- Verify events are being sent

### 2. Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### 3. Check Queue Jobs
```bash
# See failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### 4. Test Broadcasting Manually
```php
// In tinker
php artisan tinker

// Broadcast test event
broadcast(new \App\Events\ConsultationBooked($consultation, $patient, $doctor));
```

### 5. Check Database Notifications
```sql
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;
```

### 6. Verify Channel Authorization
```php
// Test in tinker
$user = User::find(1);
Broadcast::channel('doctor.1', function ($user) {
    return $user->role === 'doctor' && $user->doctor->id === 1;
});
```

## Common Issues

### Issue: Events not broadcasting
**Solution:**
- Check `BROADCAST_CONNECTION=pusher` in .env
- Verify queue worker is running
- Check Pusher credentials
- Verify BroadcastServiceProvider is enabled

### Issue: Channel authorization fails
**Solution:**
- Check user role matches channel requirements
- Verify doctor/patient relationship exists
- Check middleware is applied to `/broadcasting/auth`

### Issue: Frontend not receiving events
**Solution:**
- Verify Echo is connected (check console)
- Check token is valid and included in auth headers
- Verify channel name matches backend
- Check Pusher dashboard for events

### Issue: Notifications not stored
**Solution:**
- Verify notifications table exists
- Check User model uses Notifiable trait
- Verify queue is processing jobs

## Postman Collection

Create a Postman collection with these requests:

1. **Login (Patient)**
   - POST /api/auth/login
   - Save token as `patient_token`

2. **Login (Doctor)**
   - POST /api/auth/login
   - Save token as `doctor_token`

3. **Book Consultation**
   - POST /api/patient/consultations/book
   - Use `patient_token`
   - This triggers real-time event

4. **Broadcasting Auth**
   - POST /broadcasting/auth
   - Use `doctor_token`
   - Test channel authorization

5. **Get Notifications**
   - GET /api/notifications
   - Use `doctor_token`
   - Verify notifications are stored

## Automated Testing

You can create PHPUnit tests:

```php
public function test_consultation_booked_event_is_broadcast()
{
    Event::fake();
    
    $patient = User::factory()->create(['role' => 'patient']);
    $doctor = User::factory()->create(['role' => 'doctor']);
    
    $response = $this->actingAs($patient)
        ->postJson('/api/patient/consultations/book', [
            'doctor_id' => $doctor->doctor->id,
            'call_type' => 'schedule',
            'scheduled_at' => now()->addHour(),
        ]);
    
    Event::assertDispatched(ConsultationBooked::class);
}
```

## Performance Testing

1. **Load Test**: Book multiple consultations rapidly
2. **Concurrent Users**: Test with multiple doctors/patients
3. **Queue Performance**: Monitor queue processing time
4. **Pusher Limits**: Check Pusher dashboard for rate limits

