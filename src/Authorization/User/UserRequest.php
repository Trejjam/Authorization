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
	/**
	 * @var int
	 */
	protected $sqlTime;

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

	public function generateHash($userId, $type, $timeout = NULL) {
		if (!$this->isTypeValid($type)) {
			throw new UserRequestException("Type '$type' is not valid or registered");
		}

		$hash = Nette\Utils\Random::generate($this->tables['userRequest']['hash']['length'], '0-9A-Z');
		if (is_null($timeout)) {
			$timeout = $this->tables['userRequest']['timeout']['default'];
		}

		$insertion = $this->getTable()->insert([
			$this->tables['userRequest']['userId']          => $userId,
			$this->tables['userRequest']['hash']['name']    => Nette\Security\Passwords::hash($hash),
			$this->tables['userRequest']['type']['name']    => $type,
			$this->tables['userRequest']['timeout']['name'] => new Nette\Database\SqlLiteral('NOW() + INTERVAL ' . $timeout),
		]);

		return [$insertion->id, $hash];
	}
	/**
	 * @param int    $userId
	 * @param int    $requestId
	 * @param string $hash
	 * @param bool   $invalidateHash
	 * @return bool|string
	 */
	public function getType($userId, $requestId, $hash, $invalidateHash = TRUE) {
		if ($row = $this->getTable()->where([
			$this->tables['userRequest']['userId'] => $userId,
			$this->tables['userRequest']['id']     => $requestId,
		])->fetch()
		) {
			if (!Nette\Security\Passwords::verify($hash, $row->{$this->tables['userRequest']['hash']['name']})) {
				throw new UserRequestException('Hash is corrupted', UserRequestException::CORRUPTED_HASH);
			}

			if ($row->{$this->tables['userRequest']['used']['name']} == $this->tables['userRequest']['used']['positive']) {
				throw new UserRequestException('Hash was used', UserRequestException::USED_HASH);
			}

			if ($row->{$this->tables['userRequest']['timeout']['name']} < $this->getSqlTime()) {
				throw new UserRequestException('Hash timeout', UserRequestException::HASH_TIMEOUT);
			}

			if ($invalidateHash) {
				$row->update([
					$this->tables['userRequest']['used']['name'] => $this->tables['userRequest']['used']['positive']
				]);
			}

			return $row->{$this->tables['userRequest']['type']['name']};
		}

		throw new UserRequestException("Permission denied to requestId '$requestId' for user '$userId'", UserRequestException::PERMISSION_DENIED);
	}

	private function getSqlTime() {
		if (isset($this->sqlTime)) {
			return $this->sqlTime;
		}

		$q = $this->database->query('SELECT NOW() as now;');

		return $this->sqlTime = $q->fetch()->now;
	}
}
