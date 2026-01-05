<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Email extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'subject',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'cc',
        'bcc',
        'body_html',
        'body_text',
        'received_at',
        'imported_via',
        'topics',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'received_at' => 'datetime',
        'topics' => 'array',
    ];

    /**
     * Relationship: Email hat viele Attachments
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Scout: Welche Felder sollen durchsuchbar sein
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'to_email' => $this->to_email,
            'body_text' => $this->body_text,
            'topics' => $this->topics,
        ];
    }

    /**
     * Scout: Name des Index in Meilisearch
     */
    public function searchableAs(): string
    {
        return 'emails_index';
    }

    /**
     * Scope: Filter nach Topic
     */
    public function scopeWithTopic($query, $topic)
    {
        return $query->whereJsonContains('topics', $topic);
    }

    /**
     * Scope: Filter nach Datum-Range
     */
    public function scopeDateRange($query, $from, $to)
    {
        if ($from) {
            $query->where('received_at', '>=', $from);
        }
        if ($to) {
            $query->where('received_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Accessor: Kurzer Preview-Text (erste 150 Zeichen)
     */
    public function getPreviewAttribute()
    {
        $text = strip_tags($this->body_text ?? $this->body_html ?? '');
        return mb_substr($text, 0, 150) . (mb_strlen($text) > 150 ? '...' : '');
    }

    /**
     * Accessor: Hat Attachments?
     */
    public function getHasAttachmentsAttribute()
    {
        return $this->attachments()->exists();
    }

    /**
     * Accessor: Anzahl Attachments
     */
    public function getAttachmentCountAttribute()
    {
        return $this->attachments()->count();
    }

    /**
     * Helper: Füge Topic hinzu (wenn noch nicht vorhanden)
     */
    public function addTopic(string $topic)
    {
        $topics = $this->topics ?? [];
        if (!in_array($topic, $topics)) {
            $topics[] = $topic;
            $this->update(['topics' => $topics]);
        }
    }

    /**
     * Helper: Entferne Topic
     */
    public function removeTopic(string $topic)
    {
        $topics = $this->topics ?? [];
        $topics = array_values(array_diff($topics, [$topic]));
        $this->update(['topics' => $topics]);
    }

    /**
     * Helper: Prüfe ob Email von bestimmtem Absender
     */
    public function isFrom(string $email)
    {
        return strtolower($this->from_email) === strtolower($email);
    }

    /**
     * Helper: Prüfe ob Email an bestimmten Empfänger
     */
    public function isTo(string $email)
    {
        return strtolower($this->to_email) === strtolower($email);
    }
}
