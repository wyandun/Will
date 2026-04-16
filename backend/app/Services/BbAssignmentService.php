<?php

namespace App\Services;

use App\Models\BbAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
        // Guard: each company can only have one BB. The check-and-insert runs inside
        // a transaction so concurrent requests cannot both pass the exists() check and
        // then race to insert — the second will hit the DB unique constraint, which is
        // caught and converted to a friendly ValidationException instead of a 500.
        try {
            $assignment = DB::transaction(function () use ($data, $assignedBy): BbAssignment {
                $alreadyAssigned = BbAssignment::where('company_id', $data['company_id'])->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'company_id' => 'Esta empresa ya tiene un Business Bishop asignado.',
                    ]);
                }

                return BbAssignment::create([
                    'bb_user_id'  => $data['bb_user_id'],
                    'company_id'  => $data['company_id'],
                    'assigned_at' => now(),
                    'assigned_by' => $assignedBy->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request inserted first — surface the same friendly message.
            throw ValidationException::withMessages([
                'company_id' => 'Esta empresa ya tiene un Business Bishop asignado.',
            ]);
        }

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
