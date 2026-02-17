<?php

namespace CPE\Framework;

/**
 * All application components (controllers, models, managers, etc.)
 * extends this so they can access the app
 */
abstract class AbstractComponent
{
    protected AbstractApplication $app;

    public function __construct(AbstractApplication $app)
    {
        $this->app = $app;
    }
}
