<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** Mentions « J'aime » (fil d'actualité). */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /** Commentaires (fil d'actualité). */
    public function comments(): HasMany
    {
        return $this->hasMany(MessageComment::class);
    }

    /**
     * L'utilisateur a-t-il aimé cette publication ?
     * Suppose la relation `reactions` déjà chargée (fil d'actualité).
     */
    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->reactions->contains('user_id', $user->getKey());
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /** Un PDF, affiché dans la visionneuse intégrée. */
    public function isPdf(): bool
    {
        if (! $this->hasAttachment()) {
            return false;
        }

        return strtolower(pathinfo($this->attachment_name ?? '', PATHINFO_EXTENSION)) === 'pdf'
            || strtolower((string) $this->attachment_mime) === 'application/pdf';
    }

    /** Un document Word récent, ouvert dans l'éditeur intégré. */
    public function isEditableDocx(): bool
    {
        return $this->editableOfficeType() === 'docx';
    }

    /** Un classeur Excel récent, ouvert dans l'éditeur intégré. */
    public function isEditableXlsx(): bool
    {
        return $this->editableOfficeType() === 'xlsx';
    }

    public function isEditableOffice(): bool
    {
        return $this->editableOfficeType() !== null;
    }

    /** Extension du document bureautique éditable, ou null. */
    public function editableOfficeType(): ?string
    {
        if (! $this->hasAttachment()) {
            return null;
        }

        $extension = strtolower(pathinfo($this->attachment_name ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['docx', 'xlsx'], true) ? $extension : null;
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
