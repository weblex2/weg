<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use React\Socket\Connector;
use React\Promise\Deferred;

class HomeAssistantWebSocket
{
    protected $wsUrl;
    protected $token;
    protected $connection;
    protected $messageId = 1;
    protected $onStateChange;
    protected $buffer = '';

    public function __construct()
    {
        $haUrl = config('homeassistant.url');
        $this->wsUrl = str_replace(['http://', 'https://'], ['tcp://', 'tls://'], $haUrl) . ':8123/api/websocket';
        $this->token = config('homeassistant.token');
    }

    /**
     * Starte den WebSocket Listener
     */
    public function listen(callable $onStateChange)
    {
        $this->onStateChange = $onStateChange;

        $loop = Loop::get();
        $connector = new Connector($loop);

        // Parse URL
        $parsedUrl = parse_url(config('homeassistant.url'));
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? 8123;

        $connector->connect("tcp://{$host}:{$port}")->then(
            function ($stream) {
                $this->connection = $stream;
                Log::info('Home Assistant WebSocket verbunden');

                // Send HTTP Upgrade Request
                $request = "GET /api/websocket HTTP/1.1\r\n";
                $request .= "Host: " . parse_url(config('homeassistant.url'), PHP_URL_HOST) . "\r\n";
                $request .= "Upgrade: websocket\r\n";
                $request .= "Connection: Upgrade\r\n";
                $request .= "Sec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\n";
                $request .= "Sec-WebSocket-Version: 13\r\n";
                $request .= "\r\n";

                $stream->write($request);

                $stream->on('data', function ($data) {
                    $this->handleData($data);
                });

                $stream->on('close', function () {
                    Log::warning('Home Assistant WebSocket geschlossen');
                    sleep(5);
                    $this->listen($this->onStateChange);
                });

                $stream->on('error', function ($e) {
                    Log::error('Home Assistant WebSocket Fehler: ' . $e->getMessage());
                });
            },
            function ($e) {
                Log::error('WebSocket Verbindungsfehler: ' . $e->getMessage());
                sleep(10);
                $this->listen($this->onStateChange);
            }
        );

        $loop->run();
    }

    protected function handleData($data)
    {
        $this->buffer .= $data;

        // Parse WebSocket frames
        while (strlen($this->buffer) > 2) {
            $firstByte = ord($this->buffer[0]);
            $secondByte = ord($this->buffer[1]);

            $opcode = $firstByte & 0x0F;
            $masked = ($secondByte & 0x80) !== 0;
            $payloadLength = $secondByte & 0x7F;

            $headerLength = 2;

            if ($payloadLength === 126) {
                if (strlen($this->buffer) < 4) return;
                $payloadLength = unpack('n', substr($this->buffer, 2, 2))[1];
                $headerLength = 4;
            } elseif ($payloadLength === 127) {
                if (strlen($this->buffer) < 10) return;
                $payloadLength = unpack('J', substr($this->buffer, 2, 8))[1];
                $headerLength = 10;
            }

            if ($masked) {
                $headerLength += 4;
            }

            if (strlen($this->buffer) < $headerLength + $payloadLength) {
                return;
            }

            $payload = substr($this->buffer, $headerLength, $payloadLength);
            $this->buffer = substr($this->buffer, $headerLength + $payloadLength);

            if ($opcode === 0x01) { // Text frame
                $this->handleMessage($payload);
            }
        }
    }

    protected function handleMessage($msg)
    {
        $data = json_decode($msg, true);

        if (!$data) return;

        switch ($data['type'] ?? null) {
            case 'auth_required':
                $this->authenticate();
                break;
            case 'auth_ok':
                Log::info('Home Assistant WebSocket Authentifizierung erfolgreich');
                $this->subscribeToStateChanges();
                break;
            case 'auth_invalid':
                Log::error('Home Assistant WebSocket Authentifizierung fehlgeschlagen');
                break;
            case 'event':
                $this->handleEvent($data);
                break;
            case 'result':
                $this->handleResult($data);
                break;
        }
    }

    protected function authenticate()
    {
        $this->sendWebSocket([
            'type' => 'auth',
            'access_token' => $this->token
        ]);
    }

    protected function subscribeToStateChanges()
    {
        $this->sendWebSocket([
            'id' => $this->messageId++,
            'type' => 'subscribe_events',
            'event_type' => 'state_changed'
        ]);

        Log::info('Subscribed to Home Assistant state changes');
    }

    protected function handleEvent($data)
    {
        if (isset($data['event']['event_type']) && $data['event']['event_type'] === 'state_changed') {
            $eventData = $data['event']['data'];

            $entityId = $eventData['entity_id'];
            $oldState = $eventData['old_state'];
            $newState = $eventData['new_state'];

            if ($this->onStateChange) {
                call_user_func($this->onStateChange, [
                    'entity_id' => $entityId,
                    'old_state' => $oldState,
                    'new_state' => $newState
                ]);
            }
        }
    }

    protected function handleResult($data)
    {
        if (isset($data['success']) && !$data['success']) {
            Log::warning('Command failed', ['data' => $data]);
        }
    }

    protected function sendWebSocket(array $data)
    {
        if (!$this->connection) return;

        $payload = json_encode($data);
        $frame = chr(0x81); // FIN + Text frame

        $length = strlen($payload);
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        $frame .= $payload;
        $this->connection->write($frame);
    }

    public function close()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
