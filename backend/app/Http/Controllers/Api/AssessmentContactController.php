<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssessmentContact\UpdateAdminNoteRequest;
use App\Http\Resources\AssessmentContactResource;
use App\Models\AssessmentContact;
use App\Services\AssessmentContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssessmentContactController extends Controller
{
    public function __construct(private AssessmentContactService $service) {}

    /**
     * List all assessment contacts (paginated).
     * Access: superadmin, admin_sm.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AssessmentContact::class);

        $contacts = $this->service->list();

        return AssessmentContactResource::collection($contacts);
    }

    /**
     * Show a single assessment contact.
     * Access: superadmin, admin_sm.
     */
    public function show(AssessmentContact $assessmentContact): JsonResponse
    {
        $this->authorize('view', $assessmentContact);

        $assessmentContact->loadMissing('adminNotedByUser');

        return response()->json([
            'success' => true,
            'data' => new AssessmentContactResource($assessmentContact),
            'message' => 'OK.',
        ]);
    }

    /**
     * Save an internal audit note on a submitted assessment contact.
     *
     * Only admin_sm (and superadmin) may call this endpoint.
     * The note is purely internal — the contact never sees it.
     *
     * PATCH /assessment-contacts/{assessmentContact}/admin-note
     */
    public function updateAdminNote(UpdateAdminNoteRequest $request, AssessmentContact $assessmentContact): JsonResponse
    {
        $this->authorize('updateAdminNote', $assessmentContact);

        $contact = $this->service->saveAdminNote(
            $assessmentContact,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new AssessmentContactResource($contact),
            'message' => 'Nota guardada correctamente.',
        ]);
    }
}
