<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HA WebSocket Events</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
        }

        h1 {
            margin-bottom: 20px;
            color: #00ff00;
        }

        #status {
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #00ff00;
            border-radius: 4px;
            background: #0a0a0a;
        }

        #status.connected {
            color: #00ff00;
        }

        #status.disconnected {
            color: #ff0000;
        }

        #events {
            background: #0a0a0a;
            border: 1px solid #00ff00;
            border-radius: 4px;
            padding: 15px;
            height: calc(100vh - 150px);
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.6;
        }

        .event {
            margin-bottom: 15px;
            padding: 10px;
            border-left: 3px solid #00ff00;
            background: #111;
        }

        .event .time {
            color: #888;
            font-size: 12px;
        }

        .event .entity {
            color: #00ccff;
            font-weight: bold;
        }

        .event .state {
            color: #ffaa00;
        }

        .event .old {
            color: #ff6666;
        }

        .event .new {
            color: #66ff66;
        }

        .controls {
            margin-bottom: 20px;
        }

        button {
            padding: 10px 20px;
            background: #00ff00;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            margin-right: 10px;
        }

        button:hover {
            background: #00cc00;
        }

        button:disabled {
            background: #555;
            color: #888;
            cursor: not-allowed;
        }

        #filter {
            padding: 8px;
            background: #0a0a0a;
            color: #00ff00;
            border: 1px solid #00ff00;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            width: 300px;
        }
    </style>
</head>

<body>
    <h1>🔴 Home Assistant WebSocket Events</h1>

    <div id="status" class="disconnected">⏺ Connecting...</div>

    <div class="controls">
        <button id="clearBtn">Clear Events</button>
        <input type="text" id="filter" placeholder="Filter (z.B. light.*, sensor.*)">
        <span style="color: #888; margin-left: 10px;">Events: <span id="eventCount">0</span></span>
    </div>

    <div id="events"></div>

    <script>
        const eventsContainer = document.getElementById('events');
        const statusDiv = document.getElementById('status');
        const clearBtn = document.getElementById('clearBtn');
        const filterInput = document.getElementById('filter');
        const eventCountSpan = document.getElementById('eventCount');
        let eventCount = 0;
        let filterPattern = null;

        // Event Source für Server-Sent Events
        const eventSource = new EventSource('/homeassistant/websocket/stream');

        eventSource.onopen = function() {
            statusDiv.textContent = '🟢 Connected';
            statusDiv.className = 'connected';
        };

        eventSource.onerror = function() {
            statusDiv.textContent = '🔴 Disconnected';
            statusDiv.className = 'disconnected';
        };

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                // Filter anwenden
                if (filterPattern && !matchesFilter(data.entity_id, filterPattern)) {
                    return;
                }

                addEvent(data);
            } catch (e) {
                console.error('Parse error:', e);
            }
        };

        function addEvent(data) {
            const eventDiv = document.createElement('div');
            eventDiv.className = 'event';

            const time = new Date().toLocaleTimeString();
            const oldState = data.old_state?.state || 'unknown';
            const newState = data.new_state?.state || 'unknown';

            eventDiv.innerHTML = `
                <div class="time">[${time}]</div>
                <div class="entity">${data.entity_id}</div>
                <div class="state">
                    <span class="old">${oldState}</span> → <span class="new">${newState}</span>
                </div>
            `;

            eventsContainer.insertBefore(eventDiv, eventsContainer.firstChild);

            // Limit to 100 events
            while (eventsContainer.children.length > 100) {
                eventsContainer.removeChild(eventsContainer.lastChild);
            }

            eventCount++;
            eventCountSpan.textContent = eventCount;
        }

        clearBtn.addEventListener('click', function() {
            eventsContainer.innerHTML = '';
            eventCount = 0;
            eventCountSpan.textContent = '0';
        });

        filterInput.addEventListener('input', function() {
            const value = this.value.trim();
            filterPattern = value ? value : null;
        });

        function matchesFilter(entityId, filter) {
            const pattern = filter.replace(/\*/g, '.*');
            const regex = new RegExp('^' + pattern + '$');
            return regex.test(entityId);
        }
    </script>
</body>

</html>
