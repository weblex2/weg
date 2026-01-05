<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'file_size',
        'storage_path',
    ];

    /**
     * Relationship: Attachment gehört zu einer Email
     */
    public function email()
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Accessor: Formatierte Dateigröße
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' Bytes';
        }
    }

    /**
     * Accessor: Dateiendung
     */
    public function getExtensionAttribute()
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    /**
     * Accessor: Ist Bild?
     */
    public function getIsImageAttribute()
    {
        $imageMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];

        return in_array($this->mime_type, $imageMimes);
    }

    /**
     * Accessor: Ist PDF?
     */
    public function getIsPdfAttribute()
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Accessor: Kann im Browser angezeigt werden?
     */
    public function getCanPreviewAttribute()
    {
        return $this->is_image || $this->is_pdf;
    }

    /**
     * Accessor: Icon-Klasse basierend auf Dateityp
     */
    public function getIconClassAttribute()
    {
        return match(true) {
            $this->is_image => 'fa-file-image',
            $this->is_pdf => 'fa-file-pdf',
            in_array($this->extension, ['doc', 'docx']) => 'fa-file-word',
            in_array($this->extension, ['xls', 'xlsx']) => 'fa-file-excel',
            in_array($this->extension, ['zip', 'rar', '7z']) => 'fa-file-archive',
            default => 'fa-file',
        };
    }

    /**
     * Accessor: Download-URL
     */
    public function getDownloadUrlAttribute()
    {
        return route('attachments.download', $this);
    }

    /**
     * Accessor: Preview-URL (nur für Bilder und PDFs)
     */
    public function getPreviewUrlAttribute()
    {
        if (!$this->can_preview) {
            return null;
        }
        return route('attachments.view', $this);
    }

    /**
     * Helper: Prüfe ob Datei existiert
     */
    public function exists()
    {
        return Storage::exists($this->storage_path);
    }

    /**
     * Helper: Lösche physische Datei
     */
    public function deleteFile()
    {
        if ($this->exists()) {
            Storage::delete($this->storage_path);
        }
    }

    /**
     * Override delete um auch Datei zu löschen
     */
    public function delete()
    {
        $this->deleteFile();
        return parent::delete();
    }

    /**
     * Boot: Automatisches Löschen der Datei beim Model-Delete
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            $attachment->deleteFile();
        });
    }
}
