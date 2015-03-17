<?php

/**
 * Database driven ACL class.
 *
 * @author Jasper van Herpt
 * @package JaztecAcl\Acl
 */

namespace JaztecAcl\Acl;

use Zend\Permissions\Acl\Acl as ZendAcl;
use Doctrine\ORM\EntityManager;
use JaztecAcl\Entity\Resource as ResourceEntity;

class Acl extends ZendAcl
{

    /** @var boolean $loaded */
    protected $loaded;

    /**
     * @return bool
     */
    public function isLoaded()
    {
        return $this->loaded ? : false;
    }

    /**
     * Build a new ACL object from the database.
     *
     * @param  \Doctrine\ORM\EntityManager $em
     * @return \JaztecAcl\Acl\Acl
     */
    public function setupAcl(EntityManager $em)
    {
        $this->insertRoles($this->findRoles($em))
            ->insertResources($this->findResources($em))
            ->insertPrivileges($this->findPrivileges($em));

        $this->loaded = true;

        return $this;
    }

    /**
     * Insert an array of roles into the current ACL object.
     *
     * @param  array              $roles
     * @return \JaztecAcl\Acl\Acl
     */
    protected function insertRoles(array $roles)
    {
        foreach ($roles as $role) {
            if (null === $role->getParent()) {
                $this->addRole($role);
            } else {
                $parents   = [];
                $parents[] = $role->getParent()->getRoleId();
                $this->addRole($role, $parents);
            }
        }

        return $this;
    }

    /**
     * Inserts an array of resources into the current ACL object.
     *
     * @param  array              $resources
     * @return \JaztecAcl\Acl\Acl
     */
    protected function insertResources(array $resources)
    {
        foreach ($resources as $resource) {
            if (null === $resource->getParent()) {
                $this->addResource($resource);
            } else {
                $parent = $resource->getParent()->getResourceId();
                $this->addResource($resource, $parent);
            }
        }

        return $this;
    }

    /**
     * Setup the privileges.
     *
     * @param  array              $privileges
     * @return \JaztecAcl\Acl\Acl
     */
    protected function insertPrivileges(array $privileges)
    {
        foreach ($privileges as $privilege) {
            $type = $privilege->getType();
            $this->$type(
                $privilege->getRole(), $privilege->getResource(), $privilege->getPrivilege()
            );
        }

        return $this;
    }

    /**
     * Find the roles in the database.
     *
     * @param  \Doctrine\ORM\EntityManager $em
     * @return array
     */
    protected function findRoles(EntityManager $em)
    {
        $roles = $em->getRepository('JaztecAcl\Entity\Role')->findBy(
            [],
            ['sort' => 'ASC']
        );

        return $roles;
    }

    /**
     * Find the resources in the database.
     *
     * @param  \Doctrine\ORM\EntityManager $em
     * @return array
     */
    protected function findResources(EntityManager $em)
    {
        $resources = $em->getRepository('JaztecAcl\Entity\Resource')->findBy(
            [],
            ['sort' => 'ASC']
        );

        return $resources;
    }

    /**
     * Find the privileges in the database.
     *
     * @param  \Doctrine\ORM\EntityManager $em
     * @return array
     */
    protected function findPrivileges(EntityManager $em)
    {
        $privileges = $em->getRepository('JaztecAcl\Entity\Privilege')->findAll();

        return $privileges;
    }

    /**
     *
     * @param  string                            $newResource
     * @param  string|\JaztecAcl\Entity\Resource $baseResource
     * @param  \Doctrine\ORM\EntityManager       $em
     * @return \JaztecAcl\Entity\Resource
     */
    public function createResource($newResource, $baseResource, EntityManager $em)
    {
        // Check if the base resource exists, otherwise create it.
        if (!$baseResource instanceof ResourceEntity &&
            !is_string($baseResource)) {
            throw new \Exception('Base resource is not a valid ACL resource, ' . get_class($baseResource) . ' given.');
        } elseif (!$baseResource instanceof \ResourceEntity) {
            $baseName     = $baseResource;
            $baseResource = $em->getRepository('JaztecAcl\Entity\Resource')->findOneBy(['name' => $baseName]);
            if (!$baseResource instanceof ResourceEntity) {
                $baseResource = new \JaztecAcl\Entity\Resource();
                $baseResource->setName($baseName);
                $baseResource->setSort(0);
                $em->persist($baseResource);
                $this->addResource($baseResource->getResourceId());
            }
        }
        // Checking the new resource on validity
        if (!is_string($newResource)) {
            throw new \Exception('The new resource is not a valid string');
        }

        // Create the new (unknown) resource and add it to the ACL.
        $resource = new \JaztecAcl\Entity\Resource();
        $resource->setName($newResource);
        $resource->setParent($baseResource);
        $resource->setSort($baseResource->getSort() + 1);
        $em->persist($resource);

        $em->flush();

        $this->addResource($resource, $baseResource->getResourceId());

        return $resource;
    }

    /**
     * Checks and adds the privilege request and the resource to the request
     * storage if it doesn't exist.
     * 
     * @param   string                          $privilege
     * @param   string                          $resource
     * @param   \Doctrine\ORM\EntityManager     $em
     * @return  bool
     */
    public function checkPrivilegeRequest($privilege, $resource, EntityManager $em)
    {
        $privilege = trim($privilege);
        $resource = trim($resource);
        // Check the input values.
        if ($resource === '' || $privilege === '') {
            return false;
        }
        // Try to find the privilege request in the database.
        $requestedPrivilege = $em->getRepository('JaztecAcl\Entity\RequestedPrivilege')->findOneBy([
            'privilege' => $privilege,
            'resource'  => $resource,
        ]);
        if ($requestedPrivilege instanceof \JaztecAcl\Entity\RequestedPrivilege) {
            return true;
        }
        // Create the privilege request.
        $newRequestedPrivilege = new \JaztecAcl\Entity\RequestedPrivilege();
        $newRequestedPrivilege
            ->setPrivilege($privilege)
            ->setResource($resource);
        $em->persist($newRequestedPrivilege);
        $em->flush();
        return true;
    }
}
