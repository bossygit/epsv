<x-app-layout>
    <x-page-header
        title="Utilisateurs"
        subtitle="Comptes de l'application, rôles et accès."
    >
        <x-slot name="actions">
            @can('users.manage')
                <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-user')">
                    + Nouvel utilisateur
                </x-primary-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        <!-- Filtres -->
        <x-card>
            <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div class="lg:col-span-2">
                    <x-input-label for="search" value="Recherche" />
                    <x-text-input id="search" name="search" class="mt-1 block w-full" :value="request('search')" placeholder="Nom ou adresse e-mail" />
                </div>
                <div>
                    <x-input-label for="role" value="Rôle" />
                    <x-select name="role" class="mt-1" :options="$roles" :selected="request('role')" placeholder="— Tous les rôles —" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('users.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Réinitialiser</a>
                </div>
            </form>
        </x-card>

        <!-- Liste des utilisateurs -->
        <x-card :padding="false">
            <x-slot name="title">Comptes ({{ $users->total() }})</x-slot>
            <x-slot name="subtitle">Cliquez sur « Modifier » pour changer les informations d'un compte.</x-slot>

            @if ($users->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun utilisateur ne correspond à cette recherche." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Nom</th>
                                <th class="px-4 py-2.5">E-mail</th>
                                <th class="px-4 py-2.5">Fonction</th>
                                <th class="px-4 py-2.5">Rôle</th>
                                <th class="px-4 py-2.5">Statut</th>
                                <th class="px-4 py-2.5">Dernière connexion</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($users as $user)
                                @php $roleName = $user->roles->first()?->name; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $user->email }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $user->job_title ?: '—' }}
                                        @if ($user->phone)
                                            <span class="block text-xs text-gray-400">{{ $user->phone }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $roleName ? ($roles[$roleName] ?? $roleName) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$user->is_active ? 'active' : 'inactive'" />
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('users.manage')
                                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                        x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-user-{{ $user->id }}')">
                                                    Modifier
                                                </button>
                                                <button type="button" class="text-xs font-semibold text-gray-600 hover:text-gray-800"
                                                        x-data="" x-on:click.prevent="$dispatch('open-modal', 'reset-password-{{ $user->id }}')">
                                                    Réinitialiser mot de passe
                                                </button>
                                                @if ($user->id !== auth()->id())
                                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}"
                                                          @if ($user->is_active) onsubmit="return confirm('Désactiver le compte de {{ $user->name }} ?')" @endif>
                                                        @csrf
                                                        <button class="text-xs font-semibold {{ $user->is_active ? 'text-rose-600 hover:text-rose-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                                            {{ $user->is_active ? 'Désactiver' : 'Activer' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
                    {{ $users->links() }}
                </div>
            @endif
        </x-card>
    </div>

    @can('users.manage')
        <!-- Modale : nouvel utilisateur -->
        <x-modal name="create-user" focusable>
            <form method="POST" action="{{ route('users.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Nouvel utilisateur</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="create-name" value="Nom complet" />
                        <x-text-input id="create-name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="create-email" value="Adresse e-mail" />
                        <x-text-input id="create-email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="create-phone" value="Téléphone" />
                        <x-text-input id="create-phone" name="phone" class="mt-1 block w-full" :value="old('phone')" />
                    </div>
                    <div>
                        <x-input-label for="create-job" value="Fonction" />
                        <x-text-input id="create-job" name="job_title" class="mt-1 block w-full" :value="old('job_title')" placeholder="ex. Enseignant de mathématiques" />
                    </div>
                    <div>
                        <x-input-label for="create-role" value="Rôle" />
                        <x-select name="role" id="create-role" class="mt-1" :options="$roles" :selected="old('role')" placeholder="— Choisir un rôle —" required />
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="create-password" value="Mot de passe (optionnel)" />
                        <x-text-input id="create-password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Laisser vide pour générer automatiquement" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    Si le mot de passe est laissé vide, un mot de passe est généré puis affiché une seule fois après la création.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Créer le compte</x-primary-button>
                </div>
            </form>
        </x-modal>

        @foreach ($users as $user)
            <!-- Modale : modifier {{ $user->name }} -->
            <x-modal :name="'edit-user-'.$user->id" focusable>
                <form method="POST" action="{{ route('users.update', $user) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <h2 class="text-lg font-semibold text-gray-900">Modifier {{ $user->name }}</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-name-{{ $user->id }}" value="Nom complet" />
                            <x-text-input id="edit-name-{{ $user->id }}" name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required />
                        </div>
                        <div>
                            <x-input-label for="edit-email-{{ $user->id }}" value="Adresse e-mail" />
                            <x-text-input id="edit-email-{{ $user->id }}" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                        </div>
                        <div>
                            <x-input-label for="edit-phone-{{ $user->id }}" value="Téléphone" />
                            <x-text-input id="edit-phone-{{ $user->id }}" name="phone" class="mt-1 block w-full" :value="old('phone', $user->phone)" />
                        </div>
                        <div>
                            <x-input-label for="edit-job-{{ $user->id }}" value="Fonction" />
                            <x-text-input id="edit-job-{{ $user->id }}" name="job_title" class="mt-1 block w-full" :value="old('job_title', $user->job_title)" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="edit-role-{{ $user->id }}" value="Rôle" />
                            <x-select name="role" :id="'edit-role-'.$user->id" class="mt-1" :options="$roles" :selected="$user->roles->first()?->name" placeholder="— Choisir un rôle —" required />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Enregistrer</x-primary-button>
                    </div>
                </form>
            </x-modal>

            <!-- Modale : réinitialiser le mot de passe -->
            <x-modal :name="'reset-password-'.$user->id" focusable>
                <form method="POST" action="{{ route('users.reset-password', $user) }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-gray-900">Réinitialiser le mot de passe</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Compte de {{ $user->name }} ({{ $user->email }}).
                    </p>
                    <div class="mt-4">
                        <x-input-label for="reset-password-{{ $user->id }}" value="Nouveau mot de passe (optionnel)" />
                        <x-text-input id="reset-password-{{ $user->id }}" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Laisser vide pour générer automatiquement" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Le mot de passe défini ou généré s'affiche une seule fois après l'opération : communiquez-le à l'utilisateur.
                    </p>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Réinitialiser</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endcan
</x-app-layout>
