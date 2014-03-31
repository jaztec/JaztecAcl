<?php

namespace JaztecAclTest\Service;

use JaztecAclTest\Bootstrap;
use PHPUnit_Framework_TestCase;

class AclServiceTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    protected $serviceManager;

    /**
     * @var \JaztecAcl\Service\AclService
     */
    protected $aclService;

    public function setUp()
    {
        Bootstrap::setUpAclDatabase();

        $this->serviceManager = Bootstrap::getServiceManager();
        $em                   = Bootstrap::getServiceManager()->get('doctrine.entitymanager.orm_default');
        /* @var $em \Doctrine\ORM\EntityManager */

        $aclService = $this->serviceManager->get('jaztec_acl_service');
        $aclService->setEntityManager($em);

        // Configure the ACL object needed by our AclService.
        $aclService->getAcl()->removeResourceAll();
        $aclService->getAcl()->removeRoleAll();
        $aclService->getAcl()->setupAcl($em);
        /* @var $aclService \JaztecAcl\Service\AclService */

        // Add a test resource to the ACL
        if (!$aclService->getAcl()->hasResource('resource01')) {
            $aclService->getAcl()->addResource('resource01');
        }

        $this->aclService = $aclService;
    }

    /**
     * @covers \JaztecAcl\Service\AclService::getAcl
     */
    public function testAclClass()
    {
        // Testing for a good ACL object.
        $this->assertInstanceOf('\JaztecAcl\Acl\Acl', $this->aclService->getAcl());
    }

    /**
     * @covers \JaztecAcl\Service\AclService::isAllowed
     */
    public function testControlList()
    {
        // Testing default capabilities
        $this->aclService->getAcl()->allow('guest', 'resource01');
        $this->aclService->getAcl()->deny('additionalRole', 'resource01');

        // Testing for solid control list.
        $this->assertTrue($this->aclService->isAllowed('guest', 'resource01', ''));
        $this->assertFalse($this->aclService->isAllowed('additionalRole', 'resource01', ''));
        $this->assertTrue($this->aclService->isAllowed('member', 'resource01', ''));
    }

    /**
     * @covers \JaztecAcl\Service\AclService::isAllowed
     */
    public function testControlDetail()
    {
        // Testing special capabilities.
        $this->aclService->getAcl()->deny();
        $this->aclService->getAcl()->allow('additionalRole');

        // Are is the right role permitted?
        $this->assertFalse($this->aclService->isAllowed('guest', 'resource01', ''), 'The guest role should no longer have access to the resource');
        $this->assertFalse($this->aclService->isAllowed('member', 'resource01', ''), 'A role extended from the guest role should not have access to the resource.');
        $this->assertTrue($this->aclService->isAllowed('additionalRole', 'resource01', ''), 'The ACL should allow this role because it as all rights.');
    }

    /**
     * @covers \JaztecAcl\Service\AclService::isAllowed
     */
    public function testServiceResourceCreation()
    {
        // Prepare the ACL
        $this->aclService->getAcl()->deny();
        $this->aclService->getAcl()->allow('additionalRole');

        $this->aclService->isAllowed('additionalRole', 'resource99', 'index');
        $this->assertTrue($this->aclService->getAcl()->hasResource('resource99'), "The test resource should have been added to the database");
    }

    /**
     * @covers \JaztecAcl\Service\AclService::isAllowed
     */    
    public function testServicePrivilegeRequestCreation()
    {
        // Prepare the ACL
        $this->aclService->getAcl()->deny();
        $this->aclService->getAcl()->allow('additionalRole');

        $this->aclService->isAllowed('additionalRole', 'resource50', 'index');

        $em = Bootstrap::getServiceManager()->get('doctrine.entitymanager.orm_default');
        /* @var $em \Doctrine\ORM\EntityManager */
        $requests = $em->getRepository('JaztecAcl\Entity\RequestedPrivilege')->findBy(
            array(
                'privilege' => 'index',
                'resource'  => 'resource50',
            )
        );
        /* @var $requests array */

        $this->assertGreaterThan(0, count($requests), "The newly added privilege should exist in the database");
    }
}
