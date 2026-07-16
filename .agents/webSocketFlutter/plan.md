# WebSocket + Flutter Real-Time isActive Plan

## Overview
جعل تغيير حالة `isActive` للدكتور تنعكس مباشرة (Real-Time) على تطبيق Flutter
باستخدام WebSocket بدلا من الاعتماد على REST API فقط.

## Current State (الوضع الحالي)
- لا يوجد أي WebSocket/Broadcasting setup في المشروع
- تغيير `isActive` يتم فقط من لوحة تحكم الأدمن عبر:
  `POST /dashboard/users/{user}/toggle-status` → `AdminDashboardController@toggleUserStatus` (Blade Web route)
- تطبيق Flutter لا يشاهد التغيير إلا عند إعادة تحميل البيانات يدويا (polling أو pull-to-refresh)
- يوجد Push Notification عبر FCM (نظام Firebase) لكنه ليس WebSocket ولا يضمن الاستلام الفوري

## Architecture (الهيكل المقترح)

```
┌─────────────┐     toggle isActive      ┌───────────────┐
│  Admin       │ ──────────────────────> │  Laravel       │
│  Dashboard   │   POST /dashboard/      │  Backend       │
│  (Blade)     │   users/{id}/toggle     │                │
└─────────────┘                          │  ┌───────────┐ │
                                         │  │ Event     │ │
                                         │  │ fired     │ │
                                         │  └─────┬─────┘ │
                                         │        │        │
                                         │  ┌─────▼─────┐ │
                                         │  │ Broadcast │ │
                                         │  │ (Reverb)  │ │
                                         │  └─────┬─────┘ │
                                         └────────┼───────┘
                                                  │ WebSocket (wss://)
                                                  │ Channel: private-doctor.{id}
                                                  │ Event: DoctorStatusChanged
                                                  │
                                         ┌────────┼───────┐
                                         │  ┌─────▼─────┐ │
                                         │  │ Flutter    │ │
                                         │  │ Listener   │ │
                                         │  │ (Echo/WS)  │ │
                                         │  └─────┬─────┘ │
                                         │        │        │
                                         │  ┌─────▼─────┐ │
                                         │  │ UI Update │ │
                                         │  │ setState  │ │
                                         │  │ / Bloc    │ │
                                         │  └───────────┘ │
                                         │  Flutter App    │
                                         └─────────────────┘
```

## Phase 1: Laravel Backend - WebSocket Setup

### 1.1 Install Laravel Reverb
```bash
composer require laravel/reverb
php artisan reverb:install
php artisan install:broadcasting
```

