<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file stored in the Kudos shared Google Drive (or an external link) attached to an order or client.
 * File bytes never live on the application server; only Drive identifiers are stored here.
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    public const KIND_FILE = 'file';

    public const KIND_LINK = 'link';

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_COMMENT = 'comment';

    public const SOURCE_TRELLO_IMPORT = 'trello_import';

    protected $table = 'media';

    protected $fillable = [
        'order_id',
        'client_id',
        'order_comment_id',
        'kind',
        'drive_file_id',
        'drive_folder_id',
        'external_url',
        'name',
        'mime_type',
        'size',
        'source',
        'trello_attachment_id',
        'uploaded_by',
        'uploader_name',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(OrderComment::class, 'order_comment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLink(): bool
    {
        return $this->kind === self::KIND_LINK;
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function getDriveWebUrlAttribute(): ?string
    {
        return $this->drive_file_id ? "https://drive.google.com/file/d/{$this->drive_file_id}/view" : null;
    }

    /**
     * Relative URL that streams the file through the application (no absolute host, so stored links survive domain changes).
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->isLink()) {
            return $this->external_url;
        }

        return route('media.file', $this, absolute: false);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->isLink()) {
            return null;
        }

        return route('media.thumbnail', $this, absolute: false);
    }
}
