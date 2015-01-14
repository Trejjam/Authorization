<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 15.1.15
 * Time: 0:23
 */

namespace Trejjam\Authorization\Acl;


use Nette,
	Trejjam;

class Role
{
	protected $id;
	protected $parentId;
	protected $roleName;
	protected $info = FALSE;

	/**
	 * @var Role
	 */
	protected $parent = NULL;
	/**
	 * @var Role[]
	 */
	protected $child = [];

	/**
	 * @var Resource[]
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
	 * @param Role $role
	 */
	protected function connectToChild(Role $role) {
		$this->child[$role->getId()] = $role;
	}
	/**
	 * @param Resource $resource
	 */
	public function addResource(Resource $resource) {
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
	 * @throws Trejjam\Authorization\UserConfigurationException
	 */
	public function getInfo() {
		if ($this->info === FALSE) throw new Trejjam\Authorization\UserConfigurationException("Field 'info' was disabled in configuration");

		return $this->info;
	}

	/**
	 * @return Role|null
	 */
	public function getParent() {
		return $this->hasParent() ? $this->parent : NULL;
	}
	/**
	 * @return Role[]
	 */
	public function getChild() {
		return $this->child;
	}

	/**
	 * @return Resource[]
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