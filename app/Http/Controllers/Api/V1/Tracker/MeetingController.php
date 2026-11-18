<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\MeetingAttachment;
use App\Models\Project;
use App\Models\ProjectMeeting;
use App\Services\ClientNotifier;
use App\Support\Tenancy\TenantContext;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client meeting records with minutes, files, images and links, kept under the project.
 */
class MeetingController extends Controller
{
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'msi', 'com', 'scr', 'jar', 'js', 'vbs', 'ps1'];

    public function __construct(private ClientNotifier $notifier) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $meeting = $project->meetings()->create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->storeFiles($request, $meeting);
        $this->notifier->notify($project, 'meeting', "Meeting recorded: {$meeting->title}", 'Held on '.$meeting->meeting_date->format('j M Y').'.');

        return response()->json(['data' => TrackerPresenter::meeting($meeting->load('attachments'))], 201);
    }

    public function update(Request $request, ProjectMeeting $meeting): JsonResponse
    {
        $meeting->update($this->validated($request, partial: true));
        $this->storeFiles($request, $meeting);

        return response()->json(['data' => TrackerPresenter::meeting($meeting->load('attachments'))]);
    }

    public function destroy(ProjectMeeting $meeting): JsonResponse
    {
        $meeting->attachments->each(fn (MeetingAttachment $f) => Storage::disk('local')->delete($f->path));
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted.']);
    }

    public function download(MeetingAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function destroyAttachment(MeetingAttachment $attachment): JsonResponse
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment removed.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'meeting_date' => [$required, 'date'],
            'title' => [$required, 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:10000'],
            'discussion_points' => ['nullable', 'string', 'max:10000'],
            'links' => ['nullable', 'array', 'max:20'],
            'links.*' => ['url', 'max:500'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ]);

        unset($data['files']);

        return $data;
    }

    private function storeFiles(Request $request, ProjectMeeting $meeting): void
    {
        $tenant = app(TenantContext::class)->get();

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            abort_if(in_array($extension, self::BLOCKED_EXTENSIONS, true), 422, "Files of type .{$extension} are not allowed.");

            $meeting->attachments()->create([
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'path' => $file->storeAs("tenants/{$tenant->uuid}/meetings/{$meeting->id}", Str::uuid().($extension ? ".{$extension}" : ''), 'local'),
            ]);
        }
    }
}
