<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canal général « Toute l'école » : visible par tous les comptes actifs.
 *
 * Il est créé par migration (et non par un seeder) pour que les installations
 * déjà en production le reçoivent au déploiement. Les comptes existants y sont
 * rattachés avec leur curseur de lecture au dernier message (aucun historique
 * non lu à l'arrivée). Les comptes créés ensuite sont rattachés à leur première
 * ouverture de la messagerie.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conversationId = DB::table('conversations')->insertGetId([
            'type' => 'channel',
            'name' => 'Toute l\'école',
            'direct_key' => null,
            'created_by' => null,
            'last_message_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            DB::table('conversation_user')->insert([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'last_read_message_id' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('conversations')
            ->where('type', 'channel')
            ->where('name', 'Toute l\'école')
            ->delete();
    }
};
