<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SignatureController extends Controller
{
    // A signature is tied to the person, not any one business — it appears on
    // every invoice they create anywhere, so this reads whichever user id the
    // invoice's creator has, not a business-scoped membership.
    public function show(User $user): BinaryFileResponse
    {
        abort_unless($user->signature_path && Storage::disk('public')->exists($user->signature_path), 404);

        return response()->file(Storage::disk('public')->path($user->signature_path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        $user = $request->user();
        $previousPath = $user->signature_path;
        $path = $validated['signature']->store('signatures', 'public');
        $user->update(['signature_path' => $path]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return response()->json([
            'message' => 'Signature uploaded.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }
        $user->update(['signature_path' => null]);

        return response()->json([
            'message' => 'Signature removed.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
