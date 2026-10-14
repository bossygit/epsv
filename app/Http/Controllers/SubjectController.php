<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::query()
            ->withCount('classSubjects')
            ->orderBy('name')
            ->get();

        return view('structure.subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Subject::create($data + ['is_active' => true]);

        return back()->with('success', 'Matière créée.');
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($subject->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $subject->update($data);

        return back()->with('success', 'Matière mise à jour.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->classSubjects()->exists()) {
            return back()->withErrors(['subject' => 'Impossible de supprimer une matière utilisée par des classes. Désactivez-la plutôt.']);
        }

        $subject->delete();

        return back()->with('success', 'Matière supprimée.');
    }
}
