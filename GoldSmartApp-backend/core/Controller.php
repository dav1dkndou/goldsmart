<?php declare(strict_types=1);

/**
 * Base Controller Class - Optimized
 * Features:
 * - Request body caching (php://input can only be read once)
 * - Model lazy loading with static cache
 * - Auth user caching to avoid repeated JWT decode
 * - Optimized validation with rule parsing cache
 */
class Controller
{
    protected PDO $db;
    private ?string $cachedAuthHeader = null;
    private ?array $cachedRequestBody = null;
    private ?array $cachedAuthUser = null;
    private static array $modelCache = [];
    private static array $validationRuleCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Load a model (with static caching for reuse)
     */
    protected function model(string $model): object
    {
        // Return cached instance if exists
        if (isset(self::$modelCache[$model])) {
            return self::$modelCache[$model];
        }

        $modelFile = __DIR__ . '/../models/' . $model . '.php';
        if (file_exists($modelFile)) {
            require_once $modelFile;
            self::$modelCache[$model] = new $model();
            return self::$modelCache[$model];
        }
        throw new Exception("Model {$model} not found");
    }

    /**
     * Get JSON request body (cached - php://input can only be read once)
     */
    protected function getRequestBody(): array
    {
        // Return cached value if already read
        if ($this->cachedRequestBody !== null) {
            return $this->cachedRequestBody;
        }

        $json = file_get_contents('php://input');
        if ($json === false || $json === '') {
            $this->cachedRequestBody = [];
            return [];
        }

        // Decode with performance flags
        $decoded = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
        $this->cachedRequestBody = is_array($decoded) ? $decoded : [];

        return $this->cachedRequestBody;
    }

    /**
     * Get request data (alias for getRequestBody for consistency)
     */
    protected function getRequestData(): array
    {
        return $this->getRequestBody();
    }

    /**
     * Validate required fields (optimized with rule caching)
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            // Cache parsed rules to avoid repeated explode/trim
            $cacheKey = md5($rule);
            if (!isset(self::$validationRuleCache[$cacheKey])) {
                self::$validationRuleCache[$cacheKey] = array_map('trim', explode('|', $rule));
            }
            $ruleList = self::$validationRuleCache[$cacheKey];

            // Pre-check if field exists and get value once
            $fieldExists = isset($data[$field]);
            $fieldValue = $fieldExists ? $data[$field] : null;
            $fieldEmpty = $fieldValue === '' || $fieldValue === null;

            foreach ($ruleList as $r) {
                // Required
                if ($r === 'required' && (!$fieldExists || $fieldEmpty)) {
                    $errors[$field][] = ucfirst($field) . ' wajib diisi';
                    continue;
                }

                // Skip other validations if field is empty and not required
                if ($fieldEmpty) {
                    continue;
                }

                // Email
                if ($r === 'email') {
                    if (!filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = 'Format email tidak valid';
                    }
                    continue;
                }

                // Numeric
                if ($r === 'numeric') {
                    if (!is_numeric($fieldValue)) {
                        $errors[$field][] = ucfirst($field) . ' harus berupa angka';
                    }
                    continue;
                }

                // Min length
                if ($r[0] === 'm' && strpos($r, 'min:') === 0) {
                    $min = (int) substr($r, 4);
                    if (mb_strlen((string) $fieldValue) < $min) {
                        $errors[$field][] = ucfirst($field) . " minimal {$min} karakter";
                    }
                    continue;
                }

                // Max length
                if ($r[0] === 'm' && strpos($r, 'max:') === 0) {
                    $max = (int) substr($r, 4);
                    if (mb_strlen((string) $fieldValue) > $max) {
                        $errors[$field][] = ucfirst($field) . " maksimal {$max} karakter";
                    }
                    continue;
                }
            }
        }

        return $errors;
    }

    /**
     * Get authenticated user from JWT (cached to avoid repeated decode)
     */
    protected function getAuthUser(): ?array
    {
        // Return cached user if already decoded
        if ($this->cachedAuthUser !== null) {
            return $this->cachedAuthUser;
        }

        $token = $this->getBearerToken();
        if (!$token) {
            $this->cachedAuthUser = [];
            return null;
        }

        try {
            $payload = JWT::decode($token);
            $this->cachedAuthUser = $payload;
            return $payload;
        } catch (Exception $e) {
            $this->cachedAuthUser = [];
            return null;
        }
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): array
    {
        $user = $this->getAuthUser();
        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized. Please login.'
            ], 401);
            exit;
        }
        return $user;
    }

    /**
     * Get authenticated user ID
     */
    protected function getUserId(): ?int
    {
        $user = $this->requireAuth();
        return isset($user['user_id']) ? (int) $user['user_id'] : null;
    }

    /**
     * Check if user has specific role
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getAuthUser();
        return $user && isset($user['role']) && $user['role'] === $role;
    }

    /**
     * Require specific role
     */
    protected function requireRole(string $role): array
    {
        $user = $this->requireAuth();
        if (!isset($user['role']) || $user['role'] !== $role) {
            Response::forbidden('Access denied. Required role: ' . $role);
            exit;
        }
        return $user;
    }

    /**
     * Get Bearer token from header
     */
    private function getBearerToken(): ?string
    {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s+(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Get Authorization header (cached for performance)
     */
    private function getAuthorizationHeader(): ?string
    {
        // Return cached value if available
        if ($this->cachedAuthHeader !== null) {
            return $this->cachedAuthHeader;
        }

        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if ($requestHeaders !== false) {
                $requestHeaders = array_combine(
                    array_map('ucwords', array_keys($requestHeaders)),
                    array_values($requestHeaders)
                );
                if (isset($requestHeaders['Authorization'])) {
                    $headers = trim($requestHeaders['Authorization']);
                }
            }
        }

        // Cache the result
        $this->cachedAuthHeader = $headers;
        return $headers;
    }
}
