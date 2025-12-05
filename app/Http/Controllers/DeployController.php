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
                'message' => 'Unauthorized',
                'debug' => [
                    'auth_check' => auth()->check(),
                    'has_token' => !empty($request->header('X-Deploy-Token')),
                    'user' => auth()->user()?->name ?? 'not logged in'
                ]
            ], 403);
        }

        // Debug-Informationen sammeln
        $debugInfo = [
            'user' => auth()->user()?->name ?? 'guest',
            'ip' => $request->ip(),
            'method' => $request->method(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        try {
            Log::info('Deploy triggered', $debugInfo);

            // Welche Methode wird verwendet?
            $method = $this->detectDeployMethod();
            $debugInfo['deploy_method'] = $method;

            Log::info('Using deploy method: ' . $method);

            // SSH-Methode (wenn SSH-Key vorhanden)
            // $result = $this->deployViaSsh();

            // Flag-File-Methode (einfacher, sicherer)
            $result = $this->deployViaFlagFile();

            return response()->json([
                'success' => true,
                'message' => 'Deploy wurde ausgelöst',
                'output' => $result,
                'debug' => $debugInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Deploy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'debug' => $debugInfo
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deploy fehlgeschlagen',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'debug' => $debugInfo,
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for full trace'
            ], 500);
        }
    }

    /**
     * Erkennt welche Deploy-Methode verfügbar ist
     */
    private function detectDeployMethod(): string
    {
        // Umgebungs-abhängige Pfade
        $flagPath = $this->getDeployFlagPath();
        $scriptPath = $this->getDeployScriptPath();
        $sshKeyPath = $this->getSshKeyPath();

        $info = [
            'os' => PHP_OS,
            'flag_path' => $flagPath,
            'script_path' => $scriptPath,
            'flag_dir_exists' => is_dir(dirname($flagPath)),
            'flag_dir_writable' => is_writable(dirname($flagPath)),
            'script_exists' => file_exists($scriptPath),
            'script_executable' => is_executable($scriptPath),
            'ssh_key_exists' => file_exists($sshKeyPath),
        ];

        Log::info('Deploy method detection', $info);

        if ($info['flag_dir_writable']) {
            return 'flag-file';
        } elseif ($info['ssh_key_exists']) {
            return 'ssh';
        } elseif ($info['script_executable']) {
            return 'direct';
        }

        throw new \Exception('Keine Deploy-Methode verfügbar. Info: ' . json_encode($info));
    }

    /**
     * Gibt den Pfad zur Deploy-Flag-Datei zurück
     */
    private function getDeployFlagPath(): string
    {
        // Lokales Entwicklungs-Override (Windows-Check)
        if (PHP_OS_FAMILY === 'Windows') {
            return storage_path('app/deploy.flag');
        }

        return env('DEPLOY_FLAG_PATH', '/homeassistant/laravel/deploy.flag');
    }

    /**
     * Gibt den Pfad zum Deploy-Script zurück
     */
    private function getDeployScriptPath(): string
    {
        // Lokales Entwicklungs-Override (Windows-Check)
        if (PHP_OS_FAMILY === 'Windows') {
            return base_path('deploy-local.bat'); // oder .sh wenn Git Bash
        }

        return env('DEPLOY_SCRIPT_PATH', '/homeassistant/laravel/deploy.sh');
    }

    /**
     * Gibt den Pfad zum SSH-Key zurück
     */
    private function getSshKeyPath(): string
    {
        return env('DEPLOY_SSH_KEY_PATH', '/root/.ssh/id_rsa');
    }

    /**
     * Deploy via Flag-File (empfohlen für Docker)
     */
    private function deployViaFlagFile(): string
    {
        $flagFile = $this->getDeployFlagPath();

        // Verzeichnis erstellen falls es nicht existiert
        $dir = dirname($flagFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception("Konnte Verzeichnis nicht erstellen: $dir");
            }
        }

        // Flag-File erstellen
        if (file_put_contents($flagFile, date('Y-m-d H:i:s') . "\n" . auth()->user()?->name ?? 'system') === false) {
            throw new \Exception('Konnte Flag-File nicht erstellen: ' . $flagFile);
        }

        return 'Deploy-Flag wurde gesetzt. Watcher wird das Script ausführen.';
    }

    /**
     * Deploy via SSH (wenn SSH konfiguriert ist)
     */
    private function deployViaSsh(): string
    {
        $sshKey = $this->getSshKeyPath();
        $scriptPath = $this->getDeployScriptPath();
        $sshHost = env('DEPLOY_SSH_HOST', 'root@172.17.0.1');

        $process = new Process([
            'ssh',
            '-i', $sshKey,
            '-o', 'StrictHostKeyChecking=no',
            $sshHost,
            $scriptPath
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
        $scriptPath = $this->getDeployScriptPath();

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
