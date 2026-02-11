<?php

namespace CPE\Framework;

class Route
{
    public string $url;
    public string $urlRegexp;
    public string $controller;
    public string $action;
    public string $method;
    public array $requiredParams;
    public array $foundParams;
    public array $additionalParams;
    public array $optionalParams;

    public function __construct(string $method, string $url, string $controller, string $action = 'index')
    {
        $this->method = $method;
        $this->url = $url;
        list($this->urlRegexp, $this->requiredParams) = $this->getUrlRegex();
        $this->controller = $controller;
        $this->action = $action;
        $this->foundParams = [];
        $this->additionalParams = [];
        $this->optionalParams = [];
    }

    public function addOptionalParam($name): Route
    {
        $this->optionalParams[$name] = null;
        return $this;
    }

    public function setAdditionalParam($name, $value): Route
    {
        $this->additionalParams[$name] = $value;
        return $this;
    }

    private function getUrlRegex(): array
    {
        $requiredParams = [];

        //handle parameter types
        $regexp = preg_replace_callback(
            "/{(\w+)\:(\w+)}/",
            function ($matches) use (&$requiredParams) {

                $requiredParams[] = $matches[2];

                //handle parameter type
                if ($matches[1] == 'int') {
                    return '(\d+)';
                } elseif ($matches[1] == 'string') {
                    return '(.+)';
                } else {
                    return $matches[0];
                }
            },
            str_replace('.', '\.', $this->url)
        );
        
        return [$regexp, $requiredParams];
    }
}