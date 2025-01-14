<?php

namespace CommandString\Router;

use HttpSoft\Emitter\SapiEmitter;
use HttpSoft\Message\Response;
use HttpSoft\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;

/**
 * Class Router.
 */
class Router
{
    /**
     * @var array The before middleware route patterns and their handling functions
     */
    private array $beforeRoutes = array();
    
    /**
     * @var array The route patterns and their handling functions
     */
    private array $routes = array();

    /**
     * @var array The after middle route patterns and their handling functions
     */
    private array $afterRoutes = array();

    /**
     * @var array [string|callable] The function to be executed when no route has been matched
     */
    protected array $notFoundCallback = [];

    /**
     * @var string Current base route, used for (sub)route mounting
     */
    private string $baseRoute = '';

    /**
     * @var string The Request Method that needs to be handled
     */
    private string $requestedMethod = '';

    /**
     * @var string The Server Base Path for Router Execution
     */
    private string $serverBasePath = '';

    /**
     * @var string Default Controllers Namespace
     */
    private $namespace = '';

    /**
     * Store a before middleware route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string            $methods Allowed methods, | delimited
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function before(string $methods, string $pattern, callable|string $fn): void
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->beforeRoutes[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn,
            );
        }
    }

    /**
     * Store a after middleware route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string            $methods Allowed methods, | delimited
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function after(string $methods, string $pattern, callable|string $fn): void
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->afterRoutes[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn,
            );
        }
    }

    /**
     * Set a Default Lookup Namespace for Callable methods.
     *
     * @param string $namespace A given namespace
     */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Get the given Namespace before.
     *
     * @return string The given Namespace if exists
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Store a route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string            $methods Allowed methods, | delimited
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function match(string $methods, string $pattern, string|callable $fn): void
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->routes[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn,
            );
        }
    }

    /**
     * Shorthand for a route accessed using any method.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function all(string $pattern, string|callable $fn): void
    {
        $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using GET.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function get(string $pattern, string|callable $fn): void
    {
        $this->match('GET', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using POST.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function post(string $pattern, string|callable $fn): void
    {
        $this->match('POST', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using PATCH.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function patch(string $pattern, string|callable $fn): void
    {
        $this->match('PATCH', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using DELETE.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function delete(string $pattern, string|callable $fn): void
    {
        $this->match('DELETE', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using PUT.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function put(string $pattern, string|callable $fn): void
    {
        $this->match('PUT', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using OPTIONS.
     *
     * @param string            $pattern A route pattern such as /about/system
     * @param string|callable   $fn      The handling function to be executed
     */
    public function options(string $pattern, string|callable $fn): void
    {
        $this->match('OPTIONS', $pattern, $fn);
    }

    /**
     * Mounts a collection of callbacks onto a base route.
     *
     * @param string   $baseRoute The route sub pattern to mount the callbacks on
     * @param callable $fn        The callback method
     */
    public function mount(string $baseRoute, callable $fn): void
    {
        // Track current base route
        $curBaseRoute = $this->baseRoute;

        // Build new base route string
        $this->baseRoute .= $baseRoute;

        // Call the callable
        call_user_func($fn);

        // Restore original base route
        $this->baseRoute = $curBaseRoute;
    }

    /**
     * Get all request headers.
     *
     * @return array The request headers
     */
    public function getRequestHeaders(): array
    {
        $headers = array();

        // If getallheaders() is available, use that
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            // getallheaders() can return false if something went wrong
            if ($headers !== false) {
                return $headers;
            }
        }

        // Method getallheaders() not available or went wrong: manually extract 'm
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get the request method used, taking overrides into account.
     *
     * @return string The Request method to handle
     */
    public function getRequestMethod(): string
    {
        // Take the method as found in $_SERVER
        $method = $_SERVER['REQUEST_METHOD'];

        // If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_start();
            $method = 'GET';
        }

        // If it's a POST request, check for a method override header
        elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $headers = $this->getRequestHeaders();
            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }

        return $method;
    }

    /**
     * Execute the router: Loop all defined before middleware's and routes, and execute the handling function if a match was found.
     *
     * @return bool
     */
    public function run(): bool
    {
        // Define which method we need to handle
        $this->requestedMethod = $this->getRequestMethod();

        $this->response = new Response();

        // Handle all before middlewares
        if (isset($this->beforeRoutes[$this->requestedMethod])) {
            $this->handle($this->beforeRoutes[$this->requestedMethod]);
        }

        // Handle all routes
        $numHandled = 0;
        if (isset($this->routes[$this->requestedMethod])) {
            $numHandled = $this->handle($this->routes[$this->requestedMethod], true);
        }

        // If no route was handled, trigger the 404 (if any)
        if ($numHandled === 0) {
            $this->response = new Response();
            $this->trigger404([$this->requestedMethod]);
        } // If a route was handled, perform the finish callback (if any)
        else {
            // Handle all after middlewares
            if (isset($this->afterRoutes[$this->requestedMethod])) {
                $numHandled = $this->handle($this->afterRoutes[$this->requestedMethod]);
            }
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        } else {
            $this->emit();
        }

        // Return true if a route was handled, false otherwise
        return $numHandled !== 0;
    }

    private function emit(): void
    {
        (new SapiEmitter())->emit($this->response);
    }

    /**
     * Set the 404 handling function.
     *
     * @param string $match_fn The function to be executed
     * @param string|callable $fn The function to be executed
     */
    public function set404(string $match_fn, string|callable $fn = null): void
    {
      if (!is_null($fn)) {
        $this->notFoundCallback[$match_fn] = $fn;
      } else {
        $this->notFoundCallback['/'] = $match_fn;
      }
    }

    /**
     * Triggers 404 response
     *
     * @param string|array $pattern A route pattern such as /about/system
     */
    public function trigger404(string|array $match = null): void
    {
        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // handle 404 pattern
        if (count($this->notFoundCallback) > 0)
        {
            // loop fallback-routes
            foreach ($this->notFoundCallback as $route_pattern => $route_callable) {

              // matches result
              $matches = [];

              // check if there is a match and get matches as $matches (pointer)
              $is_match = $this->patternMatches($route_pattern, $this->getCurrentUri(), $matches, PREG_OFFSET_CAPTURE);

              // is fallback route match?
              if ($is_match) {

                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(function ($match, $index) use ($matches) {

                  // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                  if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                    if ($matches[$index + 1][0][1] > -1) {
                      return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                    }
                  } // We have no following parameters: return the whole lot

                  return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                $this->invoke($route_callable);

                ++$numHandled;
              }
            }
        }
        if (($numHandled == 0) && (isset($this->notFoundCallback['/']))) {
            $this->invoke($this->notFoundCallback['/']);
        } elseif ($numHandled == 0) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        }
    }

    /**
    * Replace all curly braces matches {} into word patterns (like Laravel)
    * Checks if there is a routing match
    *
    * @param string     $pattern
    * @param string     $uri
    * @param array|null $matches
    *
    * @return bool -> is match yes/no
    */
    private function patternMatches(string $pattern, string $uri, array|null &$matches): bool
    {
      // Replace all curly braces matches {} into word patterns (like Laravel)
      $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      // we may have a match!
      return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
    }

    /**
     * Handle a a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array     $routes       Collection of route patterns and their handling functions
     * @param bool      $quitAfterRun Does the handle function need to quit after one route was matched?
     *
     * @return int The number of routes handled
     */
    private function handle(array $routes, $quitAfterRun = false)
    {
        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // The current page URL
        $uri = $this->getCurrentUri();

        // Loop all routes
        foreach ($routes as $route) {
            // get routing matches
            $is_match = $this->patternMatches($route['pattern'], $uri, $matches);

            // is there a valid match?
            if ($is_match) {

                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(function ($match, $index) use ($matches) {

                    // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        if ($matches[$index + 1][0][1] > -1) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        }
                    } // We have no following parameters: return the whole lot

                    return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                // Call the handling function with the URL parameters if the desired input is callable
                $this->invoke($route['fn'], $params);

                ++$numHandled;

                // If we need to quit, then quit
                if ($quitAfterRun) {
                    break;
                }
            }
        }

        // Return the number of routes handled
        return $numHandled;
    }

    private function invoke($fn, $params = array())
    {
        if (is_callable($fn)) {
            $response = call_user_func_array($fn, [$this->response, ...$params]);

        } else if (stripos($fn, '@') !== false) {
            // Explode segments of given route
            list($controller, $method) = explode('@', $fn);

            // Adjust controller class if namespace has been set
            if ($this->getNamespace() !== '') {
                $controller = $this->getNamespace() . '\\' . $controller;
            }

            try {
                $reflectedMethod = new ReflectionMethod($controller, $method);
                // Make sure it's callable
                if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                    if ($reflectedMethod->isStatic()) {
                        $response = forward_static_call_array(array($controller, $method), [$this->response, ...$params]);
                    } else {
                        // Make sure we have an instance, because a non-static method must not be called statically
                        if (\is_string($controller)) {
                            $controller = new $controller();
                        }

                        $response = call_user_func_array(array($controller, $method), [$this->response, ...$params]);
                    }
                }
            } catch (\ReflectionException $reflectionException) {
                // The controller class is not available or the class does not have the method $method
            }
        }

        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException("Your route handler must return an instance of ResponseInterface.");
        }

        $this->response = $response;

        if ($this->response instanceof RedirectResponse) {
            $this->emit();
        }
    }

    /**
     * Define the current relative URI.
     *
     * @return string
     */
    public function getCurrentUri()
    {
        // Get the current Request URI and remove rewrite base path from it (= allows one to run the router in a sub folder)
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBasePath()));

        // Don't take query params into account on the URL
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // Remove trailing slash + enforce a slash at the start
        return '/' . trim($uri, '/');
    }

    /**
     * Return server base Path, and define it if isn't defined.
     *
     * @return string
     */
    public function getBasePath()
    {
        // Check if server base path is defined, if not define it.
        if ($this->serverBasePath === null) {
            $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return $this->serverBasePath;
    }

    /**
     * Explicitly sets the server base path. To be used when your entry script path differs from your entry URLs.
     * @see https://github.com/bramus/router/issues/82#issuecomment-466956078
     *
     * @param string
     */
    public function setBasePath($serverBasePath)
    {
        $this->serverBasePath = $serverBasePath;
    }
}
