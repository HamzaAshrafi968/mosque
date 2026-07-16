import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'reverb_websocket_service.dart';
import 'doctor_status_cubit.dart';

/// Usage example: How to integrate WebSocket + Cubit in the Flutter app.
///
/// 1. Initialize ReverbWebSocketService in your main() or a service locator:
///
/// ```dart
/// final wsService = ReverbWebSocketService(
///   reverbHost: '192.168.1.100',   // Your server IP
///   reverbPort: 8080,
///   scheme: 'http',                 // 'wss' for production
///   appKey: 'local_key',            // REVERB_APP_KEY
///   sanctumToken: userToken,        // From login response
///   baseApiUrl: 'http://192.168.1.100/api',
/// );
/// await wsService.connect();
/// ```
///
/// 2. Wrap your MaterialApp with BlocProvider:
///
/// ```dart
/// BlocProvider<DoctorStatusCubit>(
///   create: (_) => DoctorStatusCubit(wsService),
///   child: MaterialApp(...)
/// )
/// ```
///
/// 3. In a screen/widget, listen to status changes:
///
/// ```dart
/// @override
/// void initState() {
///   super.initState();
///   context.read<DoctorStatusCubit>().listenToDoctor(currentUserId);
/// }
/// ```
///
/// 4. UI reacts automatically:
///
/// ```dart
/// BlocBuilder<DoctorStatusCubit, DoctorStatusState>(
///   builder: (context, state) {
///     if (state is DoctorStatusUpdated) {
///       return Text(state.isActive ? 'Active' : 'Inactive');
///     }
///     return CircularProgressIndicator();
///   },
/// )
/// ```

class DoctorStatusListenerWidget extends StatelessWidget {
  final String userId;
  final Widget child;

  const DoctorStatusListenerWidget({
    super.key,
    required this.userId,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return BlocListener<DoctorStatusCubit, DoctorStatusState>(
      listener: (context, state) {
        if (state is DoctorStatusUpdated) {
          if (!state.isActive) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.red,
                duration: const Duration(seconds: 5),
              ),
            );
            // Optional: Navigate to login screen
            // Navigator.pushReplacementNamed(context, '/login');
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.green,
              ),
            );
          }
        }
      },
      child: child,
    );
  }
}

/// App lifecycle observer to handle WebSocket on app background/foreground.
class ReverbLifecycleObserver extends WidgetsBindingObserver {
  final ReverbWebSocketService wsService;

  ReverbLifecycleObserver(this.wsService);

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        wsService.connect();
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.inactive:
        wsService.disconnect();
        break;
      default:
        break;
    }
  }
}
