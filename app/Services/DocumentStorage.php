<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Stores an uploaded project document, from either the team or the client. */
class DocumentStorage
{
    public const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'msi', 'com', 'scr', 'jar', 'js', 'vbs', 'ps1'];

    public const MESSAGES = [
        'title.required' => 'Give the document a title.',
        'file.required' => 'Choose a file to upload.',
        'file.max' => 'That file is larger than 20 MB.',
        'file.uploaded' => 'The upload failed. The file may be larger than the server allows.',
    ];

    public function store(Project $project, UploadedFile $file, string $title, ?string $note, bool $fromClient, bool $visibleToClient, ?int $userId): ProjectDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());
        abort_if(in_array($extension, self::BLOCKED_EXTENSIONS, true), 422, "Files of type .{$extension} are not allowed.");

        $tenant = app(TenantContext::class)->get();

        return $project->documents()->create([
            'uploaded_by' => $userId,
            'title' => $title,
            'note' => $note,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'visible_to_client' => $visibleToClient,
            'from_client' => $fromClient,
            'path' => $file->storeAs("tenants/{$tenant->uuid}/documents/{$project->id}", Str::uuid().($extension ? ".{$extension}" : ''), 'local'),
        ]);
    }
}
