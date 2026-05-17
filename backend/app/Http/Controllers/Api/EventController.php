<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(private EventService $eventService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        $events = $this->eventService->list(
            $request->user(),
            $request->query('search'),
            $perPage,
        );

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $event = $this->eventService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
            'message' => 'events.created_success',
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load('creator');

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
            'message' => 'OK.',
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
            'message' => 'events.updated_success',
        ]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'events.deleted_success',
        ]);
    }
}
