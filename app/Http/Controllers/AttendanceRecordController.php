<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceRecordController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    /** Correction d'un statut avec motif obligatoire (traçabilité). */
    public function correct(Request $request, AttendanceRecord $record)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'minutes_late' => ['nullable', 'integer', 'min:1', 'max:600'],
            'justification' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $this->service->correctRecord($record, $data, $request->user(), $data['reason']);

        return back()->with('success', 'Présence corrigée. La modification est tracée.');
    }

    /** Justification d'une absence ou d'un retard. */
    public function justify(Request $request, AttendanceRecord $record)
    {
        $data = $request->validate([
            'justification' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->service->justify($record, $data['justification'], $request->user());

        return back()->with('success', 'Justification enregistrée.');
    }
}
