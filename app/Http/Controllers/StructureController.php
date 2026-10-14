<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Structure pédagogique : cycles et niveaux.
 */
class StructureController extends Controller
{
    public function index()
    {
        $cycles = Cycle::query()
            ->with(['gradeLevels' => fn ($q) => $q->withCount('classes')])
            ->orderBy('sequence')
            ->get();

        return view('structure.cycles.index', compact('cycles'));
    }

    public function storeCycle(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:cycles,name'],
            'code' => ['required', 'string', 'max:10', 'unique:cycles,code'],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        Cycle::create($data);

        return back()->with('success', 'Cycle créé.');
    }

    public function updateCycle(Request $request, Cycle $cycle)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('cycles', 'name')->ignore($cycle->id)],
            'code' => ['required', 'string', 'max:10', Rule::unique('cycles', 'code')->ignore($cycle->id)],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $cycle->update($data);

        return back()->with('success', 'Cycle mis à jour.');
    }

    public function destroyCycle(Cycle $cycle)
    {
        if ($cycle->gradeLevels()->exists()) {
            return back()->withErrors(['cycle' => 'Supprimez d\'abord les niveaux de ce cycle.']);
        }

        $cycle->delete();

        return back()->with('success', 'Cycle supprimé.');
    }

    public function storeLevel(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', 'unique:grade_levels,code'],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        GradeLevel::create($data);

        return back()->with('success', 'Niveau créé.');
    }

    public function updateLevel(Request $request, GradeLevel $gradeLevel)
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'exists:cycles,id'],
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('grade_levels', 'code')->ignore($gradeLevel->id)],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $gradeLevel->update($data);

        return back()->with('success', 'Niveau mis à jour.');
    }

    public function destroyLevel(GradeLevel $gradeLevel)
    {
        if ($gradeLevel->classes()->exists()) {
            return back()->withErrors(['level' => 'Impossible de supprimer un niveau utilisé par des classes.']);
        }

        $gradeLevel->delete();

        return back()->with('success', 'Niveau supprimé.');
    }
}
