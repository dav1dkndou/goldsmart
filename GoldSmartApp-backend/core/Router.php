<?php declare(strict_types=1);

/**
 * Router Class - Optimized
 * Handles URL routing for the API with improved performance
 */
class Router
{
    private array $routes = [];
    private $notFoundCallback = null;
    private array $regexCache = [];
    private static ?string $cachedUri = null;

    public function get(string $path, $callback): void
    {
        $this->addRoute('GET', $path, $callback);
    }

    public function post(string $path, $callback): void
    {
        $this->addRoute('POST', $path, $callback);
    }

    public function put(string $path, $callback): void
    {
        $this->addRoute('PUT', $path, $callback);
    }

    public function delete(string $path, $callback): void
    {
        $this->addRoute('DELETE', $path, $callback);
    }

    private function addRoute(string $method, string $path, $callback): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    public function notFound($callback): void
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Parse and normalize URI (cached for performance)
     */
    private function parseUri(): string
    {
        if (self::$cachedUri !== null) {
            return self::$cachedUri;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove base path if exists
        $basePath = parse_url(BASE_URL, PHP_URL_PATH);
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        // Strip /api prefix from URI
        if (strpos($uri, '/api') === 0) {
            $uri = substr($uri, 4);
        }

        // Normalize to root if empty
        $uri = ($uri === '' || $uri === '/') ? '/' : $uri;

        self::$cachedUri = $uri;
        return $uri;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->parseUri();

        // Early exit for OPTIONS (already handled in index.php, but keep as fallback)
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Route matching with optimized loop
        $routeCount = count($this->routes);
        for ($i = 0; $i < $routeCount; $i++) {
            $route = $this->routes[$i];

            // Quick method check first (faster than regex)
            if ($route['method'] !== $method) {
                continue;
            }

            // Get or create regex pattern
            if (!isset($this->regexCache[$i])) {
                $this->regexCache[$i] = $this->convertToRegex($route['path']);
            }

            if (preg_match($this->regexCache[$i], $uri, $matches)) {
                array_shift($matches);  // Remove full match

                // Extract named parameters if any
                $params = [];
                if (strpos($route['path'], '{') !== false) {
                    if (preg_match_all('/\{(\w+)\}/', $route['path'], $paramNames)) {
                        foreach ($paramNames[1] as $idx => $name) {
                            $params[$name] = $matches[$idx] ?? null;
                        }
                    }
                }

                $this->executeCallback($route['callback'], $params);
                return;
            }
        }

        // Not found
        if ($this->notFoundCallback !== null) {
            call_user_func($this->notFoundCallback);
            return;
        }

        Response::json(['success' => false, 'message' => 'Endpoint not found'], 404);
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '/?$#';
    }

    private function executeCallback($callback, array $params): void
    {
        if (is_array($callback)) {
            $controller = new $callback[0]();
            $method = $callback[1];
            $controller->$method(...array_values($params));
        } else {
            call_user_func_array($callback, array_values($params));
        }
    }
}
