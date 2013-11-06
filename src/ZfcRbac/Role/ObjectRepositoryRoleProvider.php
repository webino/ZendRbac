<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbac\Role;

use Doctrine\Common\Persistence\ObjectRepository;
use RecursiveIteratorIterator;
use Zend\Permissions\Rbac\RoleInterface;
use ZfcRbac\Service\RbacEvent;

/**
 * Role provider that uses Doctrine object repository to fetch roles
 *
 * This provider can be used for small applications that do not have a lot of roles, as everything
 * is loaded in memory. The loaded entity must implement Zend\Permissions\Rbac\RoleInterface
 */
class ObjectRepositoryRoleProvider implements RoleProviderInterface
{
    /**
     * @var ObjectRepository
     */
    protected $objectRepository;

    /**
     * Constructor
     *
     * @param ObjectRepository $objectRepository
     */
    public function __construct(ObjectRepository $objectRepository)
    {
        $this->objectRepository = $objectRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(RbacEvent $event)
    {
        $loadedRoles  = $this->objectRepository->findAll();
        $cleanedRoles = array();

        // @TODO: if we have more provider, we likely should move this code to a trait or an abstract class
        foreach ($loadedRoles as $role) {
            if (!$role instanceof RoleInterface) {
                continue;
            }

            if ($parent = $role->getParent()) {
                $cleanedRoles[$role->getName()][] = $parent->getName();
            } else {
                $cleanedRoles[$role->getName()] = array();
            }
        }

        return $cleanedRoles;
    }
}