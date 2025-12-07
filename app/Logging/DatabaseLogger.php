<?php
namespace App\Logging;

use Illuminate\Support\Facades\DB;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class DatabaseLogger
{
    public function __invoke(array $config)
    {
        // Erstelle einen Monolog-Logger
        $logger = new MonologLogger('database');

        // Optional: Logs auch in eine Datei speichern, um zu debuggen
        $logger->pushHandler(new StreamHandler(storage_path('logs/database.log')));

        // Füge einen Prozessor hinzu, um Logs in die Datenbank zu schreiben
        $logger->pushProcessor(function ($record) {
        
            $type = $record['context']['type'] ?? 'default'; // 'default' als Fallback
            DB::table('logs')->insert([
                'level' => $record['level_name'],
                'type' => $type,
                'message' => $record['message'],
                'context' => json_encode($record['context']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $record;
        });

        return $logger;
    }
}
