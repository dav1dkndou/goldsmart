<?php declare(strict_types=1);

/**
 * JWT Implementation - Optimized
 * Features:
 * - Cached header encoding (never changes)
 * - Fast token validation without full decode
 * - Signature verification optimization
 * - Token refresh support
 * - DB-based token blacklist for logout invalidation
 */
class JWT
{
    // Static header for reuse (never changes)
    private const HEADER = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    // Cache encoded header (computed once)
    private static ?string $cachedHeaderEncoded = null;

    // In-memory cache for blacklist lookups within same request
    private static array $blacklistCache = [];

    // Whether the blacklist table exists (checked once per request)
    private static ?bool $blacklistTableExists = null;

    /**
     * Encode data to JWT token (optimized with cached header)
     */
    public static function encode(array $payload): string
    {
        $currentTime = time();

        // Add timestamps
        $payload['exp'] = $currentTime + JWT_EXPIRY;
        $payload['iat'] = $currentTime;

        // Use cached header encoding
        if (self::$cachedHeaderEncoded === null) {
            $headerJson = json_encode(self::HEADER, JSON_UNESCAPED_SLASHES);
            if ($headerJson === false) {
                throw new Exception('Failed to encode JWT header');
            }
            self::$cachedHeaderEncoded = self::base64UrlEncode($headerJson);
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new Exception('Failed to encode JWT payload');
        }

        $payloadEncoded = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', self::$cachedHeaderEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return self::$cachedHeaderEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Decode JWT token (optimized)
     */
    public static function decode(string $token): array
    {
        // Check DB blacklist first
        if (self::isRevoked($token)) {
            throw new Exception('Token has been revoked');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature (timing-safe comparison)
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", JWT_SECRET, true);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid token signature');
        }

        // Decode payload
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new Exception('Invalid token payload');
        }

        // Check expiry
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }

        return $payload;
    }

    /**
     * Base64 URL encode (optimized)
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode (optimized)
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Validate token without full decode (faster for validation-only checks)
     */
    public static function isValid(string $token): bool
    {
        try {
            // Quick format check
            if (substr_count($token, '.') !== 2) {
                return false;
            }

            // Check blacklist
            if (self::isRevoked($token)) {
                return false;
            }

            $parts = explode('.', $token);
            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // Verify signature
            $signature = self::base64UrlDecode($signatureEncoded);
            $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", JWT_SECRET, true);

            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }

            // Quick expiry check (without full JSON decode)
            $payloadJson = self::base64UrlDecode($payloadEncoded);
            if (preg_match('/"exp":(\d+)/', $payloadJson, $matches)) {
                if ((int) $matches[1] < time()) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Refresh token (create new token from existing valid token)
     */
    public static function refresh(string $token): string
    {
        $payload = self::decode($token);

        // Remove old timestamps
        unset($payload['exp'], $payload['iat']);

        // Blacklist old token
        self::revoke($token);

        // Create new token
        return self::encode($payload);
    }

    /**
     * Revoke a token (add to DB blacklist)
     */
    public static function revoke(string $token): void
    {
        $hash = self::tokenHash($token);

        // Cache in-memory for this request
        self::$blacklistCache[$hash] = true;

        // Persist to DB
        try {
            $db = Database::getInstance()->getConnection();
            self::ensureBlacklistTable($db);

            // Get token expiry for auto-cleanup
            $expiry = self::getExpiry($token);
            $expiresAt = $expiry ? date('Y-m-d H:i:s', $expiry) : date('Y-m-d H:i:s', time() + 86400);

            $stmt = $db->prepare(
                'INSERT IGNORE INTO token_blacklist (token_hash, expires_at) VALUES (?, ?)'
            );
            $stmt->execute([$hash, $expiresAt]);

            // Opportunistic cleanup: remove expired entries (1% chance per request)
            if (mt_rand(1, 100) === 1) {
                $db->exec('DELETE FROM token_blacklist WHERE expires_at < NOW()');
            }
        } catch (Exception $e) {
            error_log('JWT revoke error: ' . $e->getMessage());
        }
    }

    /**
     * Check if token is revoked (DB-backed with in-memory cache)
     */
    public static function isRevoked(string $token): bool
    {
        $hash = self::tokenHash($token);

        // Check in-memory cache first (same-request optimization)
        if (isset(self::$blacklistCache[$hash])) {
            return true;
        }

        try {
            $db = Database::getInstance()->getConnection();
            if (!self::ensureBlacklistTable($db)) {
                return false;
            }

            $stmt = $db->prepare(
                'SELECT 1 FROM token_blacklist WHERE token_hash = ? LIMIT 1'
            );
            $stmt->execute([$hash]);
            $found = (bool) $stmt->fetchColumn();

            // Cache result in-memory
            if ($found) {
                self::$blacklistCache[$hash] = true;
            }

            return $found;
        } catch (Exception $e) {
            error_log('JWT isRevoked check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hash token for storage (SHA-256, never store raw tokens)
     */
    private static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Ensure the blacklist table exists (checked once per request)
     */
    private static function ensureBlacklistTable(PDO $db): bool
    {
        if (self::$blacklistTableExists !== null) {
            return self::$blacklistTableExists;
        }

        try {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `token_blacklist` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `token_hash` VARCHAR(64) NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_token_hash` (`token_hash`),
                    KEY `idx_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            self::$blacklistTableExists = true;
        } catch (Exception $e) {
            error_log('JWT blacklist table check error: ' . $e->getMessage());
            self::$blacklistTableExists = false;
        }

        return self::$blacklistTableExists;
    }

    /**
     * Get payload without validation (use with caution!)
     */
    public static function getPayloadUnsafe(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($parts[1]);
        return json_decode($payloadJson, true);
    }

    /**
     * Get token expiry time
     */
    public static function getExpiry(string $token): ?int
    {
        $payload = self::getPayloadUnsafe($token);
        return $payload['exp'] ?? null;
    }

    /**
     * Check if token will expire soon (within specified seconds)
     */
    public static function willExpireSoon(string $token, int $withinSeconds = 300): bool
    {
        $expiry = self::getExpiry($token);
        if ($expiry === null) {
            return true;
        }

        return ($expiry - time()) < $withinSeconds;
    }
}
