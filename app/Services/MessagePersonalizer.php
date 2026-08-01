<?php

namespace App\Services;

class MessagePersonalizer
{
    public function render(string $body, array $vars = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($vars) {
            $key = $matches[1];

            return array_key_exists($key, $vars) ? (string) $vars[$key] : $matches[0];
        }, $body) ?? $body;
    }
}
