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
	protected $reloadChangedUser;

	/**
	 * @var Trees
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
	 * @return Trees
	 */
	public function getTrees() {
		return is_null($this->trees) ? ($this->trees = $this->cacheLoad(self::CACHE_TREES, function () {
			return new Trees($this->database, $this->tables);
		})) : $this->trees;
	}

	protected function setupRoles(AclRole $role) {
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
	protected function setupResource(AclResource $resource) {
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

		$aclRole = new AclRole($DBrole, $tableInfo);
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
	 * @return null|AclRole
	 */
	public function getRoleByName($role) {
		foreach ($this->getTrees()->getRoles() as $v) {
			if ($v->getName() == $role) {
				return $v;
			}
		}

		return NULL;
	}
	protected function findCircle(AclRole $role, AclRole $parent) {
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

		$aclResource = new AclRole($DBresource, $tableInfo);
		$this->getTrees()->registerResource($aclResource);

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
	 * @return AclResource
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

class Trees
{
	/**
	 * @var AclRole[]
	 */
	protected $roles = [];
	/**
	 * @var AclRole[]
	 */
	protected $rootRoles = [];
	/**
	 * @var AclResource[]
	 */
	protected $resource = [];

	function __construct(Nette\Database\Context $database, array $tables) {
		$this->createRolesTree($database, $tables);
	}

	protected function createRolesTree(Nette\Database\Context $database, array $tables) {
		if (!$database->query("SHOW TABLES LIKE '" . $tables["roles"]["table"] . "'")->getRowCount()) {
			throw new TableNotFoundException("Table " . $tables["roles"]["table"] . " not exist");
		}
		foreach ($database->table($tables["roles"]["table"]) as $v) {
			$tableInfo = $tables["roles"];
			unset($tableInfo["table"]);
			if ($tableInfo["info"] === FALSE) {
				unset($tableInfo["info"]);
			}

			$this->registerRole(new AclRole($v, $tableInfo), FALSE);
		}

		foreach ($this->roles as $v) {
			$v->connectToParent($this->roles);

			if (!$v->hasParent()) {
				$this->rootRoles[$v->getId()] = $v;
			}
		}

		$this->createResourceTree($database, $tables);
	}
	public function registerRole(AclRole $role, $connect = TRUE) {
		$this->roles[$role->getId()] = $role;

		if ($connect) {
			$role->connectToParent($this->roles);

			if (!$role->hasParent()) {
				$this->rootRoles[$role->getId()] = $role;
			}
		}
	}
	protected function createResourceTree(Nette\Database\Context $database, array $tables) {
		if (!$database->query("SHOW TABLES LIKE '" . $tables["resource"]["table"] . "'")->getRowCount()) {
			throw new TableNotFoundException("Table " . $tables["resource"]["table"] . " not exist");
		}
		foreach ($database->table($tables["resource"]["table"]) as $v) {
			$tableInfo = $tables["resource"];
			unset($tableInfo["table"]);

			$this->registerResource(new AclResource($v, $tableInfo));
		}
	}
	public function registerResource(AclResource $resource) {
		$this->resource[$resource->getId()] = $resource;

		$resource->connectToRole($this->roles);
	}

	/**
	 * @return AclRole[]
	 */
	public function getRoles() {
		return $this->roles;
	}
	/**
	 * @return AclRole[]
	 */
	public function getRootRoles() {
		return $this->rootRoles;
	}
	/**
	 * @return AclResource[]
	 */
	public function getResources() {
		return $this->resource;
	}
}

class AclRole
{
	protected $id;
	protected $parentId;
	protected $roleName;
	protected $info = FALSE;

	/**
	 * @var AclRole
	 */
	protected $parent = NULL;
	/**
	 * @var AclRole[]
	 */
	protected $child = [];

	/**
	 * @var AclResource[]
	 */
	protected $resources = [];

	function __construct(Nette\Database\Table\IRow $row, array $tableInfo) {
		foreach ($tableInfo as $k => $v) {
			$this->{is_numeric($k) ? $v : $k} = $row->$v;
		}
	}

	/**
	 * @param array $roles
	 */
	public function connectToParent(array $roles) {
		if (!$this->hasParent()) return;
		$this->parent = $roles[$this->parentId];
		$this->parent->connectToChild($this);
	}
	/**
	 * @param AclRole $role
	 */
	protected function connectToChild(AclRole $role) {
		$this->child[$role->getId()] = $role;
	}
	/**
	 * @param AclResource $resource
	 */
	public function addResource(AclResource $resource) {
		$this->resources[$resource->getId()] = $resource;
	}
	/**
	 * @param array $role
	 */
	public function fillArrays(array &$role, array &$resource) {
		$role[$this->getId()] = $this;
		foreach ($this->resources as $k => $v) {
			$resource[$k] = $v;
		}

		foreach ($this->child as $v) {
			$v->fillArrays($role, $resource);
		}
	}

	/**
	 * @return bool
	 */
	public function hasParent() {
		return isset($this->parentId);
	}
	/**
	 * @return bool
	 */
	public function hasChild() {
		return count($this->child) > 0;
	}


	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}
	/**
	 * @return string
	 */
	public function getName() {
		return $this->roleName;
	}
	/**
	 * @return string
	 * @throws UserConfigurationException
	 */
	public function getInfo() {
		if ($this->info === FALSE) throw new UserConfigurationException("Field 'info' was disabled in configuration");

		return $this->info;
	}

	/**
	 * @return AclRole|null
	 */
	public function getParent() {
		return $this->hasParent() ? $this->parent : NULL;
	}
	/**
	 * @return AclRole[]
	 */
	public function getChild() {
		return $this->child;
	}

	/**
	 * @return AclResource[]
	 */
	public function getResource() {
		return $this->resources;
	}

	/**
	 * @return int
	 */
	public function getDepth() {
		$depth = 0;

		$tempThis = $this;
		while ($tempThis->hasParent()) {
			$tempThis = $tempThis->getParent();
			$depth++;
		}

		return $depth;
	}
	/**
	 * @param array $roleNames
	 * @return bool
	 */
	public function isChildOf(array $roleNames) {
		$tempThis = $this;
		if (in_array($tempThis->getName(), $roleNames)) {
			return TRUE;
		}
		while ($tempThis->hasParent()) {
			$tempThis = $tempThis->getParent();
			if (in_array($tempThis->getName(), $roleNames)) {
				return TRUE;
			}
		}

		return FALSE;
	}
}

class AclResource
{
	protected $id;
	protected $roleId;
	protected $resourceName;
	protected $resourceAction;

	/**
	 * @var AclRole
	 */
	protected $role = NULL;

	function __construct(Nette\Database\Table\IRow $row, array $tableInfo) {
		foreach ($tableInfo as $k => $v) {
			$this->{is_numeric($k) ? $v : $k} = $row->$v;
		}
	}

	/**
	 * @param AclRole[] $roles
	 */
	public function connectToRole($roles) {
		$this->role = $roles[$this->roleId];
		$this->role->addResource($this);
	}

	public function getId() {
		return $this->id;
	}
	public function getNameRaw() {
		return $this->resourceName;
	}
	public function getName() {
		return defined('\Nette\Security\Permission::' . $this->resourceName)
			? constant('\Nette\Security\Permission::' . $this->resourceName)
			: $this->resourceName;
	}
	public function getActionRaw() {
		return $this->resourceAction;
	}
	public function getAction() {
		return defined('\Nette\Security\Permission::' . $this->resourceAction)
			? constant('\Nette\Security\Permission::' . $this->resourceAction)
			: $this->resourceAction;
	}
	public function getAllowedList() {
		return [
			$this->getName(),
			$this->getAction(),
		];
	}
	public function getRole() {
		return $this->role;
	}
}
