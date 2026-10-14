<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolPeriod;
use App\Services\PeriodWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolPeriodController extends Controller
{
    public function __construct(private readonly PeriodWorkflowService $workflow) {}

    public function store(Request $request, AcademicYear $academicYear)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'sequence' => [
                'required', 'integer', 'min:1', 'max:10',
                Rule::unique('school_periods', 'sequence')->where('academic_year_id', $academicYear->id),
            ],
            'type' => ['required', Rule::in(['trimestre', 'semestre'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:10'],
        ]);

        $period = $academicYear->periods()->create($data + ['status' => SchoolPeriod::STATUS_OPEN]);

        return back()->with('success', "Période « {$period->name} » créée.");
    }

    public function update(Request $request, SchoolPeriod $schoolPeriod)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'sequence' => [
                'required', 'integer', 'min:1', 'max:10',
                Rule::unique('school_periods', 'sequence')
                    ->where('academic_year_id', $schoolPeriod->academic_year_id)
                    ->ignore($schoolPeriod->id),
            ],
            'type' => ['required', Rule::in(['trimestre', 'semestre'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:10'],
        ]);

        $schoolPeriod->update($data);

        return back()->with('success', 'Période mise à jour.');
    }

    public function destroy(SchoolPeriod $schoolPeriod)
    {
        if ($schoolPeriod->evaluations()->exists()) {
            return back()->withErrors(['period' => 'Impossible de supprimer une période qui contient des évaluations.']);
        }

        $schoolPeriod->delete();

        return back()->with('success', 'Période supprimée.');
    }

    public function submit(Request $request, SchoolPeriod $schoolPeriod)
    {
        $this->workflow->submit($schoolPeriod, $request->user());

        return back()->with('success', 'Résultats soumis à la direction pour validation.');
    }

    public function validate(Request $request, SchoolPeriod $schoolPeriod)
    {
        $this->workflow->validate($schoolPeriod, $request->user());

        return back()->with('success', 'Période validée.');
    }

    public function lock(Request $request, SchoolPeriod $schoolPeriod)
    {
        $this->workflow->lock($schoolPeriod, $request->user());

        return back()->with('success', 'Période verrouillée.');
    }

    public function reopen(Request $request, SchoolPeriod $schoolPeriod)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->workflow->reopen($schoolPeriod, $request->user(), $data['reason']);

        return back()->with('success', 'Période réouverte. Toute modification ultérieure sera tracée.');
    }
}
