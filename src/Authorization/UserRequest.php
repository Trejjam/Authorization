<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 31.12.14
 * Time: 13:51
 */

namespace Trejjam\Authorization;

use Nette,
	Trejjam;

class UserRequest
{
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var array
	 */
	protected $tables;

	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
	}
	public function setTables(array $tables) {
		$this->tables = $tables;
	}

	protected function getTable() {
		return $this->database->table($this->tables['userRequest']['table']);
	}
	protected function isTypeValid($type) {
		return in_array($type, $this->tables['userRequest']['type']['option']);
	}

	public function generateHash($userId, $type) {
		if (!$this->isTypeValid($type)) {
			throw new \Exception("Type '$type' is not valid");
		}

		$hash = Nette\Utils\Random::generate($this->tables['userRequest']['hash']['length'], '0-9A-Z');

		$this->getTable()->insert([
			$this->tables['userRequest']['userId']       => $userId,
			$this->tables['userRequest']['hash']['name'] => $hash,
			$this->tables['userRequest']['type']['name'] => $type,
		]);

		return $hash;
	}
	public function getType($userId, $hash) {
		if ($row = $this->getTable()->where([
			$this->tables['userRequest']['userId']       => $userId,
			$this->tables['userRequest']['hash']['name'] => $hash,
		])->fetch()
		) {
			return $row->{$this->tables['userRequest']['type']['name']};
		}

		return FALSE;
	}
}