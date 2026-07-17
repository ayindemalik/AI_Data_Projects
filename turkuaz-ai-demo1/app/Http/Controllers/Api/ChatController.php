<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Handle one chat message. Works for logged-in users AND guests:
     * the session is identified by a token the browser holds, so no
     * login is required to talk to the assistant (Guest role behavior).
     */
    public function send(Request $request, AssistantService $assistant): JsonResponse
    {
        if (!Setting::get('assistant_enabled', true)) {
            return response()->json([
                'reply' => 'Asistan şu anda devre dışı. Lütfen daha sonra tekrar deneyin.',
                'products' => [],
            ], 503);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_token' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();

        // Find or start the session. A session belongs to whoever holds its
        // token; if a logged-in user sends a token from before login, adopt it.
        $session = null;
        if (!empty($data['session_token'])) {
            $session = ChatSession::where('token', $data['session_token'])->first();
        }

        if (!$session) {
            $session = ChatSession::create([
                'token' => (string) Str::uuid(),
                'user_id' => $user?->id,
                'locale' => app()->getLocale(),
            ]);
        } elseif ($user && $session->user_id === null) {
            $session->update(['user_id' => $user->id]);
        }

        $result = $assistant->reply($session, $data['message'], $user);

        // Small product cards for the UI, from the matched ids.
        $products = Product::with(['images', 'series'])
            ->whereIn('id', $result['product_ids'] ?: [-1])
            ->get()
            ->map(function (Product $p) {
                return [
                    'id' => $p->id,
                    'name' => $p->translate('name'),
                    'series' => $p->series?->translate('name'),
                    'dimensions' => $p->dimensions,
                    'image' => $p->images->first()?->url,
                ];
            });

        return response()->json([
            'session_token' => $session->token,
            'reply' => $result['reply'],
            'source' => $result['source'],
            'products' => $products,
        ]);
    }
}
