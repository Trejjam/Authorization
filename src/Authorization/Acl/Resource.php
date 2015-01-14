<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 15.1.15
 * Time: 0:22
 */

namespace Trejjam\Authorization\Acl;


use Nette,
	Trejjam;

class Resource
{
	protected $id;
	protected $roleId;
	protected $resourceName;
	protected $resourceAction;

	/**
	 * @var Role
	 */
	protected $role = NULL;

	function __construct(Nette\Database\Table\IRow $row, array $tableInfo) {
		foreach ($tableInfo as $k => $v) {
			$this->{is_numeric($k) ? $v : $k} = $row->$v;
		}
	}

	/**
	 * @param Role[] $roles
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