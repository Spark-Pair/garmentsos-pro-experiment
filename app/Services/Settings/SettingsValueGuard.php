<?php

namespace App\Services\Settings;

class SettingsValueGuard
{
    protected const SECRET_PATTERNS = [
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/i',
        '/\bAPP_KEY\s*=/i',
        '/\bDB_(PASSWORD|USERNAME|HOST|DATABASE)\s*=/i',
        '/\b(github_pat|ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9_]{20,}\b/',
        '/\b(password|token|secret|private[_ -]?key|authorization)\s*[:=]/i',
        '/\bBearer\s+[A-Za-z0-9._-]{20,}\b/i',
    ];

    public function assertNoSecretLikeValue(string $value): void
    {
        foreach (self::SECRET_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new \InvalidArgumentException('Settings values must not contain secrets or credentials.');
            }
        }
    }
}
