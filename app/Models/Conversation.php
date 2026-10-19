<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversation de la messagerie interne : canal général, groupe ou échange
 * direct entre deux membres du personnel.
 */
class Conversation extends Model
{
    public const TYPE_CHANNEL = 'channel';

    public const TYPE_GROUP = 'group';

    public const TYPE_DIRECT = 'direct';

    protected $fillable = [
        'type',
        'name',
        'direct_key',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_message_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Conversations auxquelles l'utilisateur participe. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('users.id', $userId));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->getKey())->exists();
    }

    /**
     * Marque la conversation comme lue jusqu'à maintenant (ou jusqu'au
     * message indiqué). Rattache l'utilisateur s'il n'est pas encore membre.
     */
    public function markReadBy(User $user, ?int $messageId = null): void
    {
        $messageId ??= (int) $this->messages()->max('id');

        if ($this->hasParticipant($user)) {
            $this->participants()->updateExistingPivot($user->getKey(), [
                'last_read_message_id' => $messageId,
            ]);

            return;
        }

        $this->participants()->attach($user->getKey(), [
            'last_read_message_id' => $messageId,
        ]);
    }

    /** Nombre de messages non lus par cet utilisateur dans la conversation. */
    public function unreadCountFor(User $user): int
    {
        return Message::query()
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.conversation_id', $this->getKey())
            ->where('conversation_user.user_id', $user->getKey())
            ->where('messages.user_id', '<>', $user->getKey())
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > COALESCE(conversation_user.last_read_message_id, 0)')
            ->count();
    }

    /** Total des messages non lus, toutes conversations confondues. */
    public static function unreadTotalFor(User $user): int
    {
        return Message::query()
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.user_id', $user->getKey())
            ->where('messages.user_id', '<>', $user->getKey())
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > COALESCE(conversation_user.last_read_message_id, 0)')
            ->count();
    }

    /** Titre affiché : nom du canal/groupe, ou nom de l'interlocuteur en direct. */
    public function titleFor(User $user): string
    {
        if ($this->type === self::TYPE_DIRECT) {
            $other = $this->participants->first(
                fn (User $participant) => $participant->getKey() !== $user->getKey()
            );

            return $other?->name ?? 'Discussion privée';
        }

        return $this->name ?: 'Discussion';
    }

    /** Identifiant canonique d'une conversation directe (ordre indifférent). */
    public static function directKeyFor(int $firstUserId, int $secondUserId): string
    {
        return min($firstUserId, $secondUserId).':'.max($firstUserId, $secondUserId);
    }

    /** Canal général créé à l'installation. */
    public static function general(): ?self
    {
        return static::query()
            ->where('type', self::TYPE_CHANNEL)
            ->orderBy('id')
            ->first();
    }
}
