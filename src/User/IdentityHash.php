<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 26.9.15
 * Time: 20:43
 */

namespace Trejjam\Authorization\User;

use Nette,
	Trejjam;

class IdentityHash extends Trejjam\Utils\Helpers\Database\ABaseList
{
	const
		ACTION_NONE = 'none',
		ACTION_RELOAD = 'reload',
		ACTION_LOGOUT = 'logout',
		ACTION_DESTROYED = 'destroyed';

	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var array
	 */
	protected $tables;

	/**
	 * @param Nette\Database\Context $database
	 */
	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
	}

	/**
	 * @param array $tables
	 */
	public function setTables(array $tables) {
		$this->tables = $tables;
	}

	/**
	 * @return Nette\Database\Table\Selection
	 */
	protected function getTable() {
		return $this->database->table($this->tables['identityHash']['table']);
	}

	/**
	 * @param int|Nette\Database\Table\IRow $id
	 * @return \stdClass
	 */
	public function getItem($id) {
		if (isset($id->{static::ROW})) {
			$id = $id->{static::ROW};
		}
		else if (!$id instanceof Nette\Database\Table\IRow) {
			$id = $this->getTable()->get($id);
		}

		$out = (object)[
			static::ROW => $id,
			'id'        => $id->{$this->tables['identityHash']['id']},
			'userId'    => $id->{$this->tables['identityHash']['userId']},
			'hash'      => $id->{$this->tables['identityHash']['hash']},
			'ip'        => $id->{$this->tables['identityHash']['ip']},
			'action'    => $id->{$this->tables['identityHash']['action']},
		];

		return $out;
	}

	public function getUserHashes($user, array $actions = []) {
		return $this->getRelatedList($user, $this->tables['identityHash']['userId'], ['id'], [
			static::STRICT => [
				$this->tables['identityHash']['action'] => $actions,
			]
		]);
	}

	protected function generateHash() {
		do {
			$hash = Nette\Utils\Random::generate(32);
		} while ($this->getTable()->where([$this->tables['identityHash']['hash'] => $hash])->fetch());

		return $hash;
	}

	public function createIdentityHash($userId, $ip) {
		$this->getTable()->insert([
			$this->tables['identityHash']['userId'] => $userId,
			'hash'                                  => $hash = $this->generateHash(),
			'ip'                                    => $ip,
		]);

		return $hash;
	}

	public function setAction($user, $action, $identityHash = NULL) {
		if (!in_array($action, [
			static::ACTION_NONE,
			static::ACTION_RELOAD,
			static::ACTION_LOGOUT,
			static::ACTION_DESTROYED,
		])
		) {
			throw new Trejjam\Authorization\User\IdentityHashException("Action '$action' is not enabled.", Trejjam\Authorization\User\IdentityHashException::ACTION_NOT_ENABLED);
		}

		$where = [
			$this->tables['identityHash']['userId'] => Nette\Utils\Validators::isNumericInt($user) ? $user : $user->id,
		];
		if (!is_null($identityHash)) {
			$where[$this->tables['identityHash']['hash']] = $identityHash;
		}

		switch ($action) {
			case static::ACTION_RELOAD:
				$where[$this->tables['identityHash']['action']] = static::ACTION_NONE;

				break;
		}

		$this->getTable()->where($where)->update([
			$this->tables['identityHash']['action'] => $action,
		]);
	}

	public function getHashAction($hash) {
		if (is_null($hash)) {
			return NULL;
		}

		$hashes = $this->getList(NULL, [
			Trejjam\Utils\Helpers\Database\ABaseList::STRICT => [
				$this->tables['identityHash']['hash'] => $hash,
			],
		], 1);

		$action = reset($hashes);

		return $action ? $action->action : NULL;
	}

	public function setHashAction($hash, $action) {
		if (!in_array($action, [
			static::ACTION_NONE,
			static::ACTION_RELOAD,
			static::ACTION_LOGOUT,
			static::ACTION_DESTROYED,
		])
		) {
			throw new Trejjam\Authorization\User\IdentityHashException("Action '$action' is not enabled.", Trejjam\Authorization\User\IdentityHashException::ACTION_NOT_ENABLED);
		}

		$this->getTable()->where([
			$this->tables['identityHash']['hash'] => $hash,
		])->update([
			$this->tables['identityHash']['action'] => $action,
		]);
	}
}
