# WebSocket Flutter Integration Plan

## Current State (2026-07-12)

### Backend — Fully Configured
- `laravel/reverb` ^1.10 installed (composer)
- `BROADCAST_CONNECTION=reverb` active in `.env`
- All `REVERB_*` env vars set (app_id=925718, key=local_key, secret=local_secret, port=8080, scheme=http, host=0.0.0.0)
- `config/reverb.php` and `config/broadcasting.php` fully configured
- 2 broadcast events implementing `ShouldBroadcast`:
  - `App\Events\NewAppointmentBooked` — fires on pending appointment creation, channel `doctor.{doctor_id}`
  - `App\Events\DoctorStatusChanged` — fires on admin toggle, channel `doctor.{userId}`
- 2 private channels in `routes/channels.php`:
  - `doctor.{doctorId}` — doctor or admin + tenant match
  - `tenant.{tenantId}.appointments` — tenant match or admin
- No NPM packages (no Echo, no pusher-js)

### Reverb Server — NOT RUNNING
Port 8080 has no listener. Must be started manually:
```bash
php artisan reverb:start
```

---

## Flutter Settings & Code

### 1. Dependencies (`pubspec.yaml`)

```yaml
dependencies:
  laravel_echo: ^1.2.0
  pusher_channels_flutter: ^2.2.1
  flutter_bloc: ^8.1.6          # State management
  http: ^1.2.2                   # Broadcast auth HTTP calls
  flutter_secure_storage: ^9.2.4 # Token storage

dev_dependencies:
  bloc_test: ^9.1.7
  mockito: ^5.4.4
```

### 2. Environment Constants

Create `lib/config/reverb_config.dart`:

```dart
class ReverbConfig {
  // Change to your server's LAN IP for device testing
  static const String host = '192.168.1.100';

  static const int port = 8080;
  static const String scheme = 'http';        // 'https' in production
  static const bool useTLS = false;           // true in production
  static const String appKey = 'local_key';
  static const String appId = '925718';
  static const String appSecret = 'local_secret';

  // Channels
  static String doctorChannel(String userId) => 'doctor.$userId';
  static String tenantAppointmentsChannel(String tenantId) =>
      'tenant.$tenantId.appointments';

  // Events
  static const String doctorStatusChanged = '.DoctorStatusChanged';
  static const String newAppointment = '.appointment.created';
}
```

### 3. WebSocket Service

Create `lib/services/reverb_service.dart`:

```dart
import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class ReverbService {
  late final Echo echo;
  final String host;
  final int port;
  final String scheme;
  final String appKey;
  final String bearerToken;
  final String authEndpoint;

  ReverbService({
    required this.host,
    required this.port,
    required this.scheme,
    required this.appKey,
    required this.bearerToken,
    required this.authEndpoint,
  }) {
    echo = Echo(
      broadcaster: Echo.reverb,
      client: PusherChannelsFlutter(
        apiKey: appKey,
        cluster: '',
        host: host,
        port: port,
        scheme: scheme,
        useTLS: scheme == 'https',
      ),
      authEndpoint: authEndpoint,
      bearerToken: bearerToken,
    );
  }

  void listenDoctorStatus(String userId, Function(dynamic) callback) {
    echo.private('doctor.$userId').listen('.DoctorStatusChanged', callback);
  }

  void listenAppointments(String tenantId, Function(dynamic) callback) {
    echo
        .private('tenant.$tenantId.appointments')
        .listen('.appointment.created', callback);
  }

  void disconnect() => echo.disconnect();
}
```

### 4. State Management (Cubit)

Create `lib/cubits/doctor_status/doctor_status_cubit.dart`:

```dart
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../services/reverb_service.dart';

// States
abstract class DoctorStatusState {}

class DoctorStatusInitial extends DoctorStatusState {}

class DoctorStatusOnline extends DoctorStatusState {
  final bool isActive;
  DoctorStatusOnline(this.isActive);
}

class DoctorStatusOffline extends DoctorStatusState {}

// Cubit
class DoctorStatusCubit extends Cubit<DoctorStatusState> {
  final ReverbService reverbService;

  DoctorStatusCubit(this.reverbService) : super(DoctorStatusInitial());

  void listen(String userId) {
    reverbService.listenDoctorStatus(userId, (data) {
      emit(DoctorStatusOnline(data['isActive'] as bool));
    });
  }

  void setOffline() => emit(DoctorStatusOffline());
}
```

