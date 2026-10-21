@props(['status', 'label' => null])

@php
    $map = [
        // Périodes
        'open' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Ouverte'],
        'submitted' => ['border-amber-200 bg-amber-50 text-amber-800', 'Soumise'],
        'validated' => ['border-blue-200 bg-blue-50 text-blue-800', 'Validée'],
        'locked' => ['border-slate-300 bg-slate-100 text-slate-700', 'Verrouillée'],
        // Évaluations / bulletins
        'draft' => ['border-line bg-ivory text-muted', 'Brouillon'],
        'published' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Publiée'],
        'cancelled' => ['border-rose-200 bg-rose-50 text-rose-700', 'Annulée'],
        'generated' => ['border-indigo-200 bg-indigo-50 text-indigo-800', 'Généré'],
        // Notes
        'missing' => ['border-amber-200 bg-amber-50 text-amber-800', 'Non saisie'],
        'entered' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Saisie'],
        'absent' => ['border-rose-200 bg-rose-50 text-rose-700', 'Absent(e)'],
        'exempt' => ['border-line bg-ivory text-muted', 'Dispensé(e)'],
        // Présences
        'present' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Présent'],
        'late' => ['border-amber-200 bg-amber-50 text-amber-800', 'Retard'],
        'excused' => ['border-blue-200 bg-blue-50 text-blue-800', 'Justifiée'],
        // Élèves / années
        'active' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Actif'],
        'inactive' => ['border-line bg-ivory text-muted', 'Inactif'],
        'transferred' => ['border-amber-200 bg-amber-50 text-amber-800', 'Transféré'],
        'graduated' => ['border-indigo-200 bg-indigo-50 text-indigo-800', 'Diplômé'],
        'closed' => ['border-slate-300 bg-slate-100 text-slate-700', 'Clôturée'],
        // Décisions
        'admis' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'Admis'],
        'redouble' => ['border-rose-200 bg-rose-50 text-rose-700', 'Redouble'],
    ];

    [$classes, $defaultLabel] = $map[$status] ?? ['border-line bg-ivory text-muted', $status];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-sm border px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-[0.08em] $classes"]) }}>
    {{ $label ?? $defaultLabel }}
</span>
