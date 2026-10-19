<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Génère une paire de clés VAPID pour les notifications Web Push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->line('Ajoutez ces lignes dans .env (ou dans les variables Render) :');
        $this->newLine();
        $this->line('WEBPUSH_ENABLED=true');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT="mailto:contact@epsv.cg"');
        $this->newLine();
        $this->warn('Conservez la clé privée : elle ne doit jamais être régénérée sans réabonner les navigateurs.');

        return self::SUCCESS;
    }
}
