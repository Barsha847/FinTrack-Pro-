<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\ResponseHelper;
use Exception;

/**
 * Class Router
 * 
 * A clean, lightweight, regex-based request router that maps incoming HTTP requests
 * to controller actions, passes route variables, and processes routing middlewares.
 */
class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    /**
     * Register global middleware to run on every route.
     * 
     * @param callable|string $middleware Callable or class name with handle() method
     * @return void
     */
    public function addMiddleware(callable|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Add a route mapping to the registry.
     * 
     * @param string $method GET, POST, PUT, DELETE, etc.
     * @param string $path Route path (e.g. /api/expenses/{id})
     * @param array|callable $handler Action handler (e.g. [Controller::class, 'method'] or closure)
     * @param array $middlewares Route-specific middleware classes/callables
     * @return void
     */
    public function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): void
    {
        $path = '/' . trim($path, '/');
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middlewares
        ];
    }

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Dispatch the current request to matched route handlers.
     * 
     * @param string $requestMethod
     * @param string $requestUri
     * @return void
     * @throws Exception
     */
    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $parsedUrl = parse_url($requestUri);
        $path = '/' . trim($parsedUrl['path'] ?? '', '/');
        $method = strtoupper($requestMethod);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $routePattern = $this->buildRegexPattern($route['path']);
            if (preg_match($routePattern, $path, $matches)) {
                // Filter named capture groups (keys containing strings) to extract path parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run global middlewares
                foreach ($this->globalMiddleware as $middleware) {
                    $this->runMiddleware($middleware);
                }

                // Run route-specific middlewares
                foreach ($route['middleware'] as $middleware) {
                    $this->runMiddleware($middleware);
                }

                $handler = $route['handler'];

                // Handle closures/callables directly
                if (is_callable($handler)) {
                    call_user_func_array($handler, $params);
                    return;
                }

                // Handle Controller arrays: [ControllerName::class, 'method']
                if (is_array($handler) && count($handler) === 2) {
                    $controllerClass = $handler[0];
                    $action = $handler[1];

                    if (class_exists($controllerClass)) {
                        $controllerInstance = new $controllerClass();
                        if (method_exists($controllerInstance, $action)) {
                            call_user_func_array([$controllerInstance, $action], $params);
                            return;
                        }
                    }
                }

                throw new Exception("Route handler not found for {$method} {$path}", 500);
            }
        }

        // Default 404 matching
        ResponseHelper::error("Endpoint '{$method} {$path}' not found", 404);
    }

    /**
     * Convert route patterns like {id} into standard regex named capture groups.
     * 
     * @param string $path
     * @return string
     */
    private function buildRegexPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Instantiate and run a middleware.
     * 
     * @param callable|string $middleware
     * @return void
     * @throws Exception
     */
    private function runMiddleware(callable|string $middleware): void
    {
        if (is_callable($middleware)) {
            $middleware();
            return;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                $instance->handle();
                return;
            }
        }

        throw new Exception("Middleware not executable: " . (is_string($middleware) ? $middleware : gettype($middleware)));
    }
}
