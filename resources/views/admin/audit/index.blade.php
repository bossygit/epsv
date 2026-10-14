<x-app-layout>
    <x-page-header
        title="Journal d'audit"
        subtitle="Traçabilité des actions sensibles : validation, verrouillage, corrections, comptes et paramètres."
    />

    <div class="space-y-6">
        <!-- Filtres -->
        <x-card>
            <form method="GET" action="{{ route('audit.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <x-input-label for="user_id" value="Utilisateur" />
                    <x-select name="user_id" class="mt-1" :options="$users->pluck('name', 'id')" :selected="request('user_id')" placeholder="— Tous —" />
                </div>
                <div>
                    <x-input-label for="action" value="Action" />
                    <x-select name="action" class="mt-1" :options="$actions->mapWithKeys(fn ($action) => [$action => $action])" :selected="request('action')" placeholder="— Toutes —" />
                </div>
                <div>
                    <x-input-label for="from" value="Du" />
                    <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="request('from')" />
                </div>
                <div>
                    <x-input-label for="to" value="Au" />
                    <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="request('to')" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('audit.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Réinitialiser</a>
                </div>
            </form>
        </x-card>

        <!-- Journal -->
        <x-card :padding="false">
            <x-slot name="title">Événements ({{ $logs->total() }})</x-slot>
            <x-slot name="subtitle">Les entrées les plus récentes apparaissent en premier.</x-slot>

            @if ($logs->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun événement ne correspond à ces critères." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Date et heure</th>
                                <th class="px-4 py-2.5">Utilisateur</th>
                                <th class="px-4 py-2.5">Action</th>
                                <th class="px-4 py-2.5">Objet</th>
                                <th class="px-4 py-2.5">Motif</th>
                                <th class="px-4 py-2.5">IP</th>
                                <th class="px-4 py-2.5">Détails</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">
                                        {{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-700">
                                        {{ $log->user?->name ?? 'Système' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-800">{{ $log->action }}</code>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        @if ($log->auditable_type)
                                            {{ class_basename($log->auditable_type) }}
                                            <span class="text-xs text-gray-400">#{{ $log->auditable_id }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $log->reason ?: '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500">
                                        {{ $log->ip_address ?: '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($log->old_values || $log->new_values)
                                            <details>
                                                <summary class="cursor-pointer text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                                    Afficher
                                                </summary>
                                                <div class="mt-2 space-y-2">
                                                    @if ($log->old_values)
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Avant</p>
                                                            <pre class="mt-0.5 max-w-md overflow-x-auto rounded bg-gray-50 p-2 font-mono text-xs text-gray-700">{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    @endif
                                                    @if ($log->new_values)
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Après</p>
                                                            <pre class="mt-0.5 max-w-md overflow-x-auto rounded bg-gray-50 p-2 font-mono text-xs text-gray-700">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    @endif
                                                </div>
                                            </details>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
                    {{ $logs->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
