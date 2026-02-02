<?php

namespace CPE\Framework;

use InvalidArgumentException;
use ReflectionClass;

/**
 * Abstract Application
 * Main application will inherits from it and will be called from index.php
 */
abstract class AbstractApplication
{
    protected View $view;
    protected array $managers = [];
    protected string $routeParamName;
    protected Router $router;

    public function __construct()
    {
        $this->routeParamName = 'url';
        $this->initView($this->isApacheURLRewritingEnabled());
    }

    /**
     * Run the application (will call the right controller and action)
     */
    abstract public function run();

    /**
     * Returns a manager (loads it if not already loaded)
     * @param string $module manager name (case insensitive)
     * @return AbstractManager
     */
    public function manager(string $module): AbstractManager
    {
        if (!is_string($module) || empty($module)) {
            throw new InvalidArgumentException('Invalid module');
        }

        if (!isset($this->managers[$module])) {
            $manager = $module . 'Manager';
            if (substr($module, 0, 1) !== '\\') {
                $manager = '\\' . $this->getConcreteNamespace() . '\\Managers\\' . $manager;
            }
            $this->managers[$module] = new $manager($this);
        }

        return $this->managers[$module];
    }

    public function getConcreteNamespace(): string
    {
        return (new ReflectionClass($this))->getNamespaceName();
    }

    /**
     * Initialise the View object
     * @param string $rootUrl
     * @param bool $ApacheURLRewriting
     */
    public function initView(bool $ApacheURLRewriting = false)
    {
        $this->view = new View($this, $ApacheURLRewriting);
    }

    /**
     * Returns the application parameter name used in $_GET
     * @return string name
     */
    public function routeParamName(): string
    {
        return $this->routeParamName;
    }

    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Returns the application view
     * @return object view
     */
    public function view()
    {
        return $this->view;
    }

    /**
     * Gives the root directory of the app (where index.php is located)
     * @return string
     */
    public function rootPath() {
        return dirname(__FILE__);
    }

    public function isApacheURLRewritingEnabled(): bool
    {
        return isset($_SERVER['HTTP_MOD_REWRITE']) && $_SERVER['HTTP_MOD_REWRITE'] == 'On';
    }
}
