<?php

namespace App\Services;

use App\Models\SchoolPeriod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Workflow de validation des périodes :
 * open → submitted → validated → locked.
 * Toute transition est journalisée dans l'audit.
 */
class PeriodWorkflowService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(SchoolPeriod $period, User $by): void
    {
        $this->transition($period, SchoolPeriod::STATUS_OPEN, SchoolPeriod::STATUS_SUBMITTED, 'period.submitted', $by);
    }

    public function validate(SchoolPeriod $period, User $by): void
    {
        $this->transition($period, SchoolPeriod::STATUS_SUBMITTED, SchoolPeriod::STATUS_VALIDATED, 'period.validated', $by, function (SchoolPeriod $p) use ($by) {
            $p->validated_by = $by->id;
            $p->validated_at = now();
        });
    }

    public function lock(SchoolPeriod $period, User $by): void
    {
        $this->transition($period, SchoolPeriod::STATUS_VALIDATED, SchoolPeriod::STATUS_LOCKED, 'period.locked', $by, function (SchoolPeriod $p) {
            $p->locked_at = now();
        });
    }

    /** Réouverture exceptionnelle (direction uniquement), avec motif. */
    public function reopen(SchoolPeriod $period, User $by, string $reason): void
    {
        if (! in_array($period->status, [SchoolPeriod::STATUS_SUBMITTED, SchoolPeriod::STATUS_VALIDATED, SchoolPeriod::STATUS_LOCKED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Cette période est déjà ouverte.',
            ]);
        }

        $before = $period->status;
        $period->update(['status' => SchoolPeriod::STATUS_OPEN]);

        $this->audit->log('period.reopened', $period, ['status' => $before], ['status' => $period->status], $reason, $by);
    }

    private function transition(
        SchoolPeriod $period,
        string $from,
        string $to,
        string $action,
        User $by,
        ?callable $callback = null,
    ): void {
        if ($period->status !== $from) {
            throw ValidationException::withMessages([
                'status' => "Transition impossible : la période doit être au statut « {$from} » (statut actuel : {$period->status}).",
            ]);
        }

        $period->status = $to;

        if ($callback) {
            $callback($period);
        }

        $period->save();

        $this->audit->log($action, $period, ['status' => $from], ['status' => $to], null, $by);
    }
}
