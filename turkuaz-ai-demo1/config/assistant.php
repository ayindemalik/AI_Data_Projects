<?php

return [
    // OpenAI
    'openai_api_key' => env('OPENAI_API_KEY'),
    'model' => env('ASSISTANT_MODEL', 'gpt-4o-mini'),
    'temperature' => 0.3,
    'max_tokens' => 700,
    'timeout' => 30,

    // Conversation memory: how many previous messages (user + assistant
    // combined) are replayed to the model so follow-up questions like
    // "and in black?" make sense. 6 = roughly the last 3 exchanges.
    // Every message here is re-sent on every request, so this is the main
    // cost/quality dial. Memory can be switched off entirely in Settings.
    'history_messages' => 6,

    // Long past answers are truncated before being replayed — the model only
    // needs the gist of what was already said, not the full text again.
    'history_max_chars' => 500,

    // Orchestration flags (assistant on/off itself lives in Settings so
    // admins can toggle it without a deploy).
    'db_first' => true,
    'knowledge_base_enabled' => false, // future: PDF source
    'vector_search_enabled' => false,  // future: vector DB source
];
