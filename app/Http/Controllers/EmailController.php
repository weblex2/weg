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
        $extension = strtolower($request->file('email_file')->getClientOriginalExtension());
        $request->validate([
            //'email_file' => 'required|file|mimes:eml,msg|max:51200', // max 50MB
            'email_file' => 'required|file|mimetypes:application/octet-stream,text/plain,message/rfc822|max:51200',
            'topics' => 'nullable|array',
        ]);

        try {
            $file = $request->file('email_file');
            $extension = strtolower($file->getClientOriginalExtension());

            $pathToParse = $file->getRealPath();

            if ($extension === 'msg') {
                $pathToParse = MsgToEmlConverter::convert($pathToParse);
            }

            $parser = new Parser();
            $parser->setPath($pathToParse);


            // Erstelle Email-Eintrag
            $email = Email::create([
                'subject' => $parser->getHeader('subject') ?? 'Kein Betreff',
                'from_email' => $this->extractEmail($parser->getHeader('from')),
                'from_name' => $this->extractName($parser->getHeader('from')),
                'to_email' => $this->extractEmail($parser->getHeader('to')),
                'to_name' => $this->extractName($parser->getHeader('to')),
                'cc' => $parser->getHeader('cc'),
                'bcc' => $parser->getHeader('bcc'),
                'body_html' => $parser->getMessageBody('html'),
                'body_text' => $parser->getMessageBody('text'),
                'received_at' => $this->parseDate($parser->getHeader('date')),
                'imported_via' => 'drag_drop',
                'topics' => $request->topics ?? [],
            ]);

            // Verarbeite Attachments
            $attachments = $parser->getAttachments();
            foreach ($attachments as $attachment) {
                $this->storeAttachment($email, $attachment);
            }

            return redirect()
                ->route('emails.show', $email)
                ->with('success', 'Email erfolgreich importiert!');

        } catch (\Exception $e) {
             \Log::info($e->getMessage());
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
        $originalFilename = $attachment->getFilename();
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $storedFilename = Str::uuid() . '.' . $extension;

        // Speichere Datei in storage/app/attachments
        $path = 'attachments/' . $email->id;
        Storage::put(
            $path . '/' . $storedFilename,
            $attachment->getContent()
        );

        // Erstelle Attachment-Eintrag
        Attachment::create([
            'email_id' => $email->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $attachment->getContentType(),
            'file_size' => strlen($attachment->getContent()),
            'storage_path' => $path . '/' . $storedFilename,
        ]);
    }

    /**
     * Download Attachment
     */
    public function downloadAttachment(Attachment $attachment)
    {
        if (!Storage::exists($attachment->storage_path)) {
            abort(404, 'Datei nicht gefunden');
        }

        return Storage::download(
            $attachment->storage_path,
            $attachment->original_filename
        );
    }

    /**
     * Zeige Attachment im Browser (für PDFs/Bilder)
     */
    public function viewAttachment(Attachment $attachment)
    {
        if (!Storage::exists($attachment->storage_path)) {
            abort(404, 'Datei nicht gefunden');
        }

        return response()->file(
            Storage::path($attachment->storage_path),
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"'
            ]
        );
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
        // Lösche alle Attachment-Dateien
        foreach ($email->attachments as $attachment) {
            Storage::delete($attachment->storage_path);
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
