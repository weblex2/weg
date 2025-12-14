<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class ArtisanController extends Controller
{
    public function run(Request $request, string $command)
    {
        // Nur erlaubte Commands (Whitelist für Sicherheit!)
        $allowedCommands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'migrate',
            'queue:restart',
            'queue:stop',
            // Füge hier nur sichere Commands hinzu!
        ];

        if (!in_array($command, $allowedCommands)) {
            return response()->json(['error' => 'Command nicht erlaubt'], 403);
        }

        // Optionale Parameter aus Request (z. B. für migrate --force)
        $options = $request->input('options', []);

        $output = new BufferedOutput();
        $exitCode = Artisan::call($command, $options, $output);

        return response()->json([
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => $output->fetch(),
        ]);
    }
}
