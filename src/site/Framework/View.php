<?php

namespace CPE\Framework;

use LogicException;

/**
 * View handler
 * Class that manages the output of the application
 */
class View extends AbstractComponent
{
    protected array $params;
    protected string $rootUrl;
    protected string $baseUrl;
    protected string $currentUrl;
    protected object $template;
    protected string $templatePath;
    protected bool $ApacheURLRewriting;

    public function __construct(AbstractApplication $app, $ApacheURLRewriting)
    {
        parent::__construct($app);

        $this->params = [];
        $this->ApacheURLRewriting = $ApacheURLRewriting;
        $this->rootUrl = $this->getProtocol() . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

        $this->templatePath = 'App/Templates' . DIRECTORY_SEPARATOR;

        //if there is no URL Rewriting, the route will be put in the $_GET['p']
        $this->baseUrl = $this->rootUrl ?: dirname($_SERVER['PHP_SELF']) . '/';
        $this->baseUrl .= !$this->ApacheURLRewriting ? '?' . $this->app->routeParamName() . '=' : '';

        $this->setParam("templateUrl", $this->rootUrl . 'App/Templates/');
        $this->setParam("rootUrl", $this->rootUrl);
        $this->setParam("baseUrl", $this->baseUrl);
    }

    /**
     * Add or update a parameter to the view
     * @param string $name parameter name
     * @param mixed $value parameter value
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * Build a route based on the base URL and the given format and includes the arguments (works like sprintf() )
     * @param string $routeFormat
     * @param mixed ...$args
     * @return string
     */
    public function buildRoute(string $routeFormat, ...$args): string
    {
        return $this->baseUrl . ltrim(sprintf(...func_get_args()), '/');
    }

    /**
     * Assign all parameters to the view, then render it
     * @param string $name view name
     */
    public function render(string $name)
    {
        //template file not found
        if (file_exists($this->templatePath . $name) === false) {
            throw new LogicException(sprintf('Template not found: "%s"', $name));
        }

        //import the parameters into the current context
        extract($this->params);

        ob_start();
        include $this->templatePath . $name;
        $response = ob_get_clean();

        echo $response;
        exit;
    }

    private function getProtocol(): string
    {
        return !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on'
        || !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'
            ? 'https://'
            : "http://";
    }
}
