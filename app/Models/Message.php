<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /** Un document Word récent, seul format ouvert dans l'éditeur intégré. */
    public function isEditableDocx(): bool
    {
        return $this->hasAttachment()
            && str_ends_with(strtolower($this->attachment_name ?? ''), '.docx');
    }

    /** Taille lisible (o, Ko, Mo) sans dépendance externe. */
    public function attachmentSizeLabel(): ?string
    {
        if (! $this->attachment_size) {
            return null;
        }

        if ($this->attachment_size >= 1_048_576) {
            return number_format($this->attachment_size / 1_048_576, 1, ',', ' ').' Mo';
        }

        if ($this->attachment_size >= 1024) {
            return number_format($this->attachment_size / 1024, 0, ',', ' ').' Ko';
        }

        return $this->attachment_size.' o';
    }
}
