<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\Request;
use Webklex\IMAP\Facades\Client;
use App\Models\WegDocAttachment; // neues Model für die Tabelle
use Carbon\Carbon;

class EmailController extends Controller
{
    public function importEmailsFromImap()
    {
        $client = Client::account('default'); // account aus config/imap.php
        $client->connect();

        // INBOX abrufen
        $folder = $client->getFolder('INBOX');

        // E-Mails von bahaimmo.de abrufen
        $messages = $folder->messages()->from('bahaimmo.de')->get();

        foreach ($messages as $message) {
            $from = $message->getFrom()[0]->mail ?? 'unknown@example.com';
            $subject = $message->getSubject() ?? '(kein Betreff)';

            $receivedAt = Carbon::parse($message->getDate()->toString())->format('Y-m-d H:i:s');
            $uniqueId = $message->getUid();
            $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';

            // E-Mail speichern
            $email = Email::firstOrCreate(
                ['unique_id' => $uniqueId],
                [
                    'from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                    'received_at' => $receivedAt,
                ]
            );

            // Attachments speichern
            foreach ($message->getAttachments() as $attachment) {
                $parent = Email::where('unique_id','0',$uniqueId);
                if ($parent!=null) {
                    echo "try to insert unique ID ". $uniqueId."<br>";
                    #dump($attachment);
                    $mime = $attachment->getContentType() ?: $attachment->getMimeType() ?: 'application/octet-stream';
                    WegDocAttachment::firstOrCreate(
                        [
                            'message_id' => $email->id,
                            'filename' => $attachment->name,
                            'content_type' => $mime,
                            'content' => base64_encode($attachment->content), // safe als base64
                            'size' => $attachment->size,
                        ]
                    );
                }
                else {
                    echo "parent with  unique ID ". $uniqueId." not found!<br>";
                }
            }
        }

        return count($messages) . " E-Mails mit Attachments importiert.";
    }

    public function showMails()
    {
        return view('weg.email');
    }
}
