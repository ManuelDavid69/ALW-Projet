<?php

namespace CPE\Framework;

/**
 * Almighty powerful and wonderful routing class
 */
class Router extends AbstractComponent
{
    protected string $routeParamName;
    protected array $routes;
    protected Route $defaultRoute;
    protected string $file;

    public function __construct(AbstractApplication $app)
    {
        parent::__construct($app);

        $this->routes = [];
        $this->routeParamName = $app->routeParamName();
        $this->file = '';
    }

    public function map(
        string $method,
        string $url,
        string $controller,
        string $action = 'index',
        array $additional = [],
        array $optional = []
    ): Route
    {
        $route = new Route($method, $url, $controller, $action, $additional, $optional);
        $this->routes[] = $route;
        return $route;
    }

    public function mapDefault(string $controller, string $action = 'index'): Route
    {
        $this->defaultRoute = new Route('', '', $controller, $action);
        return $this->defaultRoute;
    }

    /**
     * Returns a route based requested URL
     * @param string $url meaningful part of the url
     */
    public function findRoute(?string $url = null): ?Route
    {
        $foundRoute = null;
        $parameters = [];

        if (is_null($url)) {
            $url = $this->getUrl();
        }
        $method = $_SERVER['REQUEST_METHOD'];

        //check for a predefined route
        foreach ($this->routes as $route) {
            $parameters = $this->matchRoute($method, $url, $route);
            if (!is_null($parameters)) {
                $foundRoute = $route;
                $foundRoute->foundParams = $parameters;
            }
        }

        //add other parameters given after '?' in a subarray
        $get = $_GET;
        unset($get[$this->routeParamName]);
        if (empty($get) === false) {
            $foundRoute->foundParams['get'] = $get;
        }

        if (empty($foundRoute)) {
            $foundRoute = $this->defaultRoute;
        }
        if (empty($foundRoute)) {
            throw new \RuntimeException('no available route found for this URL');
        }

        //return found route and parameters
        return $foundRoute;
    }

    public function getUrl(): string
    {
        // base URL as interpreted by this router
        // (must always start with a "/")
        $url = "/";

        //remove '/' at the end of the url
        if (isset($_GET[$this->routeParamName])) {
            $url .= trim($_GET[$this->routeParamName], "/");
        }

        return $url;
    }

    public function matchRoute(string $method, string $url, Route $route): ?array
    {
        if ($method != $route->method) {
            return null;
        }

        //route doesn't match when including required parameters
        if (preg_match('%^' . $route->urlRegexp . '(/.*)?$%i', $url, $matches) == false) {
            return null;
        }

        $parameters = [];
        $optionalParamsGiven = '';
        
        //remove unnecessary match
        unset($matches[0]);
        //pop optional parameters if they exists
        if (count($matches) > count($route->requiredParams)) {
            $optionalParamsGiven = array_pop($matches);
        }
        //combine required parameter values and names
        if (!empty($route->requiredParams)) {
            $parameters = array_combine($route->requiredParams, $matches);
        }

        //handle additional parameters (constants given via the route definition)
        $parameters = array_merge($parameters, $route->additionalParams);

        //handle optional parameters
        if (!empty($optionalParamsGiven)) {
            //if there's no "/" between required & optional part
            if ($optionalParamsGiven[0] !== "/") {
                return null;
            }

            $optionalParamsGiven = preg_split('%\|%', trim($optionalParamsGiven, '/?'));
            if (count($optionalParamsGiven) == 1 && empty($optionalParamsGiven[0])) {
                unset($optionalParamsGiven[0]);
            }
            $nbOptParam = count($route->optionalParams);

            //route don't really match (maybe has a longer path than the model)
            if (count($optionalParamsGiven) > $nbOptParam) {
                return null;
            }

            //match the remaining ones to optional parameters
            $i = 0;
            foreach ($route->optionalParams as $name => $value) {
                if (isset($optionalParamsGiven[$i])) {
                    $parameters[$name] = $optionalParamsGiven[$i];
                }
                $i++;
            }
        }

        return $parameters;
    }

    /**
     * Returns a controller based on its name
     * @param string $controllerName controller name
     * @return AbstractController controller
     */
    public function getController(string $controllerName): AbstractController
    {
        return new $controllerName($this->app);
    }
}
