<?php

use Espo\Core\Container;
use Espo\Core\DataManager;

/**
 * Rebuilds metadata and the database so subscription tables exist after install.
 */
class AfterInstall
{
    public function run(Container $container): void
    {
        $container->getByClass(DataManager::class)->rebuild();
    }
}
