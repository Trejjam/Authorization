<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 8.9.14
 * Time: 17:36
 */
namespace Trejjam\Authorization;

use Nette,
	Nette\Caching;

class Acl extends Nette\Security\Permission
{
	const
		CACHE_TREES = "trees";

	const
		DEFAULT_RESOURCE_ACTION = "ALL";

	protected $cacheParams = [];

	/**
	 * @var Caching\Cache
	 */
	protected $cache;
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;

	protected $tables;

	/**
	 * @var Acl\Trees
	 */
	protected $trees = NULL;

	public function __construct(Caching\Cache $cache = NULL, Nette\Database\Context $database) {
		$this->cache = $cache;
		$this->database = $database;
	}

	public function setTables(array $tables) {
		$this->tables = $tables;
	}
	public function setCacheParams($cacheParams) {
		$this->cacheParams = $cacheParams;
	}

	public function init() {
		try {
			$this->removeAllRoles();
			foreach ($this->getTrees()->getRootRoles() as $v) {
				$this->setupRoles($v);
			}

			$this->removeAllResources();
			foreach ($this->getTrees()->getResources() as $v) {
				$this->setupResource($v);
			}
		}
		catch (TableNotFoundException $e) {
			dump($e->getMessage());
		}
	}
	protected function reset() {
		$this->invalidateCache(TRUE);

		$this->init();
	}

	/**
	 * @return Acl\Trees
	 */
	public function getTrees() {
		return is_null($this->trees) ? ($this->trees = $this->cacheLoad(self::CACHE_TREES, function () {
			return new Acl\Trees($this->database, $this->tables);
		})) : $this->trees;
	}

	protected function setupRoles(Acl\Role $role) {
		if ($role->hasChild()) {
			$parents = [];
			foreach ($role->getChild() as $v) {
				$this->setupRoles($v);
				$parents[] = $v->getName();
			}
			$this->addRole($role->getName(), $parents);
		}
		else {
			$this->addRole($role->getName());
		}
	}
	protected function setupResource(Acl\Resource $resource) {
		if ($resource->getName() === $resource->getNameRaw() && !$this->hasResource($resource->getName())) {
			$this->addResource($resource->getName());
		}
		$this->allow($resource->getRole()->getName(), $resource->getName(), $resource->getAction());
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getUserRoles($id) {
		$out = [];

		foreach ($this->database->table($this->tables["userRoles"]["table"])
								->where([$this->tables["userRoles"]["userId"] => $id]) as $v) {
			$out[] = $this->getTrees()->getRoles()[$v->{$this->tables["userRoles"]["roleId"]}]->getName();
		}

		return $out;
	}
	/**
	 * @param int    $userId
	 * @param string $roleName
	 * @throws RoleException
	 */
	public function addUserRole($userId, $roleName) {
		$role = $this->getRoleByName($roleName);

		if (!is_null($role)) {
			$roleArr = [
				$this->tables["userRoles"]["userId"] => $userId,
				$this->tables["userRoles"]["roleId"] => $role->getId()
			];

			$userRole = $this->database->table($this->tables["userRoles"]["table"])->where($roleArr)->fetch();

			if (!$userRole) {
				$this->database->table($this->tables["userRoles"]["table"])->insert($roleArr);
			}
			else {
				throw new RoleException("The user is already member of the role: " . $roleName);
			}
		}
	}
	/**
	 * @param int    $userId
	 * @param string $roleName
	 */
	public function removeUserRole($userId, $roleName) {
		$role = $this->getRoleByName($roleName);

		if (!is_null($role)) {
			$roleArr = [
				$this->tables["userRoles"]["userId"] => $userId,
				$this->tables["userRoles"]["roleId"] => $role->getId()
			];

			$this->database->table($this->tables["userRoles"]["table"])->where($roleArr)->delete();
		}
	}

	/**
	 * @param string      $role
	 * @param string|null $parent
	 * @param string      $info
	 * @throws RoleException
	 */
	public function createRole($role, $parent = NULL, $info = "") {
		$dbArr = [
			$this->tables["roles"]["roleName"] => $role,
			$this->tables["roles"]["parentId"] => NULL,
		];

		if (!is_null($parent)) {
			$parentRole = $this->getRoleByName($parent);
			if (is_null($parentRole)) {
				throw new RoleException("Role " . $parent . " not exist");
			}

			$dbArr[$this->tables["roles"]["parentId"]] = $parentRole->getId();
		}

		$roleDb = $this->database->table($this->tables["roles"]["table"])->where($dbArr)->fetch();
		if ($roleDb) {
			throw new RoleException("Role " . $role . " already exist");
		}

		if (is_string($this->tables["roles"]["info"])) {
			$dbArr[$this->tables["roles"]["info"]] = $info;
		}

		$DBrole = $this->database->table($this->tables["roles"]["table"])->insert($dbArr);

		$tableInfo = $this->tables["roles"];
		unset($tableInfo["table"]);
		if ($tableInfo["info"] === FALSE) {
			unset($tableInfo["info"]);
		}

		$aclRole = new Acl\Role($DBrole, $tableInfo);
		$this->getTrees()->registerRole($aclRole);

		$this->invalidateCache();
	}
	/**
	 * @param string $role
	 * @param string $parent
	 * @throws RoleException
	 */
	public function moveRole($role, $parent) {
		$dbArr = [
			$this->tables["roles"]["parentId"] => NULL,
		];

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new RoleException("Role " . $role . " not exist");
		}

		if (!is_null($parent)) {
			$aclParent = $this->getRoleByName($parent);
			if (is_null($aclParent)) {
				throw new RoleException("Role " . $parent . " not exist");
			}

			$dbArr[$this->tables["roles"]["parentId"]] = $aclParent->getId();

			if ($this->findCircle($aclRole, $aclParent)) {
				throw new RoleException("Connecting role ($role) to parent ($parent) forming a circle");
			}
		}

		$roleDb = $this->database->table($this->tables["roles"]["table"])->get($aclRole->getId());
		if ($roleDb) {
			$roleDb->update($dbArr);
		}

		$this->reset();
	}
	/**
	 * @param string $role
	 * @param bool   $force
	 * @throws RoleException
	 */
	public function deleteRole($role, $force = FALSE) {
		$aclRole = $this->getRoleByName($role);

		if (is_null($aclRole)) {
			throw new RoleException("Role " . $role . " not exist");
		}

		if ($force) {
			foreach ($aclRole->getChild() as $v) {
				$this->deleteRole($v->getName(), $force);
			}

		}

		$roleDb = $this->database->table($this->tables["roles"]["table"])->get($aclRole->getId());

		if ($roleDb) {
			$roleDb->delete();
		}

		$this->reset();
	}
	/**
	 * @param $role
	 * @return null|Acl\Role
	 */
	public function getRoleByName($role) {
		foreach ($this->getTrees()->getRoles() as $v) {
			if ($v->getName() == $role) {
				return $v;
			}
		}

		return NULL;
	}
	protected function findCircle(Acl\Role $role, Acl\Role $parent) {
		if ($role->getId() == $parent->getId()) {
			return TRUE;
		}
		else if ($parent->hasParent()) {
			return $this->findCircle($role, $parent->getParent());
		}
		else {
			return FALSE;
		}
	}

