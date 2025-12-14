<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebSocketStreamController extends Controller
{
    /**
     * Zeige die Events View
     */
    public function index()
    {
        return view('homeassistant.websocket-events');
    }

    /**
     * Stream Events via Server-Sent Events (SSE)
     */
    public function stream(Request $request)
    {
        return response()->stream(function () {
            // Set headers for SSE
            echo "retry: 1000\n\n";

            $lastEventId = null;

            while (true) {
                // Check if client is still connected
                if (connection_aborted()) {
                    break;
                }

                // Get latest events from cache
                $events = Cache::get('ha_websocket_events', []);

                foreach ($events as $eventId => $event) {
                    // Skip already sent events
                    if ($lastEventId !== null && $eventId <= $lastEventId) {
                        continue;
                    }

                    // Send event
                    echo "data: " . json_encode($event) . "\n\n";
                    ob_flush();
                    flush();

                    $lastEventId = $eventId;
                }

                // Sleep for 100ms
                usleep(100000);
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
