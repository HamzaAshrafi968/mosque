import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;

/// Manages WebSocket connection to Laravel Reverb server.
/// Handles auto-reconnection and channel subscriptions.
class ReverbWebSocketService {
  final String _reverbHost;
  final int _reverbPort;
  final String _scheme;
  final String _appKey;
  final String _sanctumToken;
  final String _baseApiUrl;

  Timer? _pingTimer;
  Timer? _reconnectTimer;
  int _reconnectAttempts = 0;
  static const int _maxReconnectDelay = 30; // seconds

  final StreamController<Map<String, dynamic>> _eventController =
      StreamController<Map<String, dynamic>>.broadcast();

  Stream<Map<String, dynamic>> get events => _eventController.stream;

  bool _isConnected = false;
  bool get isConnected => _isConnected;

  final Set<String> _subscribedChannels = {};

  ReverbWebSocketService({
    required String reverbHost,
    required int reverbPort,
    required String scheme,
    required String appKey,
    required String sanctumToken,
    required String baseApiUrl,
  })  : _reverbHost = reverbHost,
        _reverbPort = reverbPort,
        _scheme = scheme,
        _appKey = appKey,
        _sanctumToken = sanctumToken,
        _baseApiUrl = baseApiUrl;

  /// Authenticates with Laravel broadcasting endpoint and
  /// returns the auth signature for Pusher/Reverb protocol.
  Future<Map<String, String>> _authenticateChannel(
      String channelName) async {
    final uri = Uri.parse('$_baseApiUrl/broadcasting/auth');
    try {
      final response = await http.post(
        uri,
        headers: {
          'Authorization': 'Bearer $_sanctumToken',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'channel_name': channelName,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return {
          'auth': data['auth'] as String,
          'channel_data': data['channel_data'] as String? ?? '',
        };
      } else {
        throw Exception(
            'Channel auth failed: ${response.statusCode} ${response.body}');
      }
    } catch (e) {
      rethrow;
    }
  }

  /// Subscribes to a private channel.
  /// Call this for each channel you want to listen to.
  Future<void> subscribe(String channelName) async {
    if (_subscribedChannels.contains(channelName)) return;

    final auth = await _authenticateChannel(channelName);
    _subscribedChannels.add(channelName);

    // Send Pusher subscribe message to Reverb
    _sendMessage({
      'event': 'pusher:subscribe',
      'data': {
        'channel': channelName,
        'auth': auth['auth'],
        'channel_data': auth['channel_data'],
      },
    });
  }

  /// Unsubscribes from a channel.
  Future<void> unsubscribe(String channelName) async {
    _subscribedChannels.remove(channelName);
    _sendMessage({
      'event': 'pusher:unsubscribe',
      'data': {
        'channel': channelName,
      },
    });
  }

  void _sendMessage(Map<String, dynamic> message) {
    if (_socket != null && _isConnected) {
      _socket?.add(jsonEncode(message));
    }
  }

  WebSocket? _socket;

  /// Connects to the Reverb WebSocket server and starts listening.
  Future<void> connect() async {
    if (_isConnected) return;

    final uri =
        Uri.parse('$_scheme://$_reverbHost:$_reverbPort/app/$_appKey');
    try {
      _socket = await WebSocket.connect(uri.toString());

      _socket!.listen(
        (data) {
          _handleMessage(data as String);
        },
        onDone: () {
          _onDisconnected();
        },
        onError: (error) {
          _onDisconnected();
        },
      );

      _isConnected = true;
      _reconnectAttempts = 0;
      _startPing();
    } catch (e) {
      _scheduleReconnect();
    }
  }

  void _handleMessage(String raw) {
    try {
      final message = jsonDecode(raw) as Map<String, dynamic>;
      final event = message['event'] as String?;

      if (event == 'pusher:connection_established') {
        final data = jsonDecode(message['data'] as String);
        // Resubscribe to previously subscribed channels
        for (final channel in _subscribedChannels.toList()) {
          subscribe(channel);
        }
        return;
      }

      if (event == 'pusher:pong') return;

      // Forward app events to stream
      _eventController.add(message);
    } catch (_) {
      // Ignore malformed messages
    }
  }

  void _onDisconnected() {
    _isConnected = false;
    _socket = null;
    _stopPing();
    _scheduleReconnect();
  }

  void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    final delay =
        (_reconnectAttempts < 5 ? _reconnectAttempts + 1 : _maxReconnectDelay)
            .toDouble();
    _reconnectTimer = Timer(Duration(seconds: delay.toInt()), () {
      _reconnectAttempts++;
      connect();
    });
  }

  void _startPing() {
    _pingTimer?.cancel();
    _pingTimer = Timer.periodic(const Duration(seconds: 60), (_) {
      _sendMessage({'event': 'pusher:ping', 'data': {}});
    });
  }

  void _stopPing() {
    _pingTimer?.cancel();
    _pingTimer = null;
  }

  /// Disconnects WebSocket. Call on app pause/close.
  Future<void> disconnect() async {
    _reconnectTimer?.cancel();
    _stopPing();
    _subscribedChannels.clear();
    _isConnected = false;
    await _socket?.close();
    _socket = null;
  }

  void dispose() {
    disconnect();
    _eventController.close();
  }
}
