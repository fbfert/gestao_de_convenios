<?php

namespace App\Services\Automation;

class AutomationPayloadRedactor
{
    private const SENSITIVE_KEYS = [
        'api_key',
        'authorization',
        'cookie',
        'password',
        'senha',
        'token',
    ];

    public function redact(array $payload): array
    {
        return $this->redactValue($payload);
    }

    private function redactValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = $this->redactValue($item);
        }

        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
