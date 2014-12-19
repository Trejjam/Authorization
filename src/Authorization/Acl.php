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
		CACHE_LIST = "list",
		CACHE_ROLE = "role_",
		CACHE_RESOURCE = "resource_";

	const
		DEFAULT_RESOURCE_ACTION = "ALL";

	private $cacheParams = [];

	/**
	 * @var Caching\Cache
	 */
	private $cache;
	/**
	 * @var Nette\Database\Context
	 */
	private $database;

	private $tables;
	private $reloadChangedUser;

	/**
	 * @var AclRole[]
	 */
	private $_roles = [];
	/**
	 * @var AclRole[]
	 */
	private $_rootRoles = [];

	/**
	 * @var AclResource[]
	 */
	private $_resource = [];

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
			foreach ($this->getRoles_() as $v) {
				$this->removeAllRoles();
				$this->setupRoles($v);
			}

			$this->removeAllResources();
			foreach ($this->getResource_() as $v) {
				$this->setupResource($v);
			}
		}
		catch (TableNotFound $e) {
			dump($e->getMessage());
		}
	}
	private function reset() {
		$this->clearCache();
		$this->_roles = [];
		$this->_rootRoles = [];
		$this->_resource = [];

		$this->init();
	}

	private function setupRoles(AclRole $role) {
		$this->addRole($role->getName(), $role->hasParent() ? $role->getParent()->getName() : NULL);

		foreach ($role->getChild() as $v) {
			$this->setupRoles($v);
		}
	}
	public function getRoles_() {
		if (count($this->_rootRoles) == 0) {
			if (!is_null($this->cacheLoad(self::CACHE_ROLE . self::CACHE_LIST))) {
				$this->_rootRoles = unserialize($this->cacheLoad(self::CACHE_ROLE . self::CACHE_LIST));

				foreach ($this->_rootRoles as $k => $v) {
					$this->_roles[$k]->fillArrays($this->_roles, $this->_resource);
				}
			}
			else {
				$this->createRolesTree();
			}
		}

		return $this->_rootRoles;
	}
	private function createRolesTree() {
		if (!$this->database->query("SHOW TABLES LIKE '" . $this->tables["roles"]["table"] . "'")->getRowCount()) {
			throw new TableNotFound("Table " . $this->tables["roles"]["table"] . " not exist");
		}
		foreach ($this->database->table($this->tables["roles"]["table"]) as $v) {
			$tableInfo = $this->tables["roles"];
			unset($tableInfo["table"]);
			if ($tableInfo["info"] === FALSE) {
				unset($tableInfo["info"]);
			}

			$this->_roles[$v->{$tableInfo["id"]}] = new AclRole($v, $tableInfo);
		}

		foreach ($this->_roles as $v) {
			$v->connectToParent($this->_roles);

			if (!$v->hasParent()) {
				$this->_rootRoles[$v->getId()] = $v;
			}
		}

		$this->createResourceTree();

		$this->cacheSave(self::CACHE_ROLE . self::CACHE_LIST, serialize($this->_rootRoles));
	}

	private function setupResource(AclResource $resource) {
		if ($resource->getName() === $resource->getNameRaw() && !$this->hasResource($resource->getName())) {
			$this->addResource($resource->getName());
		}
		$this->allow($resource->getRole()->getName(), $resource->getName(), $resource->getAction());
	}
	/**
	 * @return AclResource[]
	 */
	public function getResource_() {
		if (count($this->_resource) == 0) {
			$this->getRoles_();
		}

		return $this->_resource;
	}
	private function createResourceTree() {
		if (!$this->database->query("SHOW TABLES LIKE '" . $this->tables["resource"]["table"] . "'")->getRowCount()) {
			throw new TableNotFound("Table " . $this->tables["resource"]["table"] . " not exist");
		}
		foreach ($this->database->table($this->tables["resource"]["table"]) as $v) {
			$tableInfo = $this->tables["resource"];
			unset($tableInfo["table"]);

			$this->_resource[$v->{$tableInfo["id"]}] = new AclResource($v, $tableInfo);
		}

		$this->getRoles_();

		foreach ($this->_resource as $v) {
			$v->connectToRole($this->_roles);
		}
	}

	/**
	 * @param $role
	 * @return null|AclRole
	 */
	private function getRoleByName($role) {
		$this->getRoles_();

		foreach ($this->_roles as $v) {
			if ($v->getName() == $role) {
				return $v;
			}
		}

		return NULL;
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getUserRoles($id) {
		$this->getRoles_();

		$out = [];

		foreach ($this->database->table($this->tables["userRoles"]["table"])
								->where([$this->tables["userRoles"]["userId"] => $id]) as $v) {
			$out[] = $this->_roles[$v->{$this->tables["userRoles"]["roleId"]}]->getName();
		}

		return $out;
	}
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
				throw new \Exception("The user is already member of the role: " . $roleName);
			}
		}
	}
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

	public function createRole($role, $parent = NULL, $info = "") {
		$dbArr = [
			$this->tables["roles"]["roleName"] => $role,
			$this->tables["roles"]["parentId"] => NULL,
		];

		if (!is_null($parent)) {
			$parentRole = $this->getRoleByName($parent);
			if (is_null($parentRole)) {
				throw new \Exception("Role " . $parent . " not exist");
			}

			$dbArr[$this->tables["roles"]["parentId"]] = $parentRole->getId();
		}

		$roleDb = $this->database->table($this->tables["roles"]["table"])->where($dbArr)->fetch();
		if ($roleDb) {
			throw new \Exception("Role " . $role . " already exist");
		}

		if (is_string($this->tables["roles"]["info"])) {
			$dbArr[$this->tables["roles"]["info"]] = $info;
		}

		$this->database->table($this->tables["roles"]["table"])->insert($dbArr);

		$this->reset();
	}
	public function setRoleParent($role, $parent) {
		$dbArr = [
			$this->tables["roles"]["parentId"] => NULL,
		];

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new \Exception("Role " . $role . " not exist");
		}

		if (!is_null($parent)) {
			$aclParent = $this->getRoleByName($parent);
			if (is_null($aclParent)) {
				throw new \Exception("Role " . $parent . " not exist");
			}

			$dbArr[$this->tables["roles"]["parentId"]] = $aclParent->getId();

			if ($this->findCircle($aclRole, $aclParent)) {
				throw new \Exception("Connecting role ($role) to parent ($parent) forming a circle");
			}
		}

		$roleDb = $this->database->table($this->tables["roles"]["table"])->get($aclRole->getId());
		if ($roleDb) {
			$roleDb->update($dbArr);
		}

		$this->reset();
	}
	public function deleteRole($role, $force = FALSE) {
		$aclRole = $this->getRoleByName($role);

		if (is_null($aclRole)) {
			throw new \Exception("Role " . $role . " not exist");
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
	private function findCircle(AclRole $role, AclRole $parent) {
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

	public function createResource($resource, $resourceAction = NULL, $role) {
		$dbArr = [
			$this->tables["resource"]["resourceName"]   => $resource,
			$this->tables["resource"]["resourceAction"] => is_null($resourceAction) ? self::DEFAULT_RESOURCE_ACTION : $resourceAction,
		];

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new \Exception("Role " . $role . " not exist");
		}

		$dbArr[$this->tables["resource"]["roleId"]] = $aclRole->getId();

		$rresourceDb = $this->database->table($this->tables["resource"]["table"])->where($dbArr)->fetch();
		if ($rresourceDb) {
			throw new \Exception("Resource " . $resource . " already exist");
		}

		$this->database->table($this->tables["resource"]["table"])->insert($dbArr);

		$this->reset();
	}
	public function moveResource($resource, $resourceAction = NULL, $role) {
		$dbArr = [];

		$resourceDb = $this->getResource($resource, $resourceAction);

		$aclRole = $this->getRoleByName($role);
		if (is_null($aclRole)) {
			throw new \Exception("Role " . $role . " not exist");
		}

		$dbArr[$this->tables["resource"]["roleId"]] = $aclRole->getId();

		$resourceDb->update($dbArr);

		$this->reset();
	}
	public function deleteResource($resource, $resourceAction = NULL) {
		$resourceDb = $this->getResource($resource, $resourceAction);

		$resourceDb->delete();

		$this->reset();
	}

	/**
	 * @param $id
	 * @return AclResource
	 * @throws \Exception
	 */
	public function getResourceById($id) {
		$resources = $this->getResource_();

		if (isset($resources[$id])) {
			return $resources[$id];
		}
		else {
			throw new \Exception("Resource not exist");
		}
	}
	private function getResource($resource, $resourceAction = NULL) {
		$dbArr = [
			$this->tables["resource"]["resourceName"]   => $resource,
			$this->tables["resource"]["resourceAction"] => is_null($resourceAction) ? self::DEFAULT_RESOURCE_ACTION : $resourceAction,
		];

		$resourceDb = $this->database->table($this->tables["resource"]["table"])->where($dbArr)->fetch();

		if ($resourceDb) {
			return $resourceDb;
		}
		else {
			throw new \Exception("Resource $resource:$resourceAction not exist");
		}
	}


	private function clearCache() {
		if (is_null($this->cache)) return;

		$this->cache->clean();
	}
	private function cacheSave($key, $value) {
		if (is_null($this->cache)) return;

		$this->cache->save($key, $value, $this->cacheParams);
	}
	private function cacheLoad($key) {
		if (is_null($this->cache)) return NULL;

		if (!is_null($this->cache->load($key))) {
			return $this->cache->load($key);
		}
		else {
			return NULL;
		}
	}
}

class AclRole
{
	private $id;
	private $parentId;
	private $roleName;
	private $info = FALSE;

	/**
	 * @var AclRole
	 */
	private $parent = NULL;
	/**
	 * @var AclRole[]
	 */
	private $child = [];

	/**
	 * @var AclResource[]
	 */
	private $resources = [];

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
	public function connectToChild(AclRole $role) {
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
	 * @throws \Exception
	 */
	public function getInfo() {
		if ($this->info === FALSE) throw new \Exception("This field was disabled in configuration");

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
		$depth=0;

		$tempThis=$this;
		while($tempThis->hasParent()) {
			$tempThis= $tempThis->getParent();
			$depth++;
		}

		return $depth;
	}
}

class AclResource
{
	private $id;
	private $roleId;
	private $resourceName;
	private $resourceAction;

	/**
	 * @var AclRole
	 */
	private $role = NULL;

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

class TableNotFound extends \Exception
{

}