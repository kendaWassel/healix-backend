# Environment Configuration for Real-Time Notifications

## Backend (.env) Configuration

Add these variables to your Laravel `.env` file:

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=pusher

# Pusher Configuration
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=your_pusher_cluster

# Optional Pusher Configuration (usually not needed)
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https

# Queue Configuration (for processing notifications)
QUEUE_CONNECTION=database
```

## Getting Pusher Credentials

1. **Sign up for Pusher**
   - Go to https://pusher.com
   - Create a free account (or paid plan)

2. **Create a New App**
   - Click "Create app" or "Channels apps"
   - Choose a name for your app
   - Select a cluster closest to your users
   - Click "Create app"

3. **Get Your Credentials**
   - Go to "App Keys" tab
   - Copy the following:
     - App ID
     - Key
     - Secret
     - Cluster

4. **Add to .env**
   ```env
   PUSHER_APP_ID=1234567
   PUSHER_APP_KEY=abcdefghijklmnop
   PUSHER_APP_SECRET=your_secret_here
   PUSHER_APP_CLUSTER=mt1
   ```

## Frontend Environment Variables

### React (.env or .env.local)

```env
REACT_APP_PUSHER_APP_KEY=your_pusher_app_key
REACT_APP_PUSHER_APP_CLUSTER=your_pusher_cluster
REACT_APP_API_URL=http://localhost:8000
```

### Vue.js (.env or .env.local)

```env
VITE_PUSHER_APP_KEY=your_pusher_app_key
VITE_PUSHER_APP_CLUSTER=your_pusher_cluster
VITE_API_URL=http://localhost:8000
```

### Next.js (.env.local)

```env
NEXT_PUBLIC_PUSHER_APP_KEY=your_pusher_app_key
NEXT_PUBLIC_PUSHER_APP_CLUSTER=your_pusher_cluster
NEXT_PUBLIC_API_URL=http://localhost:8000
```

## Verification Steps

1. **Check Backend Configuration**
   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan tinker
   # Then run: config('broadcasting.default') // Should return 'pusher'
   ```

2. **Test Pusher Connection**
   ```bash
   php artisan tinker
   # Then run:
   broadcast(new \App\Events\ConsultationBooked($consultation, $patient, $doctor));
   # Check Pusher dashboard to see if event appears
   ```

3. **Check Frontend Connection**
   - Open browser console
   - Look for "Echo connected" message
   - Check for any connection errors

## Important Notes

1. **Never commit .env files** - Add to `.gitignore`
2. **Use different Pusher apps** for development and production
3. **Free Pusher plan** has limits (200k messages/day, 100 concurrent connections)
4. **Cluster selection** affects latency - choose closest to your users
5. **Secret key** should never be exposed to frontend

## Troubleshooting

### Issue: "Broadcasting connection not configured"
- Check `BROADCAST_CONNECTION=pusher` in .env
- Run `php artisan config:clear`

### Issue: "Pusher authentication failed"
- Verify Pusher credentials are correct
- Check that BroadcastServiceProvider is enabled
- Verify auth middleware on `/broadcasting/auth` route

### Issue: "Frontend can't connect"
- Check Pusher key and cluster in frontend .env
- Verify API URL is correct
- Check browser console for CORS errors
- Verify token is being sent in auth headers

