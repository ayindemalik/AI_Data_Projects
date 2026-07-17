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

        $sessions = ChatSession::query()
            ->with('user')
            ->withCount('messages') 
            ->latest()
            ->paginate(20);

        return view('admin.chat-history.index', compact('sessions'));
    }

    public function show(Request $request, ChatSession $chatSession): View
    {
        abort_unless($request->user()->hasPermission('view-chat-history'), 403);

        $chatSession->load(['user', 'messages']);

        return view('admin.chat-history.show', compact('chatSession'));
    }
}