	/**
	 * @param string      $resource
	 * @param string|null $resourceAction
	 * @param string      $role
	 * @throws ResourceException
	 * @throws RoleException
	 */
	public function createResource($resource, $resourceAction = NULL, $role) {
		$dbArr = [
			$this->tables["resource"]["resourceName"]   => $resource,
			$this->tables["resource"]["resourceAction"] => is_null($resourceAction) ? self::DEFAULT_RESOURCE_ACTION : $resourceAction,
		];

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new RoleException("Role " . $role . " not exist");
		}

		$dbArr[$this->tables["resource"]["roleId"]] = $aclRole->getId();

		$resourceDb = $this->database->table($this->tables["resource"]["table"])->where($dbArr)->fetch();
		if ($resourceDb) {
			throw new ResourceException("Resource " . $resource . " already exist");
		}

		$DBresource = $this->database->table($this->tables["resource"]["table"])->insert($dbArr);

		$tableInfo = $this->tables["resource"];
		unset($tableInfo["table"]);

		$AclResource = new Acl\Resource($DBresource, $tableInfo);
		$this->getTrees()->registerResource($AclResource);

		$this->invalidateCache();
	}
	/**
	 * @param string      $resource
	 * @param string|null $resourceAction
	 * @param string      $role
	 * @throws ResourceException
	 * @throws RoleException
	 */
	public function moveResource($resource, $resourceAction = NULL, $role) {
		$dbArr = [];

		$resourceDb = $this->getResource($resource, $resourceAction);

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new RoleException("Role " . $role . " not exist");
		}

		$dbArr[$this->tables["resource"]["roleId"]] = $aclRole->getId();

		$resourceDb->update($dbArr);

		$this->reset();
	}
	/**
	 * @param string      $resource
	 * @param string|null $resourceAction
	 * @throws ResourceException
	 */
	public function deleteResource($resource, $resourceAction = NULL) {
		$resourceDb = $this->getResource($resource, $resourceAction);

		$resourceDb->delete();

		$this->reset();
	}

	/**
	 * @param int $id
	 * @return Acl\Resource
	 * @throws ResourceException
	 */
	public function getResourceById($id) {
		$resources = $this->getTrees()->getResources();

		if (isset($resources[$id])) {
			return $resources[$id];
		}
		else {
			throw new ResourceException("Resource not exist");
		}
	}
	protected function getResource($resource, $resourceAction = NULL) {
		$dbArr = [
			$this->tables["resource"]["resourceName"]   => $resource,
			$this->tables["resource"]["resourceAction"] => is_null($resourceAction) ? self::DEFAULT_RESOURCE_ACTION : $resourceAction,
		];

		$resourceDb = $this->database->table($this->tables["resource"]["table"])->where($dbArr)->fetch();

		if ($resourceDb) {
			return $resourceDb;
		}
		else {
			throw new ResourceException("Resource $resource:$resourceAction not exist");
		}
	}

	/**
	 * @param bool $needReloadNow
	 */
	public function invalidateCache($needReloadNow = FALSE) {
		$this->cacheRemove(self::CACHE_TREES);

		if ($needReloadNow) {
			$this->trees = NULL;
		}
	}
	/**
	 * @param $key
	 */
	protected function cacheRemove($key) {
		if (!is_null($this->cache)) {
			$this->cache->save($key, NULL);
		}
	}
	/**
	 * @param string   $key
	 * @param callable $fallback
	 * @return mixed|NULL
	 */
	protected function cacheLoad($key, callable $fallback = NULL) {
		if (is_null($this->cache)) {
			if (!is_null($fallback)) {
				return $fallback();
			}
		}
		else {
			if (is_null($fallback) && !is_null($this->cache->load($key))) {
				return $this->cache->load($key);
			}
			else if (!is_null($fallback)) {
				return $this->cache->load($key, function (& $dependencies) use ($fallback) {
					$dependencies = $this->cacheParams;

					return $fallback();
				});
			}
		}

		return NULL;
	}
}
