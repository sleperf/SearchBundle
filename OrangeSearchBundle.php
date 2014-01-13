<?php

namespace Orange\SearchBundle;

use Claroline\CoreBundle\Library\PluginBundle;
use Claroline\KernelBundle\Bundle\ConfigurationBuilder;
use Orange\SearchBundle\Listener\OrangeSearchListener;
use Claroline\CoreBundle\Event\CreateResourceEvent;

/**
 * Bundle class.
 */
class OrangeSearchBundle extends PluginBundle
{

    public function getConfiguration($environment)
    {
        $config = new ConfigurationBuilder();

        return $config->addRoutingResource(__DIR__ . '/Resources/config/routing.yml', null, 'search');
    }

    public function getRequiredFixturesDirectory($environment)
    {
        return 'DataFixtures';
    }
    public function getRoutingPrefix()
    {
        return "search";
    }
}