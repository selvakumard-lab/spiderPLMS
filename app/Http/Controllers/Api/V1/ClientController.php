<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientMailer;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Client master data: who the workspace delivers to and how to reach them.
 */
class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::query()->withCount('projects')->withExists('portalUsers as has_portal_access')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->whereLike('name', '%'.$request->string('search').'%')
                ->orWhereLike('contact_person', '%'.$request->string('search').'%')
                ->orWhereLike('email', '%'.$request->string('search').'%')))
            ->orderBy('name')->get();

        return response()->json(['data' => $clients->map(fn (Client $c) => TrackerPresenter::client($c))]);
    }

    public function store(Request $request): JsonResponse
    {
        $client = Client::create($this->validated($request));

        return response()->json(['data' => TrackerPresenter::client($client->loadCount('projects'))], 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $client->update($this->validated($request, partial: true));

        return response()->json(['data' => TrackerPresenter::client($client->loadCount('projects'))]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->portalUsers()->each(fn (User $user) => $user->tokens()->delete());
        $client->portalUsers()->forceDelete();
        $client->delete();

        return response()->json(['message' => 'Client deleted.']);
    }

    /** Gives the client a portal login: they sign in with a one-time code sent to the client's email. */
    public function grantPortalAccess(Request $request, Client $client, ClientMailer $mailer): JsonResponse
    {
        if (blank($client->email)) {
            throw ValidationException::withMessages(['email' => 'Add an email address to this client first. It is their portal login.']);
        }

        $email = Str::lower($client->email);

        if (User::where('email', $email)->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', '!=', $client->id))->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already belongs to another account in your workspace. Use a different email for the client.']);
        }

        $portalUser = User::firstOrCreate(['email' => $email, 'client_id' => $client->id], [
            'name' => $client->contact_person ?: $client->name,
            'password' => Str::random(40),
            'status' => 'active',
        ]);

        // Only a newly given access sends the welcome email; pressing the button again does not repeat it.
        if ($portalUser->wasRecentlyCreated) {
            $mailer->portalAccessGranted($client, $portalUser, $request->user());
        }

        return response()->json(['data' => TrackerPresenter::client($client->loadCount('projects')->loadExists('portalUsers as has_portal_access'))]);
    }

    public function revokePortalAccess(Client $client): JsonResponse
    {
        $client->portalUsers()->each(fn (User $user) => $user->tokens()->delete());
        $client->portalUsers()->forceDelete();

        return response()->json(['data' => TrackerPresenter::client($client->loadCount('projects')->loadExists('portalUsers as has_portal_access'))]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^[\d\s+\-()]{7,20}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
