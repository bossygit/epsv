@props(['status', 'label' => null])

@php
    $map = [
        // Périodes
        'open' => ['bg-emerald-100 text-emerald-800', 'Ouverte'],
        'submitted' => ['bg-amber-100 text-amber-800', 'Soumise'],
        'validated' => ['bg-blue-100 text-blue-800', 'Validée'],
        'locked' => ['bg-slate-200 text-slate-700', 'Verrouillée'],
        // Évaluations / bulletins
        'draft' => ['bg-slate-100 text-slate-600', 'Brouillon'],
        'published' => ['bg-emerald-100 text-emerald-800', 'Publiée'],
        'cancelled' => ['bg-rose-100 text-rose-700', 'Annulée'],
        'generated' => ['bg-indigo-100 text-indigo-800', 'Généré'],
        // Notes
        'missing' => ['bg-amber-100 text-amber-800', 'Non saisie'],
        'entered' => ['bg-emerald-100 text-emerald-800', 'Saisie'],
        'absent' => ['bg-rose-100 text-rose-700', 'Absent(e)'],
        'exempt' => ['bg-slate-100 text-slate-600', 'Dispensé(e)'],
        // Présences
        'present' => ['bg-emerald-100 text-emerald-800', 'Présent'],
        'late' => ['bg-amber-100 text-amber-800', 'Retard'],
        'excused' => ['bg-blue-100 text-blue-800', 'Justifiée'],
        // Élèves / années
        'active' => ['bg-emerald-100 text-emerald-800', 'Actif'],
        'inactive' => ['bg-slate-100 text-slate-600', 'Inactif'],
        'transferred' => ['bg-amber-100 text-amber-800', 'Transféré'],
        'graduated' => ['bg-indigo-100 text-indigo-800', 'Diplômé'],
        'closed' => ['bg-slate-200 text-slate-700', 'Clôturée'],
        // Décisions
        'admis' => ['bg-emerald-100 text-emerald-800', 'Admis'],
        'redouble' => ['bg-rose-100 text-rose-700', 'Redouble'],
    ];

    [$classes, $defaultLabel] = $map[$status] ?? ['bg-gray-100 text-gray-700', $status];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $classes"]) }}>
    {{ $label ?? $defaultLabel }}
</span>
