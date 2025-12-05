<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DeployController extends Controller
{
    /**
     * Deploy-Script auf dem Host ausführen
     */
    public function deploy(Request $request)
    {
        // Optional: Token-basierte Authentifizierung
        if (!$this->isAuthorized($request)) {
            Log::warning('Unauthorized deploy attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            Log::info('Deploy triggered', [
                'user' => auth()->user()?->name ?? 'guest',
                'ip' => $request->ip()
            ]);

            // SSH-Methode (wenn SSH-Key vorhanden)
            // $result = $this->deployViaSsh();

            // Flag-File-Methode (einfacher, sicherer)
            $result = $this->deployViaFlagFile();

            return response()->json([
                'success' => true,
                'message' => 'Deploy wurde ausgelöst',
                'output' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Deploy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deploy fehlgeschlagen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deploy via Flag-File (empfohlen für Docker)
     */
    private function deployViaFlagFile(): string
    {
        $flagFile = '/homeassistant/laravel/deploy.flag';

        // Flag-File erstellen
        if (file_put_contents($flagFile, date('Y-m-d H:i:s') . "\n" . auth()->user()?->name ?? 'system') === false) {
            throw new \Exception('Konnte Flag-File nicht erstellen');
        }

        return 'Deploy-Flag wurde gesetzt. Watcher wird das Script ausführen.';
    }

    /**
     * Deploy via SSH (wenn SSH konfiguriert ist)
     */
    private function deployViaSsh(): string
    {
        $process = new Process([
            'ssh',
            '-i', '/root/.ssh/id_rsa',
            '-o', 'StrictHostKeyChecking=no',
            'root@172.17.0.1', // Docker Host IP
            '/homeassistant/laravel/deploy.sh'
        ]);

        $process->setTimeout(300); // 5 Minuten Timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Deploy via direkter Ausführung (wenn Script gemountet ist)
     */
    private function deployViaDirect(): string
    {
        $scriptPath = '/homeassistant/laravel/deploy.sh';

        if (!file_exists($scriptPath)) {
            throw new \Exception('Deploy-Script nicht gefunden: ' . $scriptPath);
        }

        if (!is_executable($scriptPath)) {
            throw new \Exception('Deploy-Script ist nicht ausführbar');
        }

        $process = new Process(['bash', $scriptPath]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Prüft ob der Request autorisiert ist
     */
    private function isAuthorized(Request $request): bool
    {
        // Option 1: Nur für eingeloggte User
        if (auth()->check()) {
            return true;
        }

        // Option 2: Mit Deploy-Token (in .env: DEPLOY_TOKEN=dein-geheimer-token)
        $token = $request->header('X-Deploy-Token') ?? $request->input('token');
        if ($token && $token === config('app.deploy_token')) {
            return true;
        }

        return false;
    }

    /**
     * Status des letzten Deploys abrufen
     */
    public function status()
    {
        $logFile = '/homeassistant/laravel/deploy.log';

        if (!file_exists($logFile)) {
            return response()->json([
                'status' => 'unknown',
                'message' => 'Keine Deploy-Logs gefunden'
            ]);
        }

        $lastLines = $this->getLastLines($logFile, 20);

        return response()->json([
            'status' => 'success',
            'last_log' => $lastLines,
            'last_modified' => date('Y-m-d H:i:s', filemtime($logFile))
        ]);
    }

    /**
     * Letzte N Zeilen einer Datei lesen
     */
    private function getLastLines(string $file, int $lines = 20): string
    {
        $process = new Process(['tail', '-n', (string)$lines, $file]);
        $process->run();
        return $process->getOutput();
    }
}
