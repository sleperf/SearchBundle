<?php

namespace Orange\SearchBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * This class contains common methods for the plugin install/uninstall commands.
 */
class IndexResourceCommand extends ContainerAwareCommand
{
private $container;

    protected function configure()
    {
        parent::configure();
        $this->setName('claroline:plugins:fixtures')
            ->setDescription('Install fixtures for a specified claroline plugin.');
    }

}
