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

namespace ZfcRbac\Service;

use RecursiveIteratorIterator;
use Traversable;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\Permissions\Rbac\Rbac;
use ZfcRbac\Assertion\AssertionInterface;
use ZfcRbac\Exception;
use ZfcRbac\Identity\IdentityInterface;
use ZfcRbac\Identity\IdentityProviderInterface;
use Zend\Permissions\Rbac\RoleInterface;

/**
 * Authorization service is a simple service that internally uses a Rbac container
 */
class AuthorizationService implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * @var Rbac
     */
    protected $rbac;

    /**
     * @var IdentityProviderInterface
     */
    protected $identityProvider;

    /**
     * @var string
     */
    protected $guestRole;

    /**
     * Is the container correctly loaded?
     *
     * @var bool
     */
    protected $isLoaded = false;

    /**
     * Should we force reload the roles and permissions each time isGranted is called?
     *
     * This can be used for very complex use cases with tons of roles and permissions, so that
     * it can triggers database queries only for a given role/permission couple
     *
     * @var bool
     */
    protected $forceReload = false;

    /**
     * Constructor
     *
     * @param Rbac                      $rbac
     * @param IdentityProviderInterface $identityProvider
     * @param string                    $guestRole
     */
    public function __construct(Rbac $rbac, IdentityProviderInterface $identityProvider, $guestRole = '')
    {
        $this->rbac             = $rbac;
        $this->identityProvider = $identityProvider;
        $this->guestRole        = $guestRole;
    }

    /**
     * Get the Rbac container
     *
     * @return Rbac
     */
    public function getRbac()
    {
        return $this->rbac;
    }

    /**
     * Set if we should force reload each time isGranted is called
     *
     * @param boolean $forceReload
     * @param void
     */
    public function setForceReload($forceReload)
    {
        $this->forceReload = (bool) $forceReload;
    }

    /**
     * Get the identity roles from the identity, applying some more logic
     *
     * @return string[]|\Zend\Permissions\Rbac\RoleInterface[]
     * @throws Exception\RuntimeException
     */
    public function getIdentityRoles()
    {
        $identity = $this->identityProvider->getIdentity();

        if (null === $identity) {
            return empty($this->guestRole) ? [] : [$this->guestRole];
        }

        if (!$identity instanceof IdentityInterface) {
            throw new Exception\RuntimeException(sprintf(
                'ZfcRbac expects your identity to implement ZfcRbac\Identity\IdentityInterface, "%s" given',
                is_object($identity) ? get_class($identity) : gettype($identity)
            ));
        }

        $roles = $identity->getRoles();

        if ($roles instanceof Traversable) {
            $roles = iterator_to_array($roles);
        }

        return (array) $roles;
    }

    /**
     * Check if a given role satisfy through one of the identity roles (it checks inheritance)
     *
     * @param  string[]|RoleInterface[] $rolesToCheck
     * @return bool
     */
    public function satisfyIdentityRoles(array $rolesToCheck)
    {
        $identityRoles = $this->getIdentityRoles();

        // Too easy...
        if (empty($identityRoles)) {
            return false;
        }

        $this->load($identityRoles);

        $rolesToCheck  = $this->flattenRoles($rolesToCheck);
        $identityRoles = $this->flattenRoles($identityRoles);

        return count(array_intersect($rolesToCheck, $identityRoles)) > 0;
    }

    /**
     * Check if the permission is granted to the current identity
     *
     * @param  string                           $permission
     * @param  callable|AssertionInterface|null $assertion
     * @return bool
     * @throws Exception\InvalidArgumentException If an invalid assertion is passed
     */
    public function isGranted($permission, $assertion = null)
    {
        $roles = $this->getIdentityRoles();

        if (empty($roles)) {
            return false;
        }

        // First load everything inside the container
        $this->load($roles, $permission);

        // Check the assertion first
        if (null !== $assertion) {
            $identity = $this->identityProvider->getIdentity();

            if (is_callable($assertion) && !$assertion($identity)) {
                return false;
            } elseif ($assertion instanceof AssertionInterface && !$assertion->assert($identity)) {
                return false;
            } else {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Assertions must be callable or implement ZfcRbac\Assertion\AssertionInterface, "%s" given',
                    is_object($assertion) ? get_class($assertion) : gettype($assertion)
                ));
            }
        }

        foreach ($roles as $role) {
            // If role does not exist, we consider this as not valid
            if (!$this->rbac->hasRole($role)) {
                return false;
            }

            if ($this->rbac->isGranted($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load roles and permissions inside the container by triggering load event
     *
     * @see \ZfcRbac\Role\RoleLoaderListener
     *
     * @param  array  $roles
     * @param  string $permission
     * @return void
     */
    protected function load(array $roles = [], $permission = '')
    {
        if ($this->isLoaded && !$this->forceReload) {
            return;
        }

        $rbacEvent = new RbacEvent($this->rbac, $roles, $permission);

        $eventManager = $this->getEventManager();
        $eventManager->trigger(RbacEvent::EVENT_LOAD_ROLES, $rbacEvent);

        // If, after loading the roles, the guest role is not in the container, we add it with no permissions
        if (!empty($this->guestRole) && !$this->rbac->hasRole($this->guestRole)) {
            $this->rbac->addRole($this->guestRole);
        }

        $this->isLoaded = true;
    }

    /**
     * Flatten an array of role with role names
     *
     * This method iterates through the list of roles, and convert any RoleInterface to a string. For any
     * role, it also extracts all the children
     *
     * @param  array|RoleInterface[] $roles
     * @return string[]
     */
    protected function flattenRoles(array $roles)
    {
        $roleNames = [];

        foreach ($roles as $role) {
            if ($role instanceof RoleInterface) {
                $roleNames[] = $role->getName();
            } else {
                $role = $this->rbac->getRole($role);
            }

            if (!$role->hasChildren()) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator($role, RecursiveIteratorIterator::SELF_FIRST);

            /* @var RoleInterface $childRole */
            foreach ($iterator as $childRole) {
                $roleNames[] = $childRole->getName();
            }
        }

        return array_unique($roleNames);
    }
}
