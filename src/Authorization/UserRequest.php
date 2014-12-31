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
		return $this->database->table($this->tables['users__user_request']['table']);
	}
	protected function isTypeValid($type) {
		return in_array($type, $this->tables['users__user_request']['type']['option']);
	}

	public function generateHash($userId, $type) {
		if (!$this->isTypeValid($type)) {
			throw new \Exception("Type '$type' is not valid");
		}

		$hash = Nette\Utils\Random::generate($this->tables['users__user_request']['hash']['length'], '0-9A-Z');

		$this->getTable()->insert([
			$this->tables['users__user_request']['userId']       => $userId,
			$this->tables['users__user_request']['hash']['name'] => $hash,
			$this->tables['users__user_request']['type']['name'] => $type,
		]);

		return $hash;
	}
	public function getType($userId, $hash) {
		if ($row = $this->getTable()->where([
			$this->tables['users__user_request']['userId']       => $userId,
			$this->tables['users__user_request']['hash']['name'] => $hash,
		])->fetch()
		) {
			return $row->{$this->tables['users__user_request']['type']['name']};
		}

		return FALSE;
	}
}