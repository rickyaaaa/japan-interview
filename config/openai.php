<?php

return [
    'api_key'             => env('OPENAI_API_KEY'),
    'base_url'            => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'whisper-1'),
    'timeout'             => (int) env('OPENAI_TIMEOUT', 60),
];
