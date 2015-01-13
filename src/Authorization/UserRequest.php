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
			throw new UserRequestException("Type '$type' is not valid");
		}

		do {
			$hash = Nette\Utils\Random::generate($this->tables['userRequest']['hash']['length'], '0-9A-Z');
		} while($this->getTable()->where([
			$this->tables['userRequest']['hash']['name'] => $hash,
		])->fetch());

		$this->getTable()->insert([
			$this->tables['userRequest']['userId']       => $userId,
			$this->tables['userRequest']['hash']['name'] => $hash,
			$this->tables['userRequest']['type']['name'] => $type,
		]);

		return $hash;
	}
	/**
	 * @param int    $userId
	 * @param string $hash
	 * @param bool   $invalidateHash
	 * @return bool|string
	 */
	public function getType($userId, $hash, $invalidateHash = TRUE) {
		if ($row = $this->getTable()->where([
			$this->tables['userRequest']['userId']       => $userId,
			$this->tables['userRequest']['hash']['name'] => $hash,
		])->fetch()
		) {
			if ($row->{$this->tables['userRequest']['used']['name']} == $this->tables['userRequest']['used']['positive']) {
				return TRUE;
			}

			if ($invalidateHash) {
				$row->update([
					$this->tables['userRequest']['used']['name'] => $this->tables['userRequest']['used']['positive']
				]);
			}

			return $row->{$this->tables['userRequest']['type']['name']};
		}

		return FALSE;
	}
}

class UserRequestException extends \Exception
{

}
