<?php

namespace Orange\SearchBundle\Controller;

use Orange\SearchBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
//use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Nelmio\SolariumBundle\DependencyInjection\NelmioSolariumExtension;
use Nelmio\SolariumBundle\DependencyInjection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Orange\SearchBundle\DataFixtures\LoadConfigData;
use Orange\SearchBundle\Converter\InvalidConfigurationException;
use Orange\SearchBundle\Entity\SearchConfig;
use Orange\SearchBundle\Manager\SearchManager;

use Solarium\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
// Test
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Orange\SearchBundle\Listener\OrangeSearchListener;
use Claroline\CoreBundle\Event\LogCreateEvent;
use Claroline\CoreBundle\Entity\Log\Log;
use Claroline\CoreBundle\Event\CreateResourceEvent;
use Claroline\CoreBundle\Pager\PagerFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;
// use Claroline\CoreBundle\Library\Security\Utilities;
// use Claroline\CoreBundle\Library\Security\TokenUpdater;

class SearchController extends Controller
{
    private $pagerFactory;
    protected $configSolr;
    private $security;

    /**
     * @DI\InjectParams({
     *     "pagerFactory"        = @DI\Inject("claroline.pager.pager_factory"),
     *     "security"           = @DI\Inject("security.context")
     * })
     */
    public function __construct(
        PagerFactory $pagerFactory,
        SecurityContextInterface $security
    )
    {
        $this->pagerFactory = $pagerFactory;
        $this->security = $security;
    }
	
	
    /**
     * @EXT\Route(
     *     "/config",
     *     name = "orange_search_config"
     * )
     *
     * @param Request $request
     *
     * @return Response
     */
    public function configAction(Request $request)
    {
		$this->assertIsGranted('ROLE_USER');
		
		$manager = $this->getDoctrine()->getManager();
		// Chargement des valeurs par defaut
		// $configData = new LoadConfigData();
		// $configData->load($manager);
        $config = $manager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}

		// Initialisation du formulaire
		$form = $this->createFormBuilder($config[0])
			->add('host', 'text', array(
			'constraints' => new NotBlank())
			)
			->add('port', 'text',array(
			'constraints' => array (new NotBlank(), new Length(array('min' => 4, 'max' => 4)))
			))
			->add('path', 'text')
			->add('save', 'submit')
			->getForm();		
		
		// Traitement de la réponse
		$form->handleRequest($request);
		if ($form->isValid()) {
			// Les données sont un tableau avec les clés "name", "email", et "message"
			//$data = $form->getData();
			// On enregistre les modifs
			$manager->flush();
			//return $this->redirect($this->generateUrl('orange_search_request', array('name' => 'test')));
		}		
		
		return $this->render('OrangeSearchBundle:ConfigSolr:index.html.twig', array('name' => $config[0], 'form' => $form->createView()));
	}
    public function statsAction()
    {
		$manager = $this->getDoctrine()->getManager();
		// Chargement des valeurs par defaut
		// $configData = new LoadConfigData();
		// $configData->load($manager);
        $config = $manager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}
		
		$config = array(
			'endpoint' => array(
				'localhost' => array(
					'host' => $config[0]->getHost(),
					'port' => $config[0]->getPort(),
					'path' => $config[0]->getPath()
					//'proxy' => '10.193.21.179:3128'
				)
			)
		);		
		// create a client instance
		$client = new \Solarium\Client($config);
		$client->setAdapter('Solarium\Core\Client\Adapter\Curl');

		return $this->render('OrangeSearchBundle:AdminSolr:index.html.twig', array('message' => 'Statistique'));
	}
    public function deleteIndexAction()
    {
		$manager = $this->getDoctrine()->getManager();
		// Chargement des valeurs par defaut
		// $configData = new LoadConfigData();
		// $configData->load($manager);
        $config = $manager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}
		
		$config = array(
			'endpoint' => array(
				'localhost' => array(
					'host' => $config[0]->getHost(),
					'port' => $config[0]->getPort(),
					'path' => $config[0]->getPath()
					//'proxy' => '10.193.21.179:3128'
				)
			)
		);		
		// create a client instance
		$client = new \Solarium\Client($config);
		//$client->setAdapter('Solarium\Core\Client\Adapter\Curl');

		// get an update query instance
		$update = $client->createUpdate();

		// add the delete query and a commit command to the update query
		$update->addDeleteQuery('*:*');
		$update->addCommit();
		$result = $client->update($update);
		return $this->render('OrangeSearchBundle:AdminSolr:index.html.twig', array('message' => 'Totalité de l\'index supprimé'));
	}

    /**
     * @EXT\Route(
     *     "/request/{name}/page/{page}/nb/{nbByPage}",
     *     name = "orange_search_request",
     *     defaults={"page"=1, "nbByPage"=5}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template("OrangeSearchBundle:Search:reponse.html.twig")
     *
     * @return Response
     */
    public function requestAction($name, $page, $nbByPage)
    {
		$this->assertIsGranted('ROLE_USER');
	
		$manager = $this->getDoctrine()->getManager();
		// Chargement des valeurs par defaut
		// $configData = new LoadConfigData();
		// $configData->load($manager);
        $config = $manager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}
		
		$config = array(
			'endpoint' => array(
				'localhost' => array(
					'host' => $config[0]->getHost(),
					'port' => $config[0]->getPort(),
					'path' => $config[0]->getPath()
				)
			)
		);		

		$client = new \Solarium\Client($config);
        
		$select = $client->createSelect();
		//$client->setAdapter('Solarium\Core\Client\Adapter\Curl');
		
		// get the facetset component
		$facetSet = $select->getFacetSet();

		// create a facet field instance and set options
		$facetSet->createFacetField('content-type')->setField('content_type');
		$facetSet->createFacetField('wks')->setField('wks_id');

		$select->setQuery($name);
		$select->setStart(((int)$page-1)*$nbByPage)->setRows($nbByPage);
		$select->setOmitHeader(false);
		
		$request = $client->createRequest($select)->addParam('qt','claroline');
		$response = $client->executeRequest($request);
		$results = $client->createResult($select, $response);		
		
		$nb = $results->getNumFound();
		$time = 0;
