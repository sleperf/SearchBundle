<?php

namespace Orange\SearchBundle\DataFixtures;

use Orange\SearchBundle\Entity\SearchConfig;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

class LoadConfigData extends AbstractFixture
{
     /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
		$config = $manager->getRepository('OrangeSearchBundle:SearchConfig')->find(1);
		if (!$config) {
			$config = new SearchConfig();
		}
        $config->setHost('p-mooc-dev');
        $config->setPort('8984');
        $config->setPath('/solr/core0/');
        $manager->persist($config);
        $manager->flush();
		
    }
}
