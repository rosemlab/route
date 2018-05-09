<?php

namespace Rosem\Route;

use Psrnext\Http\Message\RequestMethod;

class RouteCollector
{
    protected const ROUTE_REGEX = 0;

    const ROUTES_CHUNK_LIMIT = 33;
    const REGEX_DELIMITER    = ':';
    const VARIABLE_DELIMITER = ':';
    const ROUTES_SPLIT_REGEX = '/(?:(?>\\\)\/|[^\/\s])+/i';

    private $backtrackLimit; // TODO: add mechanism for regex length limiting

    /**
     * @var array[]
     */
    protected $routes = [];

    protected $count = 0;

    private $suffix;

    protected $prefix = '';

    public function __construct()
    {
        $this->backtrackLimit = ini_get('pcre.backtrack_limit');
//        $this->tail = '/' . str_repeat(' ', self::ROUTES_CHUNK_LIMIT);
        $this->suffix = '/0123456789';//876543210';
    }

    public static function normalize(string $route): string
    {
        return '/' . trim($route, '/');
    }

    public function prefixy(string $route): string
    {
        // check route is relative (without "/") or absolute (with "/")
        return $route[0] === '/' ? static::normalize($route) : $this->prefix . static::normalize($route);
    }

    /**
     * @param string|array          $methods
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function addRoute($methods, string $route, $handler)
    {
        foreach ((array) $methods as $method) {
//        $route = rtrim($route, '/');
            $count = &$this->count;
            $rest = $count % static::ROUTES_CHUNK_LIMIT;
            $regex = '(?:' . preg_replace_callback(static::ROUTES_SPLIT_REGEX, function ($matches) {
                    $node = &$matches[0];
                    if ($node[0] === static::VARIABLE_DELIMITER) {
                        $regexp_parts = explode(static::REGEX_DELIMITER, $node, 3);

                        if (\count($regexp_parts) > 2) {
//                            if (\is_numeric($regexp_parts[1])) {
                                return "($regexp_parts[2])";
//                            }

//                            return "(?<$regexp_parts[1]>$regexp_parts[2])";
                        }

                        return '([^/]+)';
                    }

                    return $node;
                }, $route) . ')/(.*' . ($rest < 10 ? $rest : intdiv($rest, 10) . '.*' . $rest % 10) . ')';
            $route = &$this->routes[(++$count - $rest) / static::ROUTES_CHUNK_LIMIT];
//            ? $route[0] .= "|$regex"
            $rest ? $route[0] = "$regex|$route[0]" : $route = [$regex, $method => []];
            $route[$method][$rest] = $handler;
        }
    }

    /**
     * @param string|\Closure       $prefix
     * @param string|array|\Closure $group
     */
    public function prefix(string $prefix, $group)
    {
        $this->prefix = ($prefix[0] === '/'
            ? static::normalize($prefix)
            : $this->prefix . static::normalize($prefix));
        is_callable($group) ? $group() : call_user_func($group);
        $this->prefix = '';
    }

    /**
     * @param string $method
     * @param string $route
     *
     * @return array
     */
    public function make($method, string $uri): array
    {
//        $uri = rtrim($uri, '/');
        $matches = [];
//        for ($i = count($this->routes) - 1; $i >= 0, $route = &$this->routes[$i]; --$i) {
        foreach ($this->routes as &$route) {
            if (preg_match("~^(?|{$route[self::ROUTE_REGEX]})\d*$~", "$uri$this->suffix", $matches)) {
//                array_shift($matches);

//                return [$route[1][strlen(array_pop($matches))], &$matches];
                $indexStr = array_pop($matches);
                array_shift($matches);
//                unset($matches[0]);

                return [
                    $route[$method][(int) ($indexStr[0] . $indexStr[-1])],
                    &$matches
                ];
            }
        }
//        foreach ($this->routes as &$route) {
//            if (preg_match("~^(?|{$route[0]})~", "$uri$this->tail", $matches)) {
//                array_shift($matches);
//                return [$route[1][strlen(array_pop($matches))], &$matches];
//            }
//        }
        return [function ($errorCode) {
            return "$errorCode Not found";
        }, [404]];
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function get(string $route, $handler)
    {
        $this->addRoute(RequestMethod::GET, $this->prefixy($route), $handler);
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function post(string $route, $handler)
    {
        $this->addRoute(RequestMethod::POST, $this->prefixy($route), $handler);
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function put(string $route, $handler)
    {
        $this->addRoute(RequestMethod::PUT, $this->prefixy($route), $handler);
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function patch(string $route, $handler)
    {
        $this->addRoute(RequestMethod::PATCH, $this->prefixy($route), $handler);
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function delete(string $route, $handler)
    {
        $this->addRoute(RequestMethod::DELETE, $this->prefixy($route), $handler);
    }

    /**
     * @param string                $route
     * @param string|array|\Closure $handler
     */
    public function options(string $route, $handler)
    {
        $this->addRoute(RequestMethod::OPTIONS, $this->prefixy($route), $handler);
    }
}