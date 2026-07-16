import 'package:flutter_bloc/flutter_bloc.dart';
import 'reverb_websocket_service.dart';

// ─── States ───────────────────────────────────────────

abstract class DoctorStatusState {}

class DoctorStatusInitial extends DoctorStatusState {}

class DoctorStatusUpdated extends DoctorStatusState {
  final String userId;
  final bool isActive;
  final String doctorName;
  final String message;

  DoctorStatusUpdated({
    required this.userId,
    required this.isActive,
    required this.doctorName,
    required this.message,
  });
}

class DoctorStatusOffline extends DoctorStatusState {}

class DoctorStatusError extends DoctorStatusState {
  final String error;
  DoctorStatusError(this.error);
}

// ─── Cubit ────────────────────────────────────────────

class DoctorStatusCubit extends Cubit<DoctorStatusState> {
  final ReverbWebSocketService? _wsService;

  DoctorStatusCubit(this._wsService) : super(DoctorStatusInitial());

  /// Start listening to doctor status changes for a specific doctor.
  /// Typically called with the currently logged-in doctor's ID.
  void listenToDoctor(String userId) {
    if (_wsService == null) return;

    final channelName = 'private-doctor.$userId';

    _wsService!.subscribe(channelName);

    _wsService!.events.listen((event) {
      final eventName = event['event'] as String?;
      final channel = event['channel'] as String?;

      if (channel == channelName &&
          eventName == 'doctor.status-changed') {
        // Data may be inside 'data' or at top level (depends on client)
        final data = event['data'] is String
            ? jsonDecode(event['data'] as String)
            : event['data'] ?? event;

        emit(DoctorStatusUpdated(
          userId: data['userId']?.toString() ?? userId,
          isActive: data['isActive'] == true,
          doctorName: data['doctorName']?.toString() ?? '',
          message: data['message']?.toString() ?? '',
        ));
      }
    }, onError: (error) {
      emit(DoctorStatusError(error.toString()));
    });
  }

  /// Stop listening for a specific doctor.
  void stopListening(String userId) {
    _wsService?.unsubscribe('private-doctor.$userId');
  }

  @override
  Future<void> close() {
    _wsService?.dispose();
    return super.close();
  }
}

// Need this import because event data might need parsing
import 'dart:convert';
