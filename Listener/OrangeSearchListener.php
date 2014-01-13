<?php

namespace Orange\SearchBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Resource\ResourceShortcut;
use Claroline\CoreBundle\Entity\Resource\ResourceRights;
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use Claroline\CoreBundle\Entity\AnnouncementAggregate;
use Claroline\CoreBundle\Entity\Log\Log;
use Claroline\CoreBundle\Entity\UserMessage;
use Claroline\ForumBundle\Entity\Message;
use Claroline\ForumBundle\Entity\Subject;
use Claroline\ForumBundle\Entity\Forum;
//use Claroline\CoreBundle\Form\FileType;
//use Claroline\CoreBundle\Event\Log\LogResourceCreate;
//use Symfony\Component\Security\Core\SecurityContextInterface;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;

/**
 * @DI\Service
 */
class OrangeSearchListener extends ContainerAware
{
    protected $configSolr;

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
		$entity = $event->getObject();
        $entityManager = $event->getEntityManager();

        $config = $entityManager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}
		
		$configSolr = array(
			'endpoint' => array(
				'localhost' => array(
					'host' => $config[0]->getHost(),
					'port' => $config[0]->getPort(),
					'path' => $config[0]->getPath()
				)
			)
		);		

		if (($entity instanceof File) || ($entity instanceof Directory) || ($entity instanceof Forum))
		{
		// $b=0;
		// $a = 10/$b;
			$this->indexDeleteNodeEntry($entity->getResourceNode(), $configSolr);
		}
	}

	
    /**
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(LifecycleEventArgs $event)
    {
			// $b=0;
			// $a = 10/$b;
			$this->postPersist($event);
	}

    /**
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        $entityManager = $event->getEntityManager();

        $config = $entityManager->getRepository('OrangeSearchBundle:SearchConfig')->findAll();
        if (count($config)==0) 
		{
			// lancer une exception
            throw new InvalidConfigurationException(InvalidConfigurationException::MISSING_CONFIG);
		}
		
		$configSolr = array(
			'endpoint' => array(
				'localhost' => array(
					'host' => $config[0]->getHost(),
					'port' => $config[0]->getPort(),
					'path' => $config[0]->getPath()
				)
			)
		);		
		
		if ($entity instanceof File) 
		{
			//\Doctrine\Common\Util\Debug::dump(var_export ($evtm,true));
			$mimeType = $entity->getMimeType();

			// Extension for which we index the file content or metadata content
			if (($mimeType == "image/png") || 
				($mimeType == "application/pdf") || 
				($mimeType == "application/octet-stream"))
			{
				$this->indexResource($entity->getResourceNode(), $entity->getHashName(), $configSolr);
			}
			else
			{
				$this->indexResource($entity->getResourceNode(), "", $configSolr);
			}
		}
		else if ($entity instanceof Directory) 
		{
			$this->indexResource($entity->getResourceNode(), "", $configSolr);
		}
		else if ($entity instanceof ResourceNode) 
		{
			$this->indexResource($entity, "", $configSolr);
		}
		else if ($entity instanceof ResourceRights) 
		{
			// TODO
		}
		else if ($entity instanceof UserMessage) 
		{
			// TODO
		}
		else if ($entity instanceof Message) 
		{
			// TODO
		}
		else if ($entity instanceof ResourceShortcut) 
		{
			$this->indexForum($entity->getResourceNode(), $configSolr);
		}
		else if ($entity instanceof Message) 
		{
			$this->indexForumMessage($entity, $configSolr);
		}
		else if ($entity instanceof Subject) 
		{
			$this->indexForumSubject($entity, $configSolr);
		}
		else if ($entity instanceof Forum) 
		{
			$this->indexForum($entity->getResourceNode(), $configSolr);
		}
		else if ($entity instanceof Text) 
		{
			$this->indexForum($entity->getResourceNode(), $configSolr);
		}
		else if ($entity instanceof AnnouncementAggregate) 
		{
			$this->indexForum($entity->getResourceNode(), $configSolr);
		}
		else
		{
			// DEBUG si autre ressource que celle qu'on indexe pas
			if (!($entity instanceof Log) &&
				!($entity instanceof Revision))
			{
				\Doctrine\Common\Util\Debug::dump($entity);
			}
		}
	}

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
	{
	}

    function indexResource(ResourceNode $node, $filename, $configSolr)
    {
		$client = new \Solarium\Client($configSolr);
		
		if ($filename != "")
		{
			// get an extract query instance and add settings
			$query = $client->createExtract();
			$query->addFieldMapping('fmap.content', 'content');
			$query->setUprefix('attr_');
			$query->setFile('/var/www/html/claroline/20131113/Claroline/files/'.$filename);
			$query->setCommit(true);
			$query->setOmitHeader(false);

			// add document
			$doc = $query->createDocument();
			$doc->id = 'resource-'.$node->getId();
			$doc->name = $node->getName();
			$doc->content_type = 'resource';
			$doc->resource_id = $node->getId();
			$doc->wks_id = $node->getWorkspace()->getId();
			$doc->mime_type = $node->getMimeType();
			$doc->res_icon_location= $node->getIcon()->getRelativeUrl();
			$doc->type= $node->getResourceType()->getName(); // ou getId()
			$doc->user_id= $node->getCreator()->getId();
			$doc->username= $node->getCreator()->getUsername();
			$doc->user_id= $node->getCreator()->getId();
			$doc->first_name= $node->getCreator()->getFirstName();
			$doc->last_name= $node->getCreator()->getLastName();
			$doc->attr_filename = $filename;
			//$doc->attr_filename_path = $node->getPath();
			//$doc->dir = $this->getContainer()->getParameter('claroline.param.files_directory');
			//$container = $this->getContainer()->getParameter('claroline.param.files_directory');
			//$container = $this->getContainer();
			$query->setDocument($doc);

			// this executes the query and returns the result
			$result = $client->extract($query);
		}
		else
		{
			$update = $client->createUpdate();
			// create a new document for the data
			$doc = $update->createDocument();
			$doc->id = 'resource-'.$node->getId();
			$doc->name = $node->getName();
			$doc->content_type = 'resource';
			$doc->resource_id = $node->getId();
			$doc->wks_id = $node->getWorkspace()->getId();
			$doc->mime_type = $node->getMimeType();
			//$doc1->username= var_export($rights,true);
			$doc->res_icon_location= $node->getIcon()->getRelativeUrl();
			$doc->type= $node->getResourceType()->getName(); // ou getId()
			$doc->user_id= $node->getCreator()->getId();
			$doc->username= $node->getCreator()->getUsername();
			$doc->first_name= $node->getCreator()->getFirstName();
			$doc->last_name= $node->getCreator()->getLastName();

			// add the documents and a commit command to the update query
			$update->addDocuments(array($doc));
			$update->addCommit();

			// this executes the query and returns the result
			$result = $client->update($update);
		}
	}
	
    function indexForumMessage(Message $message, $configSolr)
    {
		$client = new \Solarium\Client($configSolr);
		$update = $client->createUpdate();
		
		// create a new document for the data
		$doc1 = $update->createDocument();
		$doc1->id = 'forum-message-'.$message->getId();
		$doc1->name = $message->getSubject()->getTitle();
		$doc1->content_type = 'forum-message';
		$doc1->content= $message->getContent();
		$doc1->username= $message->getCreator()->getUsername();
		$doc1->user_id= $message->getCreator()->getId();
		$doc1->first_name= $message->getCreator()->getFirstName();
		$doc1->last_name= $message->getCreator()->getLastName();
		$doc1->attr_date = $message->getCreationDate();
		$doc1->wks_id = $message->getSubject()->getForum()->getResourceNode()->getWorkspace()->getId();
		$doc1->mime_type = $message->getSubject()->getForum()->getResourceNode()->getMimeType();
		$doc1->res_icon_location= $message->getSubject()->getForum()->getResourceNode()->getIcon()->getRelativeUrl();
		$doc1->type= $message->getSubject()->getForum()->getResourceNode()->getResourceType()->getName(); // ou getId()
		$doc1->resource_id = $message->getSubject()->getForum()->getResourceNode()->getId();
		$doc1->subject_id = $message->getSubject()->getId();

		// add the documents and a commit command to the update query
		$update->addDocuments(array($doc1));
		$update->addCommit();

		// this executes the query and returns the result
		$result = $client->update($update);
	}
	
    function indexForum(ResourceNode $node, $configSolr)
    {
		$client = new \Solarium\Client($configSolr);
		$update = $client->createUpdate();
		
		// create a new document for the data
		$doc1 = $update->createDocument();
		$doc1->id = 'resource-'.$node->getId();
		$doc1->name = $node->getName();
		$doc1->content_type = 'forum';
		$doc1->resource_id = $node->getId();
		$doc1->username= $node->getCreator()->getUsername();
		$doc1->user_id= $node->getCreator()->getId();
		$doc1->first_name= $node->getCreator()->getFirstName();
		$doc1->last_name= $node->getCreator()->getLastName();
		$doc1->attr_date = $node->getCreationDate();
		$doc1->wks_id = $node->getWorkspace()->getId();
		$doc1->mime_type = $node->getMimeType();
		$doc1->res_icon_location= $node->getIcon()->getRelativeUrl();
		$doc1->type= $node->getResourceType()->getName(); // ou getId()
		
		// add the documents and a commit command to the update query
		$update->addDocuments(array($doc1));
		$update->addCommit();

		// this executes the query and returns the result
		$result = $client->update($update);
	}

    function indexForumSubject(Subject $subject, $configSolr)
    {
		$client = new \Solarium\Client($configSolr);
		$update = $client->createUpdate();
		
		// create a new document for the data
		$doc1 = $update->createDocument();
		$doc1->id = 'forum-subject-'.$subject->getId();
		$doc1->name = $subject->getTitle();
		$doc1->content_type = 'forum-subject';
		$doc1->resource_id = $subject->getForum()->getResourceNode()->getId();
		$doc1->username= $subject->getCreator()->getUsername();
		$doc1->user_id= $subject->getCreator()->getId();
		$doc1->first_name= $subject->getCreator()->getFirstName();
		$doc1->last_name= $subject->getCreator()->getLastName();
		$doc1->attr_date = $subject->getCreationDate();
		$doc1->wks_id = $subject->getForum()->getResourceNode()->getWorkspace()->getId();
		$doc1->mime_type = $subject->getForum()->getResourceNode()->getMimeType();
		$doc1->res_icon_location= $subject->getForum()->getResourceNode()->getIcon()->getRelativeUrl();
		$doc1->type= $subject->getForum()->getResourceNode()->getResourceType()->getName(); // ou getId()
		$doc1->subject_id = $subject->getId();

		// add the documents and a commit command to the update query
		$update->addDocuments(array($doc1));
		$update->addCommit();

		// this executes the query and returns the result
		$result = $client->update($update);
	}

    function indexDeleteNodeEntry(ResourceNode $node, $configSolr)
    {
		$client = new \Solarium\Client($configSolr);

		// We delete the ressource
		$update = $client->createUpdate();
		$update->addDeleteQuery('id:'.'resource-'.$node->getId());
		$update->addCommit();
		$result = $client->update($update);

		// and the child of this ressource
		// TODO : necessaire ?
		$update = $client->createUpdate();
		$update->addDeleteQuery('resource_id:'.$node->getId());
		$update->addCommit();
		$result = $client->update($update);
	}
}