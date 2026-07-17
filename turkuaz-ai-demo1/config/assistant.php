<?php

return [
    // OpenAI
    'openai_api_key' => env('OPENAI_API_KEY'),
    'model' => env('ASSISTANT_MODEL', 'gpt-4o-mini'),
    'temperature' => 0.3,
    'max_tokens' => 700,
    'timeout' => 30,

    // Orchestration flags (assistant on/off itself lives in Settings so
    // admins can toggle it without a deploy).
    'db_first' => true,
    'knowledge_base_enabled' => false, // future: PDF source
    'vector_search_enabled' => false,  // future: vector DB source
];
