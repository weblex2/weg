<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpMimeMailParser\Parser;
use Illuminate\Support\Str;
use App\Services\MsgToEmlConverter;

class EmailController extends Controller
{
    /**
     * Zeige alle Emails mit Suchfunktion
     */
    public function index(Request $request)
    {
        $query = Email::query();

        // Scout-Suche
        if ($request->filled('search')) {
            $searchIds = Email::search($request->search)->keys(); // IDs der Treffer

            if (empty($searchIds)) {
                // Keine Treffer → leere Collection zurückgeben
                $emails = collect([]);
                return view('emails.index', compact('emails'));
            }

            $query->whereIn('id', $searchIds);
        }

        // Filter nach Datum
        if ($request->filled('date_from')) {
            $query->where('received_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('received_at', '<=', $request->date_to);
        }

        // Filter nach Tag/Topic
        if ($request->filled('topic')) {
            $query->whereJsonContains('topics', $request->topic);
        }

        // Eager Loading & Sortierung
        $emails = $query->with('attachments')
                        ->latest('received_at')
                        ->paginate(20)
                        ->withQueryString();

        return view('emails.index', compact('emails'));
    }


    /**
     * Zeige einzelne Email mit allen Details
     */
    public function show($id)
    {
        $email = Email::with('attachments')->findOrFail($id);
        return view('emails.show', compact('email'));
    }

    /**
     * Upload-Formular für Drag & Drop
     */
    public function create()
    {
        $topics = $this->getAvailableTopics();
        return view('emails.create', compact('topics'));
    }

    /**
     * Verarbeite hochgeladene .msg oder .eml Datei
     */
    public function store(Request $request)
    {
        \Log::info('=== EMAIL UPLOAD STARTED ===');
        \Log::info('Request has file: ' . ($request->hasFile('email_file') ? 'yes' : 'no'));

        if (!$request->hasFile('email_file')) {
            \Log::error('No file in request');
            return back()->with('error', 'Keine Datei hochgeladen');
        }

        $file = $request->file('email_file');
        \Log::info('File details:', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'is_valid' => $file->isValid(),
            'error' => $file->getError(),
        ]);

        try {
            \Log::info('Starting validation...');
            $request->validate([
                'email_file' => 'required|file|mimetypes:application/octet-stream,text/plain,message/rfc822|max:102400',
                'topics' => 'nullable|array',
            ]);
            \Log::info('Validation passed');

            $extension = strtolower($file->getClientOriginalExtension());
            \Log::info('File extension: ' . $extension);

            $pathToParse = $file->getRealPath();
            \Log::info('Original file path: ' . $pathToParse);

            if ($extension === 'msg') {
                \Log::info('Converting MSG to EML...');
                $pathToParse = MsgToEmlConverter::convert($pathToParse);
                \Log::info('Converted to: ' . $pathToParse);
            }

            \Log::info('Initializing parser...');
            $parser = new Parser();
            $parser->setPath($pathToParse);
            \Log::info('Parser initialized successfully');

            // Parse headers
            $subject = $parser->getHeader('subject') ?? 'Kein Betreff';
            $from = $parser->getHeader('from');
            $to = $parser->getHeader('to');
            $date = $parser->getHeader('date');

            \Log::info('Parsed headers:', [
                'subject' => $subject,
                'from' => $from,
                'to' => $to,
                'date' => $date,
            ]);

            // Parse body
            $bodyHtml = $parser->getMessageBody('html');
            $bodyText = $parser->getMessageBody('text');
            \Log::info('Body lengths: HTML=' . strlen($bodyHtml ?? '') . ', Text=' . strlen($bodyText ?? ''));

            // Erstelle Email-Eintrag
            \Log::info('Creating email record in database...');
            $email = Email::create([
                'subject' => $subject,
                'from_email' => $this->extractEmail($from),
                'from_name' => $this->extractName($from),
                'to_email' => $this->extractEmail($to),
                'to_name' => $this->extractName($to),
                'cc' => $parser->getHeader('cc'),
                'bcc' => $parser->getHeader('bcc'),
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'received_at' => $this->parseDate($date),
                'imported_via' => 'drag_drop',
                'topics' => $request->topics ?? [],
            ]);
            \Log::info('Email created with ID: ' . $email->id);

            // Verarbeite Attachments
            $attachments = $parser->getAttachments();
            \Log::info('Found ' . count($attachments) . ' attachments');

            foreach ($attachments as $index => $attachment) {
                \Log::info('Processing attachment ' . ($index + 1) . '/' . count($attachments));
                $this->storeAttachment($email, $attachment);
            }

            \Log::info('=== EMAIL UPLOAD COMPLETED SUCCESSFULLY ===');

            return redirect()
                ->route('emails.show', $email)
                ->with('success', 'Email erfolgreich importiert!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('=== VALIDATION FAILED ===');
            \Log::error('Validation errors:', $e->errors());
            return back()
                ->withInput()
                ->withErrors($e->errors())
                ->with('error', 'Validierungsfehler');

        } catch (\Exception $e) {
            \Log::error('=== EMAIL UPLOAD FAILED ===');
            \Log::error('Error: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return back()
                ->withInput()
                ->with('error', 'Fehler beim Import: ' . $e->getMessage());
        }
    }

    /**
     * Speichere Attachment
     */
    protected function storeAttachment(Email $email, $attachment)
    {
        try {
            $originalFilename = $attachment->getFilename();
            \Log::info('  - Attachment filename: ' . $originalFilename);

            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $storedFilename = Str::uuid() . '.' . $extension;

            $path = 'attachments/' . $email->id;
            \Log::info('  - Storage path: ' . $path);

            // Explizit 'local' Disk verwenden
            if (!Storage::disk('local')->exists($path)) {
                Storage::disk('local')->makeDirectory($path);
                \Log::info('  - Created directory: ' . $path);
            }

            $content = $attachment->getContent();
            \Log::info('  - Content size: ' . strlen($content) . ' bytes');

            $fullPath = $path . '/' . $storedFilename;
            $saved = Storage::disk('local')->put($fullPath, $content);
            \Log::info('  - File saved: ' . ($saved ? 'YES' : 'NO'));
            \Log::info('  - File exists: ' . (Storage::disk('local')->exists($fullPath) ? 'YES' : 'NO'));
            \Log::info('  - Full system path: ' . Storage::disk('local')->path($fullPath));

            $attachmentRecord = Attachment::create([
                'email_id' => $email->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'mime_type' => $attachment->getContentType(),
                'file_size' => strlen($content),
                'storage_path' => $fullPath,
            ]);
            \Log::info('  - DB record created with ID: ' . $attachmentRecord->id);

        } catch (\Exception $e) {
            \Log::error('  - ERROR storing attachment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download Attachment
     */
    public function downloadAttachment(Attachment $attachment)
    {
        \Log::info('Download request for attachment ID: ' . $attachment->id);

        if (!Storage::disk('local')->exists($attachment->storage_path)) {
            \Log::error('Attachment file not found: ' . $attachment->storage_path);
            abort(404, 'Datei nicht gefunden');
        }

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->original_filename
        );
    }

    /**
     * Zeige Attachment im Browser (für PDFs/Bilder)
     */
    public function viewAttachment(Attachment $attachment)
    {
        \Log::info('View request for attachment:', [
            'id' => $attachment->id,
            'filename' => $attachment->original_filename,
            'path' => $attachment->storage_path,
            'mime_type' => $attachment->mime_type
        ]);

        if (!Storage::disk('local')->exists($attachment->storage_path)) {
            \Log::error('Attachment file not found for viewing: ' . $attachment->storage_path);
            \Log::error('Full system path: ' . Storage::disk('local')->path($attachment->storage_path));
            abort(404, 'Datei nicht gefunden');
        }

        $path = Storage::disk('local')->path($attachment->storage_path);
        \Log::info('Serving file from: ' . $path);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Update Topics/Tags für eine Email
     */
    public function updateTopics(Request $request, Email $email)
    {
        $request->validate([
            'topics' => 'required|array',
        ]);

        $email->update([
            'topics' => $request->topics
        ]);

        return back()->with('success', 'Themen aktualisiert!');
    }

    /**
     * Lösche Email und zugehörige Attachments
     */
    public function destroy(Email $email)
    {
        \Log::info('Deleting email ID: ' . $email->id);

        // Lösche alle Attachment-Dateien
        foreach ($email->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->storage_path);
            $attachment->delete();
        }

        $email->delete();

        return redirect()
            ->route('emails.index')
            ->with('success', 'Email gelöscht!');
    }

    /**
     * Hilfsfunktionen
     */
    protected function extractEmail($header)
    {
        if (!$header) return null;

        preg_match('/<(.+?)>/', $header, $matches);
        return $matches[1] ?? $header;
    }

    protected function extractName($header)
    {
        if (!$header) return null;

        preg_match('/^(.+?)\s*</', $header, $matches);
        return isset($matches[1]) ? trim($matches[1], '"') : null;
    }

    protected function parseDate($dateString)
    {
        try {
            return $dateString ? new \DateTime($dateString) : now();
        } catch (\Exception $e) {
            return now();
        }
    }

    protected function getAvailableTopics()
    {
        return [
            'Heizung',
            'Dach',
            'Fassade',
            'Eigentümerversammlung',
            'Jahresabrechnung',
            'Reparaturen',
            'Versicherung',
            'Gemeinschaftseigentum',
            'Hausordnung',
            'Rücklagen',
            'Sonstiges',
        ];
    }
}
