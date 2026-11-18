<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own details. There is no password: everyone signs in with an emailed one-time code.
 */
class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $user->fill($data)->save();

        return response()->json(['user' => new UserResource($user->load('roles'))]);
    }

    /** Signs the user out everywhere except the device they are using now. */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $revoked = $request->user()->tokens()
            ->when($current?->getKey(), fn ($query, $id) => $query->where('id', '!=', $id))
            ->delete();

        return response()->json(['revoked' => $revoked]);
    }
}
