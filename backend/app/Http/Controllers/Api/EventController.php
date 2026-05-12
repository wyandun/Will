<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ListEventsRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(private EventService $eventService) {}

    public function index(ListEventsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $validated = $request->validated();

        $events = $this->eventService->list(
            $request->user(),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): EventResource
    {
        $this->authorize('create', Event::class);

        $event = $this->eventService->create($request->user(), $request->validated());

        $event->load('user');

        return new EventResource($event);
    }

    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        $event->load('user');

        return new EventResource($event);
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        $event->load('user');

        return new EventResource($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Event deleted.',
        ]);
    }
}
