<?php

namespace App\Http\Controllers;

use App\Models\SchoolProfile;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Paramètres de l'école : identité + règles de calcul configurables.
 */
class SchoolProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit()
    {
        $profile = SchoolProfile::current();

        return view('admin.settings.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $profile = SchoolProfile::current();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'motto' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],
            'director_name' => ['nullable', 'string', 'max:150'],
            'director_title' => ['nullable', 'string', 'max:100'],

            'target_scale' => ['required', 'numeric', 'min:1', 'max:100'],
            'decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'rounding' => ['required', 'in:half_up,half_even,floor'],
            'pass_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'ranking_enabled' => ['boolean'],
            'missing_policy' => ['required', 'in:strict,ignore'],
            'absent_policy' => ['required', 'in:exclude,zero'],
            'annual_weighting' => ['required', 'in:period_weight'],

            'alert_absences_count' => ['required', 'integer', 'min:1', 'max:50'],
            'alert_window_days' => ['required', 'integer', 'min:7', 'max:180'],
            'grade_entry_delay_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $settings = $profile->settings ?? SchoolProfile::defaultSettings();

        $settings['grading'] = [
            'target_scale' => (float) $data['target_scale'],
            'decimals' => (int) $data['decimals'],
            'rounding' => $data['rounding'],
            'pass_threshold' => (float) $data['pass_threshold'],
            'ranking_enabled' => $request->boolean('ranking_enabled'),
            'missing_policy' => $data['missing_policy'],
            'absent_policy' => $data['absent_policy'],
            'exempt_policy' => 'exclude',
            'annual_weighting' => $data['annual_weighting'],
        ];

        $settings['attendance'] = [
            'alert_absences_count' => (int) $data['alert_absences_count'],
            'alert_window_days' => (int) $data['alert_window_days'],
        ];

        $settings['grading_alerts'] = [
            'grade_entry_delay_days' => (int) $data['grade_entry_delay_days'],
        ];

        $old = $profile->only(['name', 'short_name', 'settings']);

        $profile->update([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'motto' => $data['motto'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? 'Congo',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'director_name' => $data['director_name'] ?? null,
            'director_title' => $data['director_title'] ?? null,
            'settings' => $settings,
        ]);

        $this->audit->log('settings.updated', $profile, $old, ['name' => $profile->name, 'settings' => $settings]);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
