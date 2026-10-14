<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Student $student)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'profession' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
            'is_emergency_contact' => ['boolean'],
        ]);

        $guardian = Guardian::create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'phone_alt' => $data['phone_alt'] ?? null,
            'email' => $data['email'] ?? null,
            'profession' => $data['profession'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $student->guardians()->attach($guardian->id, [
            'relationship' => $data['relationship'],
            'is_primary' => $request->boolean('is_primary'),
            'is_emergency_contact' => $request->boolean('is_emergency_contact'),
        ]);

        if ($request->boolean('is_primary')) {
            // Un seul responsable principal à la fois : on met à jour la table
            // pivot student_guardian (le drapeau n'existe pas sur guardians).
            DB::table('student_guardian')
                ->where('student_id', $student->id)
                ->where('guardian_id', '!=', $guardian->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }

        $this->audit->log('guardian.created', $student, [], ['guardian' => $guardian->full_name, 'relationship' => $data['relationship']]);

        return back()->with('success', 'Responsable ajouté.');
    }

    public function update(Request $request, Student $student, Guardian $guardian)
    {
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'profession' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
            'is_emergency_contact' => ['boolean'],
        ]);

        $guardian->update([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'phone_alt' => $data['phone_alt'] ?? null,
            'email' => $data['email'] ?? null,
            'profession' => $data['profession'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $student->guardians()->updateExistingPivot($guardian->id, [
            'relationship' => $data['relationship'],
            'is_primary' => $request->boolean('is_primary'),
            'is_emergency_contact' => $request->boolean('is_emergency_contact'),
        ]);

        if ($request->boolean('is_primary')) {
            // Même règle qu'à la création : mise à jour de la table pivot.
            DB::table('student_guardian')
                ->where('student_id', $student->id)
                ->where('guardian_id', '!=', $guardian->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }

        $this->audit->log('guardian.updated', $student, [], ['guardian' => $guardian->full_name]);

        return back()->with('success', 'Responsable mis à jour.');
    }

    public function destroy(Student $student, Guardian $guardian)
    {
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);

        $student->guardians()->detach($guardian->id);

        if (! $guardian->students()->exists()) {
            $guardian->delete();
        }

        $this->audit->log('guardian.detached', $student, ['guardian' => $guardian->full_name], []);

        return back()->with('success', 'Responsable retiré.');
    }
}