// display facet results
$facetResult = $results->getFacetSet()->getFacet('content-type');
$facetResultWks = $results->getFacetSet()->getFacet('wks');
$facetWks = array();
foreach ($facetResultWks as $key => $frWks) {
		$wks = $manager->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($key);
	$facetWks[$wks->getName()] = $frWks;
}
	// echo "<PRE>";
    // print_r($facetResultWks);
	// echo "</PRE>";
		
		
		// display the total number of documents found by solr
//echo 'NumFound: '.$results->getNumFound();
  // echo "<PRE>";
  // print_r($results);
  // echo "</PRE>";
		$lr = array();
		foreach ($results as $result)
		{
			$r = array();
			foreach($result AS $field => $value)
			{
				if(is_array($value)) $value = implode(', ', $value);

				$r[$field] = $value;
			}
			
			// Traitement à faire sur la liste réponse
			// Pour un fichier on tronque le content
			if (($r["mime_type"] == "application/pdf") && isset($r["content"])) $r["content"] = substr($r["content"],0,200);
			
			// Lecture du nom du WKS
			$wks = $manager->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($r["wks_id"]);
  // echo "<PRE>";
  // print_r($wks);
  // echo "</PRE>";
			$r["wks_name"] = $wks->getName();
			
			// Lecture du sujet du owner
			if (isset($r["user_id"]))
			{
				$owner = $manager->getRepository('Claroline\CoreBundle\Entity\User')->find($r["user_id"]);
				$r["first_name"] = $owner->getFirstName();
				$r["last_name"] = $owner->getLastName();
			}
			
			// Lecture du sujet du forum si présent
			if (isset($r["subject_id"]))
			{
				$forumSubject = $manager->getRepository('Claroline\ForumBundle\Entity\Subject')->find($r["subject_id"]);
				$r["name"] = $forumSubject->getTitle();
			}
			
			array_push($lr, $r);
			unset($r);
		}

        //$pager = $this->pagerFactory->createPagerFromArray($lr, $page, 5);
		//return $this->render('OrangeSearchBundle:Search:reponse.html.twig', array('name' => $name, 'results' => $lr, 'facets' => $facetResult, 'facetsWks' => $facetWks));
		
		$currentUrl = substr($this->getRequest()->getUri(),0,strpos($this->getRequest()->getUri(), "/search/request"));
		
		
		return array(
			'name' => $name, 
			'nbResults' => $nb,
			'nbByPage' => $nbByPage,
			'page' => $page,
			'results' => $lr, 
			'facets' => $facetResult, 
			'facetsWks' => $facetWks,
			'url' => $currentUrl,
			'time' => $time
		);
    }

    private function assertIsGranted($attributes, $object = null)
    {
        if (false === $this->security->isGranted($attributes, $object)) {
            throw new AccessDeniedException();
        }
    }
	
	
    public function searchAdvancedFormAction($query)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $forum = $em->getRepository('ClarolineForumBundle:Forum')->find($forumId);
        $collection = new ResourceCollection(array($forum->getResourceNode()));

        if (!$this->get('security.context')->isGranted('post', $collection)) {
            throw new AccessDeniedHttpException($collection->getErrorsForDisplay());
        }

        $formSubject = $this->get('form.factory')->create(new SubjectType());

        return array(
            '_resource' => $forum,
            'form' => $formSubject->createView()
        );
    }
	
}