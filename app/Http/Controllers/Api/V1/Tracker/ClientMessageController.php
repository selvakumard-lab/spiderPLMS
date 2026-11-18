<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\ChatEvaluation;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Services\ChatEvaluationService;
use App\Services\ClientCommunicationService;
use App\Services\WhatsAppService;
use App\Support\Tenancy\TenantContext;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI-drafted client emails and WhatsApp messages (reviewed and sent by a person) and the WhatsApp chat of a project.
 */
class ClientMessageController extends Controller
{
    public function __construct(private ClientCommunicationService $communication) {}

    public function index(Request $request): JsonResponse
    {
        $messages = ClientMessage::query()->with(['project:id,name,code', 'client:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->latest('id')->limit(200)->get();

        return response()->json(['data' => $messages->map(fn (ClientMessage $m) => TrackerPresenter::message($m))]);
    }

    public function draft(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'reason' => ['required', Rule::in(ClientCommunicationService::REASONS)],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_if($data['channel'] === 'whatsapp' && ! app(TenantContext::class)->get()->planAllows('whatsapp_chat'), 402, 'WhatsApp chat is not included in your plan. Upgrade to message clients on WhatsApp.');

        $message = $this->communication->draft($project, $data['channel'], $data['reason'], $data['instruction'] ?? null, $request->user(), $data['body'] ?? null);

        return response()->json(['data' => TrackerPresenter::message($message->load(['project:id,name,code', 'client:id,name']))], 201);
    }

    public function update(Request $request, ClientMessage $message): JsonResponse
    {
        abort_unless($message->isDraft() || $message->status === 'failed', 422, 'Only drafts can be edited.');

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'to_address' => ['nullable', 'string', 'max:190'],
        ]);
        if ($message->channel === 'email' && isset($data['to_address'])) {
            $request->validate(['to_address' => ['email:rfc']]);
        }

        $message->update($data + ($message->status === 'failed' ? ['status' => 'draft'] : []));

        return response()->json(['data' => TrackerPresenter::message($message->load(['project:id,name,code', 'client:id,name']))]);
    }

    public function send(Request $request, ClientMessage $message): JsonResponse
    {
        abort_unless($message->isDraft() || $message->status === 'failed', 422, 'This message was already sent.');

        $message = $this->communication->send($message, $request->user());

        return response()->json(['data' => TrackerPresenter::message($message->load(['project:id,name,code', 'client:id,name']))], $message->status === 'sent' ? 200 : 422);
    }

    public function destroy(ClientMessage $message): JsonResponse
    {
        abort_unless($message->direction === 'out' && $message->status !== 'sent', 422, 'Sent messages are kept as a record.');
        $message->delete();

        return response()->json(['message' => 'Draft deleted.']);
    }

    /** The project's WhatsApp conversation with its latest evaluation. */
    public function chat(Project $project, WhatsAppService $whatsapp): JsonResponse
    {
        $messages = ClientMessage::where('project_id', $project->id)->where('channel', 'whatsapp')->whereIn('status', ['sent', 'received', 'failed'])
            ->latest('id')->limit(100)->get()->reverse()->values();
        $evaluation = ChatEvaluation::where('project_id', $project->id)->latest('id')->first();

        return response()->json([
            'configured' => $whatsapp->isConfigured() && app(TenantContext::class)->get()->planAllows('whatsapp_chat'),
            'messages' => $messages->map(fn (ClientMessage $m) => TrackerPresenter::message($m)),
            'evaluation' => $evaluation ? TrackerPresenter::evaluation($evaluation) : null,
        ]);
    }

    public function evaluateChat(Project $project, ChatEvaluationService $evaluator): JsonResponse
    {
        $evaluation = $evaluator->evaluate($project);
        abort_if($evaluation === null, 422, 'There are no WhatsApp messages on this project yet.');

        return response()->json(['evaluation' => TrackerPresenter::evaluation($evaluation)]);
    }
}