### 1.2 Configuration
`.env` additions:
```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=925718
REVERB_APP_KEY=local_key
REVERB_APP_SECRET=local_secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

`config/broadcasting.php` - ensure `reverb` connection is configured.

### 1.3 Create Broadcast Event
Create `app/Events/DoctorStatusChanged.php`:
- implements `ShouldBroadcast`
- properties: `$userId`, `$isActive`, `$doctorName`
- broadcast on `private-doctor.{userId}` channel
- broadcast name: `DoctorStatusChanged`

### 1.4 Modify toggleUserStatus to fire event
In `AdminDashboardController@toggleUserStatus`:
```php
event(new DoctorStatusChanged($user->id, $user->isActive, $user->name));
```
After `$user->update(['isActive' => !$user->isActive])`.

### 1.5 Channel Authorization (for Sanctum auth)
In `routes/channels.php`:
```php
Broadcast::channel('private-doctor.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
```

### 1.6 Also add API endpoint for mobile toggle (optional)
Since the Flutter app currently has no way to toggle isActive via API, add:
```php
// routes/api.php
Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
```

### 1.7 Run Reverb server
```bash
php artisan reverb:start
```
Add to `composer dev` script OR run separately.

---

## Phase 2: Flutter Frontend - WebSocket Client

### 2.1 Add dependencies to `pubspec.yaml`
```yaml
dependencies:
  web_socket_channel: ^3.0.1    # raw WebSocket client (simple option)
  # OR
  laravel_echo: ^1.2.0          # Laravel Echo for Flutter (recommended)
  pusher_channels_flutter: ^2.2.1  # required by laravel_echo for Reverb
```

### 2.2 Options for Flutter WebSocket

**Option A: laravel_echo (Recommended)**
- Full Laravel Echo support with channel auth
- Automatic reconnection
- Handles private channel subscription

```dart
import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

final echo = Echo(
  broadcaster: Echo.reverb,
  client: PusherChannelsFlutter(
    apiKey: 'local_key',
    cluster: '',
    host: 'YOUR_SERVER_IP',
    port: 8080,
    scheme: 'http',
    useTLS: false,
  ),
  authEndpoint: 'https://YOUR_SERVER/api/broadcasting/auth',
  bearerToken: 'YOUR_SANCTUM_TOKEN',
);

// Listen to private channel
echo.private('private-doctor.$userId')
    .listen('DoctorStatusChanged', (data) {
      // data = {userId, isActive, doctorName}
      setState(() {
        _isActive = data['isActive'];
      });
    });
```

**Option B: Raw web_socket_channel (Simpler, no auth)**
```dart
import 'dart:convert';
import 'package:web_socket_channel/web_socket_channel.dart';

final channel = WebSocketChannel.connect(
  Uri.parse('ws://YOUR_SERVER_IP:8080/app/local_key'),
);

channel.stream.listen((message) {
  final data = jsonDecode(message);
  if (data['event'] == 'DoctorStatusChanged') {
    // update UI
  }
});
```
But this requires manual channel subscription and auth handling.

### 2.3 State Management Integration
Choose one:
- **setState** (simple, for small apps)
- **Provider** (medium complexity)
- **Bloc/Cubit** (recommended for production)

```dart
// Example with Cubit
class DoctorStatusCubit extends Cubit<DoctorStatusState> {
  final Echo echo; // injected

  DoctorStatusCubit(this.echo) : super(DoctorStatusInitial());

  void listenToStatus(String userId) {
    echo.private('private-doctor.$userId')
        .listen('DoctorStatusChanged', (data) {
          emit(DoctorStatusChanged(
            isActive: data['isActive'],
            doctorName: data['doctorName'],
          ));
        });
  }
}
```

### 2.4 Reconnection Strategy
Flutter side MUST handle:
- App goes to background → WebSocket may disconnect → reconnect on resume
- Network loss → auto-reconnect with exponential backoff
- Token expiry → re-auth before reconnect

`laravel_echo` handles most of this automatically with `pusher_channels_flutter`.

### 2.5 Lifecycle Management
```dart
class AppLifecycleObserver extends WidgetsBindingObserver {
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      echo.connect();
    } else if (state == AppLifecycleState.paused) {
      echo.disconnect();
    }
  }
}
```

---

## Phase 3: Testing & Validation

### 3.1 Backend Tests
- Test that `toggleUserStatus` fires `DoctorStatusChanged` event
- Test channel authorization (only same user can subscribe)
- Test event payload correctness

### 3.2 Flutter Tests
- Mock WebSocket and test that UI updates on event
- Test reconnection logic
- Test that wrong user cannot subscribe to another's channel

### 3.3 Manual Testing
1. Open admin dashboard → toggle doctor active/inactive
2. Observe Flutter app immediately reflects the change
3. Kill Flutter app, reopen → WebSocket reconnects
4. Toggle while app is in background → change reflected on resume

---

## Alternative: Server-Sent Events (SSE)
If WebSocket is too complex for the current scale, consider SSE:

**Pros:**
- No Reverb server needed
- Simpler Laravel setup (just a streaming response)
- Works through HTTP (no port 8080)
- Flutter `EventSource` supports it

**Cons:**
- One-way only (server → client)
- Less efficient than WebSocket for bidirectional needs

---

## File Checklist (ملخص الملفات المطلوب إنشاؤها/تعديلها)

### Laravel Backend
| File | Action |
|------|--------|
| `composer.json` | add `laravel/reverb` |
| `.env` | add Reverb vars |
| `config/broadcasting.php` | ensure reverb connection |
| `app/Events/DoctorStatusChanged.php` | **CREATE** - broadcast event |
| `app/Http/Controllers/AdminDashboardController.php` | **EDIT** - fire event in `toggleUserStatus` |
| `routes/channels.php` | **EDIT** - add private channel auth rule |
| `routes/api.php` | **EDIT** - add API toggle endpoint (optional) |
| `config/reverb.php` | auto-generated, review |
| `config/queue.php` | ensure `sync` driver for dev (broadcast is sync) |

### Flutter App
| File | Action |
|------|--------|
| `pubspec.yaml` | add `laravel_echo` + `pusher_channels_flutter` |
| `lib/services/websocket_service.dart` | **CREATE** - WebSocket connection manager |
| `lib/cubits/doctor_status_cubit.dart` | **CREATE** - state management for isActive |
| `lib/screens/doctor_profile_screen.dart` | **EDIT** - listen to real-time updates |
| `lib/main.dart` | **EDIT** - initialize Echo, lifecycle observer |

---

## Security Notes
- WebSocket Auth: Sanctum token must be sent as Bearer in auth endpoint
- Channel Authorization: Verify in `channels.php` that user can only subscribe to their own channel
- HTTPS/WSS: In production, use TLS for WebSocket
- CORS: Ensure `config/cors.php` allows WebSocket connections from Flutter
- Rate Limiting: Consider rate limiting on broadcast auth endpoint

---

## Decision Points (نقاط تحتاج قرار)
1. **Reverb vs Pusher**: Reverb (self-hosted, free) recommended for this project
2. **laravel_echo vs raw WebSocket**: laravel_echo recommended (easier channel auth, reconnection)
3. **State management in Flutter**: depends on existing Flutter architecture (Provider/Bloc/Riverpod)
4. **API toggle endpoint needed?**: YES - Flutter needs a way to call toggle via API not just observe
