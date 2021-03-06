<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 31.12.14
 * Time: 13:51
 */

namespace Trejjam\Authorization\User;

use Nette,
	Trejjam;

class Request extends Trejjam\Utils\Helpers\Database\ABaseList
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

	/**
	 * @param int|Nette\Database\Table\IRow $id
	 * @return \stdClass
	 */
	public function getItem($id) {
		$row = $this->getRow($id);

		$out = (object)[
			static::ROW => $row,
			'id'        => $row->{$this->tables['userRequest']['id']},
			'userId'    => $row->{$this->tables['userRequest']['user_id']},
			'hash'      => $row->{$this->tables['userRequest']['hash']},
			'type'      => $row->{$this->tables['userRequest']['type']},
			'used'      => $row->{$this->tables['userRequest']['used']} == $this->tables['userRequest']['positive'],
		];

		return $out;
	}

	protected function isTypeValid($type) {
		return in_array($type, $this->tables['userRequest']['type']['option']);
	}

	public function generateHash($userId, $type, $timeout = NULL) {
		if (!$this->isTypeValid($type)) {
			throw new Trejjam\Authorization\User\RequestException("Type '$type' is not valid or registered");
		}

		$hash = Nette\Utils\Random::generate($this->tables['userRequest']['hash']['length'], '0-9A-Z');
		if (is_null($timeout)) {
			$timeout = $this->tables['userRequest']['timeout']['default'];
		}

		$insertion = $this->getTable()->insert([
			$this->tables['userRequest']['userId']          => isset($userId->{static::ROW}) ? $userId->id : $userId,
			$this->tables['userRequest']['hash']['name']    => Nette\Security\Passwords::hash($hash),
			$this->tables['userRequest']['type']['name']    => $type,
			$this->tables['userRequest']['timeout']['name'] => $timeout === FALSE ? NULL : new Nette\Database\SqlLiteral('NOW() + INTERVAL ' . $timeout),
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
		if (
			(!isset($userId->{static::ROW}) && !Nette\Utils\Validators::isNumericInt($userId))
			|| !Nette\Utils\Validators::isNumericInt($requestId)
			|| empty($hash)
		) {
			throw new Trejjam\Authorization\User\RequestException('Invalid input parameters', Trejjam\Authorization\User\RequestException::INVALID_INPUT);
		}

		if ($row = $this->getTable()->where([
			$this->tables['userRequest']['userId'] => isset($userId->{static::ROW}) ? $userId->id : $userId,
			$this->tables['userRequest']['id']     => $requestId,
		])->fetch()
		) {
			if (!Nette\Security\Passwords::verify($hash, $row->{$this->tables['userRequest']['hash']['name']})) {
				throw new Trejjam\Authorization\User\RequestException('Hash is corrupted', Trejjam\Authorization\User\RequestException::CORRUPTED_HASH);
			}

			if ($row->{$this->tables['userRequest']['used']['name']} == $this->tables['userRequest']['used']['positive']) {
				throw new Trejjam\Authorization\User\RequestException('Hash was used', Trejjam\Authorization\User\RequestException::USED_HASH);
			}

			if (!is_null($row->{$this->tables['userRequest']['timeout']['name']}) && $row->{$this->tables['userRequest']['timeout']['name']} < $this->getSqlTime()) {
				throw new Trejjam\Authorization\User\RequestException('Hash timeout', Trejjam\Authorization\User\RequestException::HASH_TIMEOUT);
			}

			if ($invalidateHash) {
				$row->update([
					$this->tables['userRequest']['used']['name'] => $this->tables['userRequest']['used']['positive'],
				]);
			}

			return $row->{$this->tables['userRequest']['type']['name']};
		}

		throw new Trejjam\Authorization\User\RequestException("Permission denied to requestId '$requestId' for user '$userId'", Trejjam\Authorization\User\RequestException::PERMISSION_DENIED);
	}

	protected function getSqlTime() {
		if (isset($this->sqlTime)) {
			return $this->sqlTime;
		}

		$q = $this->database->query('SELECT NOW() as now;');

		return $this->sqlTime = $q->fetch()->now;
	}
}
