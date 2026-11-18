<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ClientNotifier;
use App\Services\DocumentStorage;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents on a project (specs, designs, reports, and files the client uploaded).
 * Documents shared with the client appear in their portal.
 */
class ProjectDocumentController extends Controller
{
    public function __construct(private ClientNotifier $notifier, private DocumentStorage $storage) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:20480'],
            'visible_to_client' => ['sometimes', 'boolean'],
        ], DocumentStorage::MESSAGES);

        $document = $this->storage->store($project, $request->file('file'), $data['title'], $data['note'] ?? null, false, $request->boolean('visible_to_client', true), $request->user()->id);

        $meta = ['type' => 'document.uploaded', 'subject' => $document, 'payload' => ['name' => $document->name, 'size' => $document->size]];

        if ($document->visible_to_client) {
            $this->notifier->notify($project, 'document', "New document: {$document->title}", $document->note, $meta);
        } else {
            $this->notifier->record($project, 'document', "Document added: {$document->title}", $document->note, $meta + ['internal' => true]);
        }

        return response()->json(['data' => TrackerPresenter::document($document)], 201);
    }

    public function download(ProjectDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function destroy(ProjectDocument $document): JsonResponse
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Document removed.']);
    }
}
