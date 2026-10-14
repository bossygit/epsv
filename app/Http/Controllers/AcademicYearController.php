<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicYearController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $years = AcademicYear::query()
            ->withCount(['classes', 'enrollments'])
            ->with('periods')
            ->orderByDesc('start_date')
            ->get();

        return view('structure.years.index', compact('years'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $year = AcademicYear::create($data + ['status' => AcademicYear::STATUS_DRAFT]);

        $this->audit->log('academic_year.created', $year, [], $data);

        return redirect()->route('academic-years.index')
            ->with('success', "L'année scolaire « {$year->name} » a été créée.");
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'name')->ignore($academicYear->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $academicYear->only(array_keys($data));
        $academicYear->update($data);

        $this->audit->log('academic_year.updated', $academicYear, $old, $data);

        return redirect()->route('academic-years.index')
            ->with('success', 'Année scolaire mise à jour.');
    }

    /** Définit l'année courante (une seule à la fois). */
    public function setCurrent(AcademicYear $academicYear)
    {
        if ($academicYear->isClosed()) {
            throw ValidationException::withMessages([
                'status' => 'Une année clôturée ne peut pas devenir l\'année courante.',
            ]);
        }

        AcademicYear::query()->where('is_current', true)->update(['is_current' => false]);
        $academicYear->update(['is_current' => true, 'status' => AcademicYear::STATUS_ACTIVE]);

        $this->audit->log('academic_year.set_current', $academicYear, [], ['is_current' => true]);

        return back()->with('success', "« {$academicYear->name} » est désormais l'année scolaire courante.");
    }

    public function close(AcademicYear $academicYear)
    {
        $academicYear->update(['status' => AcademicYear::STATUS_CLOSED, 'is_current' => false]);

        $this->audit->log('academic_year.closed', $academicYear, [], ['status' => AcademicYear::STATUS_CLOSED]);

        return back()->with('success', "L'année « {$academicYear->name} » a été clôturée.");
    }

    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->enrollments()->exists()) {
            throw ValidationException::withMessages([
                'year' => 'Impossible de supprimer une année qui contient des inscriptions.',
            ]);
        }

        $this->audit->log('academic_year.deleted', null, $academicYear->toArray(), []);
        $academicYear->delete();

        return back()->with('success', 'Année scolaire supprimée.');
    }
}
