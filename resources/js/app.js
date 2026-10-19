

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Le service worker sert aux notifications Web Push (voir public/sw.js).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Contexte non sécurisé ou navigateur sans support : la messagerie
            // reste utilisable avec le badge de non-lus.
        });
    });
}

/**
 * Sur un poste partagé, l'abonnement push ne doit pas rester attaché au compte
 * précédent. On le supprime avant de valider la déconnexion.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.action.endsWith('/logout')) {
        return;
    }

    if (form.dataset.pushCleanupDone === '1') {
        return;
    }

    event.preventDefault();

    const cleanup = (async () => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                return;
            }

            await fetch('/push/subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            await subscription.unsubscribe();
        } catch (error) {
            // La déconnexion ne doit jamais être bloquée par le nettoyage push.
        }
    })();

    Promise.race([
        cleanup,
        new Promise((resolve) => setTimeout(resolve, 1200)),
    ]).finally(() => {
        form.dataset.pushCleanupDone = '1';
        form.submit();
    });
});
