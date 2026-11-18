<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ClientNotifier;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The team's side of the chat thread with a project's client. The client sees every message in their portal.
 */
class ProjectThreadController extends Controller
{
    public function __construct(private ClientNotifier $notifier) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->chatMessages()->get()->map(fn ($m) => TrackerPresenter::chatMessage($m))]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], ['body.required' => 'Write a message first.', 'body.max' => 'Your message is longer than 2000 characters.']);

        abort_if($project->client_id === null, 422, 'Link a client to this project before messaging them.');

        $message = $this->notifier->postChatMessage($project, $request->user(), $data['body']);

        return response()->json(['data' => TrackerPresenter::chatMessage($message)], 201);
    }
}
