<?php

namespace App\Services;

use App\Models\BbAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BbAssignmentService
{
    /**
     * Assign a BB user to a company.
     *
     * Throws ValidationException if the company already has an active BB,
     * since the unique constraint on company_id allows only one assignment.
     *
     * @param  array<string, mixed>  $data  Must contain 'bb_user_id' and 'company_id'.
     * @throws ValidationException
     */
    public function assign(array $data, User $assignedBy): BbAssignment
    {
        // Guard: each company can only have one BB (DB unique, but validate early
        // to return a human-readable error before hitting the constraint).
        $alreadyAssigned = BbAssignment::where('company_id', $data['company_id'])->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'company_id' => 'Esta empresa ya tiene un Business Bishop asignado.',
            ]);
        }

        $assignment = BbAssignment::create([
            'bb_user_id'  => $data['bb_user_id'],
            'company_id'  => $data['company_id'],
            'assigned_at' => now(),
            'assigned_by' => $assignedBy->id,
        ]);

        Log::info('BB assigned to company', [
            'assignment_id' => $assignment->id,
            'bb_user_id'    => $assignment->bb_user_id,
            'company_id'    => $assignment->company_id,
            'assigned_by'   => $assignedBy->id,
        ]);

        return $assignment->load(['bbUser', 'company', 'assignedByUser']);
    }

    /**
     * Remove a BB assignment.
     */
    public function unassign(BbAssignment $assignment): void
    {
        $context = [
            'assignment_id' => $assignment->id,
            'bb_user_id'    => $assignment->bb_user_id,
            'company_id'    => $assignment->company_id,
        ];

        $assignment->delete();

        Log::info('BB assignment removed', $context);
    }
}
