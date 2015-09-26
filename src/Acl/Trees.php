<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 15.1.15
 * Time: 0:27
 */

namespace Trejjam\Authorization\Acl;


use Nette,
	Trejjam;

class Trees
{
	/**
	 * @var Role[]
	 */
	protected $roles = [];
	/**
	 * @var Role[]
	 */
	protected $rootRoles = [];
	/**
	 * @var Resource[]
	 */
	protected $resource = [];

	function __construct(Nette\Database\Context $database, array $tables) {
		$this->createRolesTree($database, $tables);
	}

	protected function createRolesTree(Nette\Database\Context $database, array $tables) {
		if (!$database->query("SHOW TABLES LIKE '" . $tables["roles"]["table"] . "'")->getRowCount()) {
			throw new Trejjam\Authorization\TableNotFoundException("Table " . $tables["roles"]["table"] . " not exist");
		}
		foreach ($database->table($tables["roles"]["table"]) as $v) {
			$tableInfo = $tables["roles"];
			unset($tableInfo["table"]);
			if ($tableInfo["info"] === FALSE) {
				unset($tableInfo["info"]);
			}

			$this->registerRole(new Role($v, $tableInfo), FALSE);
		}

		foreach ($this->roles as $v) {
			$v->connectToParent($this->roles);

			if (!$v->hasParent()) {
				$this->rootRoles[$v->getId()] = $v;
			}
		}

		$this->createResourceTree($database, $tables);
	}
	public function registerRole(Role $role, $connect = TRUE) {
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
			throw new Trejjam\Authorization\TableNotFoundException("Table " . $tables["resource"]["table"] . " not exist", Trejjam\Authorization\TableNotFoundException::TABLE_NOT_FOUND);
		}
		foreach ($database->table($tables["resource"]["table"]) as $v) {
			$tableInfo = $tables["resource"];
			unset($tableInfo["table"]);

			$this->registerResource(new Resource($v, $tableInfo));
		}
	}
	public function registerResource(Resource $resource) {
		$this->resource[$resource->getId()] = $resource;

		$resource->connectToRole($this->roles);
	}

	/**
	 * @return Role[]
	 */
	public function getRoles() {
		return $this->roles;
	}
	/**
	 * @return Role[]
	 */
	public function getRootRoles() {
		return $this->rootRoles;
	}
	/**
	 * @return Resource[]
	 */
	public function getResources() {
		return $this->resource;
	}
}