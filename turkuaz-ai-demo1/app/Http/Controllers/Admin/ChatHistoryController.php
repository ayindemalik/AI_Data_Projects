<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('view-chat-history'), 403);

        $onlyNegative = $request->boolean('negative');

        $sessions = ChatSession::query()
            ->with('user')
            ->withCount('messages')
            // Surfacing the count here is what makes the feedback usable:
            // otherwise finding the bad answers means opening every session.
            ->withCount(['messages as negative_feedback_count' => fn ($query) => $query->ratedDown()])
            ->when($onlyNegative, fn ($query) => $query->whereHas('messages', fn ($q) => $q->ratedDown()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.chat-history.index', compact('sessions', 'onlyNegative'));
    }

    public function show(Request $request, ChatSession $chatSession): View
    {
        abort_unless($request->user()->hasPermission('view-chat-history'), 403);

        $chatSession->load(['user', 'messages']);

        return view('admin.chat-history.show', compact('chatSession'));
    }
}