### 5. Lifecycle Management

In `lib/main.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'config/reverb_config.dart';
import 'services/reverb_service.dart';
import 'cubits/doctor_status/doctor_status_cubit.dart';

late ReverbService reverbService;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  const storage = FlutterSecureStorage();
  final token = await storage.read(key: 'sanctum_token') ?? '';
  final userId = await storage.read(key: 'user_id') ?? '';
  final tenantId = await storage.read(key: 'tenant_id') ?? '';

  reverbService = ReverbService(
    host: ReverbConfig.host,
    port: ReverbConfig.port,
    scheme: ReverbConfig.scheme,
    appKey: ReverbConfig.appKey,
    bearerToken: token,
    authEndpoint: '${ReverbConfig.scheme}://${ReverbConfig.host}/api/broadcasting/auth',
  );

  runApp(MyApp(userId: userId, tenantId: tenantId));
}

class MyApp extends StatefulWidget {
  final String userId;
  final String tenantId;
  const MyApp({super.key, required this.userId, required this.tenantId});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Echo auto-reconnects via pusher_channels_flutter
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    reverbService.disconnect();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: HomeScreen(userId: widget.userId, tenantId: widget.tenantId),
    );
  }
}
```

### 6. Usage in a Screen

```dart
class HomeScreen extends StatelessWidget {
  final String userId;
  final String tenantId;

  const HomeScreen({super.key, required this.userId, required this.tenantId});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) {
        final cubit = DoctorStatusCubit(reverbService);
        cubit.listen(userId);
        return cubit;
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Doctor Dashboard')),
        body: BlocBuilder<DoctorStatusCubit, DoctorStatusState>(
          builder: (context, state) {
            return switch (state) {
              DoctorStatusInitial() =>
                const Center(child: CircularProgressIndicator()),
              DoctorStatusOnline(:final isActive) =>
                Center(child: Text('Active: $isActive')),
              DoctorStatusOffline() =>
                const Center(child: Text('Offline - reconnecting...')),
            };
          },
        ),
      ),
    );
  }
}
```

### 7. Toggle Status via API (Admin)

For admin features in the Flutter app:

```dart
Future<void> toggleDoctorActive(String userId, String token) async {
  final response = await http.post(
    Uri.parse('http://${ReverbConfig.host}/api/users/$userId/toggle-active'),
    headers: {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
    },
  );
  // Response: { "success": true, "message": "...", "isActive": true }
}
```

---

## Running the Reverb Server

### Development

```bash
php artisan reverb:start
```

### Add to `composer dev`

```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#60a5fa\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" \"php artisan reverb:start\" --names=server,queue,logs,vite,reverb --kill-others"
]
```

---

## Server Environment Variables

```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=925718
REVERB_APP_KEY=local_key
REVERB_APP_SECRET=local_secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

---

## Security Checklist

- [ ] Production: `REVERB_SCHEME=https`, Flutter: `scheme: 'https'`, `useTLS: true`
- [ ] Production: strong `REVERB_APP_KEY` and `REVERB_APP_SECRET`
- [ ] Store Sanctum token in `flutter_secure_storage`
- [ ] Unsubscribe channels on logout
- [ ] Restrict `allowed_origins` in `config/reverb.php` for production
- [ ] Channel auth in `routes/channels.php` verifies tenant + user identity

---

## Files Checklist

| File | Action |
|------|--------|
| `lib/config/reverb_config.dart` | CREATE — connection constants |
| `lib/services/reverb_service.dart` | CREATE — WebSocket manager |
| `lib/cubits/doctor_status/doctor_status_cubit.dart` | CREATE — state management |
| `lib/cubits/doctor_status/doctor_status_state.dart` | CREATE — state classes |
| `lib/main.dart` | EDIT — initialize Reverb, lifecycle observer |
| `pubspec.yaml` | EDIT — add `laravel_echo`, `pusher_channels_flutter`, `flutter_bloc` |
