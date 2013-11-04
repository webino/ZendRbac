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

use Zend\EventManager\Event;
use Zend\Permissions\Rbac\Rbac;

/**
 * Event triggered for loading roles and permissions to populate the Rbac container
 */
class RbacEvent extends Event
{
    /**
     * Event names
     */
    const EVENT_LOAD_ROLES       = 'loadRoles';
    const EVENT_LOAD_PERMISSIONS = 'loadPermissions';

    /**
     * Rbac container
     *
     * @var Rbac
     */
    protected $rbac;

    /**
     * Constructor
     *
     * @param Rbac $rbac
     */
    public function __construct(Rbac $rbac)
    {
        $this->rbac = $rbac;
    }

    /**
     * Get the Rbac event
     *
     * @return Rbac
     */
    public function getRbac()
    {
        return $this->rbac;
    }
} 