<?php

namespace CPE\Framework;

/**
 * Base for all controllers
 */
abstract class AbstractController extends AbstractComponent
{
    protected string $action = '';
    protected array $parameters = [];
    protected array $actions;

    /**
     * Execute the given action with the given parameters
     * @param string $action action name
     * @param array $parameters request parameters
     */
    public function execute(string $action, array $parameters)
    {
        if (empty($action)) {
            $action = "index";
        }

        // remember action and paramters
        $this->action = $action;
        $this->parameters = is_array($parameters) ? $parameters : [];

        // execute action
        $this->$action();
    }

    /**
     * Default action
     */
    public function index()
    {
    }
}
