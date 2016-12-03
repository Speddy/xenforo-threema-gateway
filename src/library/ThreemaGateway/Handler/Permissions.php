<?php
/**
 * Manages permissions of all actions.
 *
 * It is designed as a singleton, as there should only be one user whose
 * permissions are checked and this is the current user.
 * Thus it is not allowed to check the permissions of third-party users as
 * it does not make any sense and only introduces potential vulnerabilities as
 * it might allow to take over permissions from other users.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2015-2016 rugk
 * @license MIT
 */

use Threema\MsgApi\Receiver;
use Threema\MsgApi\Helpers\E2EHelper;

/**
 * This manages the permissions of all actions.
 */
class ThreemaGateway_Handler_Permissions
{
    /**
     * @var Singleton
     */
    private static $instance = null;

    /**
     * @var array|null User who is using the Threema Gateway
     */
    protected $user = null;

    /**
     * @var array Permissions cache
     */
    protected $permissions;

    /**
     * Private constructor so nobody else can instance it.
     * Use {@link getInstance()} instead.
     *
     * @return true
     */
    private function __construct()
    {
        // do nothing
    }

    /**
     * Prevent cloning for Singleton.
     */
    private function __clone()
    {
        // I smash clones!
    }

    /**
     * SDK startup as a Singleton.
     *
     * @throws XenForo_Exception
     * @return ThreemaGateway_Handler_Permissions
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Sets/Changes the user id of the user using the Threema Gateway. This
     * also forces a reload of the permission cache. (See {@link hasPermission()}).
     *
     * Returns true when value was changed. If false is returned the user is
     * already the visitor or the user id is already set or and the value was
     * not changed.
     *
     * @param  array|null $newUser (optional) User array
     * @return bool
     */
    public function setUserId($newUser = null)
    {
        // get user ids (or null)
        if ($this->user === null) {
            /** @var int|null $oldUserId User id of user (from class) */
            $oldUserId = null;
        } else {
            /** @var int|null $oldUserId User id of user (from class) */
            $oldUserId = $this->user['user_id'];
        }
        if ($newUser === null) {
            /** @var int|null $newUserId User id of new user (from param) */
            $newUserId = null;
        } else {
            /** @var int|null $newUserId User id of new user (from param) */
            $newUserId = $newUser['user_id'];
        }

        // check whether visitor is user
        /** @var bool $userIsAlreadyVisitor Whether the new user is already the default user and the
                        old user is the default user too.
        */
        $userIsAlreadyVisitor = $this->userIsDefault($newUser) && $this->userIsDefault($oldUserId);
        // prevent unneccessary changes
        if ($oldUserId == $newUserId || $userIsAlreadyVisitor) {
            return false;
        }

        //change user Id
        $this->user = $newUser;
        $this->hasPermission(null, true);
        return true;
    }

    /**
     * Checks whether the user has the permission to do something.
     *
     * This uses the user e.g. set by {@link setUserId()}. If no user is set it
     * uses the current visitor/user.
     * The currently avaliable actions are: use, send, receive, fetch, lookup,
     * tfa and credits
     * If you do not specify an action an array of all possible actions is
     * returned.
     * Note that "credits" is an admin permission and is therefore only avaliable
     * to admins.
     *
     * @param  string     $action  (optional) The action you want to do
     * @param  string     $noCache (optional) Forces the cache to reload
     * @return bool|array
     */
    public function hasPermission($action = null, $noCache = false)
    {
        /** @var array $permissions Temporary variable for permissions */
        $permissions = [];

        if ($this->user === null) {
            /** @var int|null $userId User id of user (from class) */
            $userId = null;
        } else {
            /** @var int|null $userId User id of user (from class) */
            $userId = $this->user['user_id'];
        }

        // get permissions and cache them
        if (!$noCache && is_array($this->permissions)) {
            // load from cache
            $permissions = $this->permissions;
        } else {
            if ($this->userIsDefault($userId)) {
                //normal visitor
                /** @var XenForo_Visitor $visitor */
                $visitor = XenForo_Visitor::getInstance();

                $permissions['use']     = $visitor->hasPermission('threemagw', 'use');
                $permissions['send']    = $visitor->hasPermission('threemagw', 'send');
                $permissions['receive'] = $visitor->hasPermission('threemagw', 'receive');
                $permissions['fetch']   = $visitor->hasPermission('threemagw', 'fetch');
                $permissions['lookup']  = $visitor->hasPermission('threemagw', 'lookup');
                $permissions['tfa']     = $visitor->hasPermission('threemagw', 'tfa');
                $permissions['credits'] = $visitor->hasAdminPermission('threemagw_showcredits');
            } else {
                if (!array_key_exists('permissions', $this->user)) {
                    if (!array_key_exists('global_permission_cache', $this->user) || !$this->user['global_permission_cache']) {
                        // used code by XenForo_Visitor::setup
                        // get permissions from cache
                        $perms = XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCombinationById(
                            $this->user['permission_combination_id']
                        );
                        $this->user['permissions'] = $perms ? $perms : [];
                    } else {
                        $this->user['permissions'] = XenForo_Permission::unserializePermissions($this->user['global_permission_cache']);
                    }
                }

                //get permissions
                $permissions['use']     = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'use');
                $permissions['send']    = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'send');
                $permissions['receive'] = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'receive');
                $permissions['fetch']   = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'fetch');
                $permissions['lookup']  = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'lookup');
                $permissions['tfa']     = XenForo_Permission::hasPermission($this->user['permissions'], 'threemagw', 'tfa');
                // Getting admin permission would be extensive and admins should
                // also only have special permissions if they are really logged in.
                // Therefore all admin permissions are set to false
                $permissions['credits'] = false;
            }
            $this->permissions = $permissions;
        }

        // Return permission
        if ($action) {
            if (!array_key_exists($action, $permissions)) {
                // invalid action
                return false;
            }
            return $permissions[$action];
        }

        return $permissions;
    }

    /**
     * Checks whether a user is the default user/the current "visitor".
     *
     * @param  int|null $userId A user id or null
     * @return bool
     */
    protected function userIsDefault($userId)
    {
        /** @var XenForo_Visitor $visitor */
        $visitor = XenForo_Visitor::getInstance();
        /** @var int $visitorUserId Visitor user id */
        $visitorUserId = $visitor->getUserId();

        return $userId === $visitorUserId || $userId === null;
    }
}
