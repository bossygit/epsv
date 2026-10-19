<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Un abonnement désactivé à la déconnexion reste dans le navigateur
            // mais ne reçoit plus de notifications jusqu'à la prochaine
            // connexion, qui le réattribue au compte courant.
            $table->timestamp('disabled_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn('disabled_at');
        });
    }
};
