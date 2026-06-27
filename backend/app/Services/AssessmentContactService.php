<?php

namespace App\Services;

use App\Models\AssessmentContact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AssessmentContactService
{
    /**
     * Return a paginated list of assessment contacts.
     *
     * @return LengthAwarePaginator<AssessmentContact>
     */
    public function list(): LengthAwarePaginator
    {
        return AssessmentContact::orderByDesc('created_at')->paginate(20);
    }

    /**
     * Persist an internal audit note on the given assessment contact.
     *
     * Stores who wrote the note and when alongside the note text, so the record
     * is self-documenting without needing a separate audit log table.
     *
     * @param  array<string, mixed>  $data  Validated input — expects 'admin_note' (nullable string).
     */
    public function saveAdminNote(AssessmentContact $contact, array $data, User $author): AssessmentContact
    {
        $contact->update([
            'admin_note' => $data['admin_note'] ?? null,
            'admin_noted_by_user_id' => $author->id,
            'admin_noted_at' => now(),
        ]);

        Log::info('Assessment contact admin note updated', [
            'assessment_contact_id' => $contact->id,
            'author_id' => $author->id,
        ]);

        return $contact->load('adminNotedByUser');
    }
}
