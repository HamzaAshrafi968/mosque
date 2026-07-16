# Flutter WebSocket Integration - Implementation Steps

## 1. Add dependencies to `pubspec.yaml`

```yaml
dependencies:
  web_socket_channel: ^3.0.1
  flutter_bloc: ^8.1.6     # Or use Provider/Riverpod
  http: ^1.2.2              # For channel auth HTTP call

dev_dependencies:
  bloc_test: ^9.1.7          # For testing cubits
  mockito: ^5.4.4            # For mocking in tests
```

## 2. Copy the Dart files from this folder into your Flutter app:

| File | Destination |
|------|-------------|
| `reverb_websocket_service.dart` | `lib/services/reverb_websocket_service.dart` |
| `doctor_status_cubit.dart` | `lib/cubits/doctor_status/doctor_status_cubit.dart` |
| `flutter_integration_example.dart` | Reference only - copy the code patterns |

## 3. Initialize in `main.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'services/reverb_websocket_service.dart';
import 'cubits/doctor_status/doctor_status_cubit.dart';

late ReverbWebSocketService wsService;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Get the Sanctum token (from SharedPreferences or secure storage)
  final token = await getStoredToken(); // Your implementation
  final userId = await getStoredUserId(); // Your implementation

  wsService = ReverbWebSocketService(
    reverbHost: 'YOUR_SERVER_IP',    // e.g., '192.168.1.100'
    reverbPort: 8080,
    scheme: 'http',                  // 'wss' in production
    appKey: 'local_key',
    sanctumToken: token,
    baseApiUrl: 'http://YOUR_SERVER_IP/api',
  );

  runApp(MyApp(userId: userId));
}

class MyApp extends StatefulWidget {
  final String userId;
  const MyApp({super.key, required this.userId});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> with WidgetsBindingObserver {
  final DoctorStatusCubit _statusCubit = DoctorStatusCubit(wsService);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Connect WebSocket
    wsService.connect();

    // Start listening to doctor status
    _statusCubit.listenToDoctor(widget.userId);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      wsService.connect();
    } else {
      wsService.disconnect();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _statusCubit.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocProvider<DoctorStatusCubit>.value(
      value: _statusCubit,
      child: MaterialApp(
        home: HomeScreen(),
        // ...
      ),
    );
  }
}
```

## 4. Use in any screen:

```dart
class DoctorProfileScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return BlocBuilder<DoctorStatusCubit, DoctorStatusState>(
      builder: (context, state) {
        return Column(
          children: [
            // Status badge
            Container(
              padding: EdgeInsets.all(12),
              color: _statusColor(state),
              child: Text(_statusText(state)),
            ),
            // ... rest of the UI
          ],
        );
      },
    );
  }

  Color _statusColor(DoctorStatusState state) {
    if (state is DoctorStatusUpdated) {
      return state.isActive ? Colors.green : Colors.red;
    }
    return Colors.grey;
  }

  String _statusText(DoctorStatusState state) {
    if (state is DoctorStatusUpdated) {
      return state.isActive ? 'Active' : 'Inactive';
    }
    if (state is DoctorStatusOffline) {
      return 'Offline (reconnecting...)';
    }
    return 'Connecting...';
  }
}
```

## 5. Testing (optional but recommended):

```dart
// test/cubits/doctor_status_cubit_test.dart
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('DoctorStatusCubit', () {
    late DoctorStatusCubit cubit;

    setUp(() {
      cubit = DoctorStatusCubit(null); // null service = test mode
    });

    tearDown(() => cubit.close());

    test('initial state is DoctorStatusInitial', () {
      expect(cubit.state, isA<DoctorStatusInitial>());
    });
  });
}
```

## 6. How to run the Reverb server

On your Laravel server (development):
```bash
php artisan reverb:start
```

Or add to `composer dev`:
```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#60a5fa\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" \"php artisan reverb:start\" --names=server,queue,logs,vite,reverb --kill-others"
]
```

## 7. Security checklist

- [ ] `.env`: Set `REVERB_SCHEME=https` in production
- [ ] `.env`: Use strong `REVERB_APP_KEY` and `REVERB_APP_SECRET` in production
- [ ] Flutter: Use `wss://` scheme in production
- [ ] Flutter: Store Sanctum token securely (flutter_secure_storage)
- [ ] Flutter: Unsubscribe channels on logout
- [ ] Laravel: Restrict `allowed_origins` in `config/reverb.php` for production

## 8. API Endpoint Reference

For admin apps or admin features in the Flutter app:

```
POST /api/users/{user}/toggle-active
Authorization: Bearer {sanctum_token}
Accept: application/json

Response:
{
    "success": true,
    "message": "تم تفعيل الحساب بنجاح",
    "isActive": true
}
```
