import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

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
 * Badge de non-lus global : barre latérale, titre de l'onglet, favicon et
 * badge d'application (Badging API) lorsqu'elle est disponible.
 */
function initUnreadBadge(body) {
    const unreadUrl = body.dataset.unreadUrl;
    const favicon = document.getElementById('app-favicon');

    if (!unreadUrl) {
        return;
    }

    const baseTitle = document.title;
    const originalFavicon = favicon?.getAttribute('href') || null;
    let faviconImage = null;
    let faviconFailed = false;
    let lastTotal = 0;

    const drawFavicon = (total) => {
        if (!favicon) {
            return;
        }

        const size = 64;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;

        const context = canvas.getContext('2d');

        if (context) {
            if (faviconImage?.complete && faviconImage.naturalWidth && !faviconFailed) {
                context.drawImage(faviconImage, 0, 0, size, size);
            }

            const radius = 17;
            const center = size - radius;

            context.beginPath();
            context.arc(center, radius, radius, 0, Math.PI * 2);
            context.fillStyle = '#dc2626';
            context.fill();
            context.lineWidth = 3;
            context.strokeStyle = '#ffffff';
            context.stroke();

            context.fillStyle = '#ffffff';
            context.font = 'bold 22px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(total > 9 ? '9+' : String(total), center, radius + 1);
        }

        favicon.href = canvas.toDataURL('image/png');
    };

    const updateFavicon = (total) => {
        if (!favicon) {
            return;
        }

        if (total <= 0) {
            if (originalFavicon) {
                favicon.href = originalFavicon;
            }

            return;
        }

        if (!faviconImage) {
            faviconImage = new Image();
            faviconImage.onload = () => drawFavicon(lastTotal);
            faviconImage.onerror = () => {
                faviconFailed = true;
                drawFavicon(lastTotal);
            };
            faviconImage.src = originalFavicon || '/favicon.ico';
            return;
        }

        if (faviconImage.complete) {
            drawFavicon(total);
        }
    };

    const apply = (total) => {
        total = Math.max(0, Number(total) || 0);
        lastTotal = total;

        const badge = document.getElementById('chat-unread-badge');

        if (badge) {
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.classList.toggle('hidden', total === 0);
        }

        document.title = total > 0 ? `(${total}) ${baseTitle}` : baseTitle;

        if ('setAppBadge' in navigator) {
            if (total > 0) {
                navigator.setAppBadge(total).catch(() => {});
            } else if ('clearAppBadge' in navigator) {
                navigator.clearAppBadge().catch(() => {});
            }
        }

        updateFavicon(total);
    };

    const refresh = async () => {
        try {
            const response = await fetch(unreadUrl, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            apply(data.total);
        } catch (error) {
            // Réseau instable : le prochain cycle réessaiera.
        }
    };

    // La page Messagerie met à jour son compteur toutes les 4 s ; ce point
    // d'entrée lui permet de rafraîchir aussi le titre et le favicon.
    window.epsvUpdateUnread = apply;
    window.epsvRefreshUnread = refresh;

    refresh();
    setInterval(refresh, 15000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
}

/**
 * Après une déconnexion, l'abonnement reste dans le navigateur mais est
 * désactivé côté serveur. À la connexion suivante, on le réattribue au compte
 * courant sans redemander l'autorisation (déjà accordée pour ce site).
 */
function reattachPushSubscription(body) {
    const subscribeUrl = body.dataset.pushSubscribeUrl;

    if (!subscribeUrl || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
        return;
    }

    (async () => {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                return;
            }

            await fetch(subscribeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    ...subscription.toJSON(),
                    contentEncoding: 'aes128gcm',
                }),
            });
        } catch (error) {
            // Sans réattachement, l'utilisateur peut réactiver manuellement.
        }
    })();
}

const startGlobalFeatures = () => {
    const body = document.body;

    if (!body || body.dataset.authenticated !== '1') {
        return;
    }

    initUnreadBadge(body);
    reattachPushSubscription(body);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startGlobalFeatures, { once: true });
} else {
    startGlobalFeatures();
}

/**
 * À la déconnexion, on désactive l'abonnement au lieu de le supprimer : il sera
 * réattribué automatiquement au prochain compte connecté sur ce navigateur.
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

            const disableUrl = document.body.dataset.pushDisableUrl || '/push/subscriptions/disable';

            await fetch(disableUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
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
