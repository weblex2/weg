<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Models\Attachment;
use App\Models\EmailAddress;
use Carbon\Carbon;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\DB;

class ImportStratoEmails extends Command
{
    protected $signature = 'app:import-strato-emails';
    protected $description = 'Importiert die letzten 5 Minuten E-Mails von Strato, nur wenn Absender/CC/BCC in email_addresses ist';

    public function handle()
    {
        $this->info('Starte Import von Strato E-Mails...');

        // Alle Regeln aus email_addresses laden
        $rules = EmailAddress::pluck('email')->toArray();

        // IMAP Client aus config/imap.php
        $client = Client::account('default');
        $client->connect();

        $folder = $client->getFolder('INBOX');

        // Letzte 5 Minuten
        $since = Carbon::now()->subMinutes(5)->format('d-M-Y H:i:s');
        $messages = $folder->query()->since($since)->get();

        $this->info('Gefundene E-Mails: ' . $messages->count());

        foreach ($messages as $message) {
            $from = $this->extractEmails($message->getFrom());
            $cc   = $this->extractEmails($message->getCc());
            $bcc  = $this->extractEmails($message->getBcc());

            // Prüfen, ob die E-Mail zu einer der Regeln passt
            if (!$this->matchesRules(array_merge($from, $cc, $bcc), $rules)) {
                $this->info('Übersprungen (kein Match): ' . substr($message->getSubject(),0,50) . ' | From: ' . implode(',', $from));
                continue;
            }

            // Prüfen, ob die Email schon existiert (Subject + From + Datum)
            $exists = Email::where('from_email', $from[0] ?? '')
                        ->where('subject', $message->getSubject())
                        ->where('received_at', Carbon::parse($message->getDate()))
                        ->exists();
            if ($exists) {
                $this->info('Übersprungen (bereits importiert): ' . $message->getSubject());
                continue;
            }

            // Transaction starten
            DB::transaction(function() use ($message, $from, $cc, $bcc) {
                // E-Mail erstellen
                $email = Email::create([
                    'subject'     => $message->getSubject(),
                    'from_email'  => $from[0] ?? '',
                    'from_name'   => $this->extractNames($message->getFrom())[0] ?? '',
                    'to_email'    => implode(',', $this->extractEmails($message->getTo())), // To wird gespeichert, aber nicht geprüft
                    'to_name'     => implode(',', $this->extractNames($message->getTo())),
                    'cc'          => implode(',', $cc),
                    'bcc'         => implode(',', $bcc),
                    'body_html'   => $message->getHTMLBody(),
                    'body_text'   => $message->getTextBody(),
                    'received_at' => Carbon::parse($message->getDate()),
                    'imported_via'=> 'imap',
                    'topics'      => [],
                ]);

                $this->info('E-Mail importiert: ' . $email->subject);

                // Attachments speichern
                foreach ($message->getAttachments() as $att) {
                    $filename = $att->getName();
                    $content = $att->getContent();

                    $folderPath = storage_path('app/email_attachments');
                    if (!file_exists($folderPath)) {
                        mkdir($folderPath, 0755, true);
                    }

                    $path = $folderPath . '/' . uniqid() . '_' . $filename;
                    file_put_contents($path, $content);

                    $storedFilename = uniqid() . '_' . $filename;
                    
                    // Datei speichern
                    file_put_contents($path, $content);

                    $email->attachments()->create([
                        'filename'          => $filename,             // Originalname
                        'original_filename' => $filename,
                        'stored_filename'   => $storedFilename,       // Eindeutiger Name
                        'path'              => $path,
                        'file_size'         => $att->getSize() ?? 0,  // Fallback auf 0
                        'storage_path'      => $path,
                        'mime_type'         => $att->getMime() ?? 'application/octet-stream', // Fallback
                    ]);


                    $this->info('Attachment gespeichert: ' . $filename);
                }
            }); // Ende Transaction
        }
    }

    /**
     * Prüft, ob eine E-Mail (from, cc, bcc) zu einer der Regeln passt.
     * Regeln können entweder vollständige Emails oder "*@domain" sein.
     */
    private function matchesRules(array $emails, array $rules): bool
    {
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') continue;

            if (str_starts_with($rule, '*@')) {
                $domain = substr($rule, 1); // "@domain.com"
                foreach ($emails as $email) {
                    if (str_ends_with(strtolower($email), strtolower($domain))) {
                        return true;
                    }
                }
            } else {
                foreach ($emails as $email) {
                    if (strtolower($email) === strtolower($rule)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function extractEmails(array|string|null $values): array {
        if ($values === null) return [];
        $values = is_array($values) ? $values : [$values];
        return array_map(fn($v) => is_object($v) ? ($v->mail ?? '') : $v, $values);
    }

    private function extractNames(array|string|null $values): array {
        if ($values === null) return [];
        $values = is_array($values) ? $values : [$values];
        return array_map(fn($v) => is_object($v) ? ($v->personal ?? '') : '', $values);
    }
}
