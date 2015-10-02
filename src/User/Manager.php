<?php

namespace Trejjam\Authorization\User;

use Nette,
	Trejjam;


/**
 * Users management.
 */
class Manager extends Trejjam\Utils\Helpers\Database\ABaseList
{
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;

	protected $tables;

	/**
	 * @param Nette\Database\Context $database
	 * @param IdentityHash           $identityHash
	 */
	public function __construct(Nette\Database\Context $database, IdentityHash $identityHash) {
		$this->database = $database;
		$this->identityHash = $identityHash;
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
		return $this->database->table($this->tables['users']['table']);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function getTableCell($key) {
		if (isset($this->tables['users']['items'][$key])) {
			return $this->tables['users']['items'][$key];
		}
		else {
			return $key;
		}
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
			$id = $this->getTable()->where([
				$this->getTableCell('id') => $id,
			])->fetch();

			if (!$id) {
				throw new Trejjam\Authorization\User\ManagerException("User id '$id' not found", Trejjam\Authorization\User\ManagerException::ID_NOT_FOUND);
			}
		}

		$out = (object)[
			static::ROW => $id,
		];

		foreach ($this->tables['users']['items'] as $k => $v) {
			if (Nette\Utils\Validators::isNumericInt($k)) {
				$k = $v;
			}

			$out->$k = $id->{$v};
		}

		return $out;
	}

	/**
	 * @param $username
	 * @return bool
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function isUsernameValid($username) {
		if (strlen($username) > $this->tables['users']['username']['length']) {
			throw new Trejjam\Authorization\User\ManagerException('The username is too long', Trejjam\Authorization\User\ManagerException::LONG_USERNAME);
		}

		switch ($this->tables['users']['username']['match']) {
			case 'email':
				if (!Nette\Utils\Validators::isEmail($username)) {
					throw new Trejjam\Authorization\User\ManagerException('The username is not an email', Trejjam\Authorization\User\ManagerException::NOT_VALID_USERNAME);
				}

				break;
			default:
				if (!preg_match($this->tables['users']['username']['match'], $username)) {
					throw new Trejjam\Authorization\User\ManagerException('The username is not match regex ' . $this->tables['users']['username']['match'], Trejjam\Authorization\User\ManagerException::NOT_VALID_USERNAME);
				}
		}

		return TRUE;
	}

	public function getUserByUsername($username) {
		if ($this->isUsernameValid($username)) {
			$users = $this->getList(NULL, [
				static::STRICT => [
					$this->getTableCell('username') => $username,
				]
			], 1);

			$user = reset($users);

			if ($user) {
				return $user;
			}
			else {
				throw new Trejjam\Authorization\User\ManagerException('The user is not exist', Trejjam\Authorization\User\ManagerException::NOT_EXIST_USERNAME);
			}
		}
		else {
			//this may not happen
			throw new Trejjam\Authorization\User\ManagerException;
		}
	}

	/**
	 * @param string|int|\stdClass|Nette\Database\Table\IRow $username
	 * @param string                                         $type [username|id]
	 * @return \stdClass
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	protected function getUser($username, $type = 'username') {
		if (isset($username->{static::ROW})) {
			return $username;
		}
		else if ($username instanceof Nette\Database\Table\IRow) {
			return $this->getItem($username);
		}

		switch ($type) {
			case 'username':
				return $this->getUserByUsername($username);

				break;
			case 'id':
				return $this->getItem($username);

				break;
			default:
				throw new Trejjam\Authorization\User\ManagerException('Unrecognized type', Trejjam\Authorization\User\ManagerException::UNRECOGNIZED_TYPE);
		}
	}

	/**
	 * @param $username
	 * @param $password
	 * @return bool
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function add($username, $password = NULL) {
		if ($this->isUsernameValid($username)) {
			try {
				$this->getUserByUsername($username);

				return FALSE;
			}
			catch (Trejjam\Authorization\User\ManagerException $e) {
				$this->database->table($this->tables['users']['table'])->insert([
					$this->getTableCell('username') => $username,
					$this->getTableCell('password') => is_null($password) ? $password : Nette\Security\Passwords::hash($password),
				]);

				return TRUE;
			}
		}
		else {
			//this may not happen
			throw new Trejjam\Authorization\User\ManagerException;
		}
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $type [username|id]
	 * @return bool
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function changePassword($username, $password, $type = 'username') {
		$user = $this->getUser($username, $type);

		$user->{static::ROW}->update([
			$this->getTableCell('password') => Nette\Security\Passwords::hash($password),
		]);

		return TRUE;
	}

	public function changeUsername($user, $username, $type = 'username') {
		$user = $this->getUser($user, $type);

		$user->{static::ROW}->update([
			$this->getTableCell('username') => $username,
		]);

		return TRUE;
	}

	/**
	 * @param string $username
	 * @param string $type [username|id]
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function setUpdated($username, $type = 'username') {
		$user = $this->getUser($username, $type);

		if ($user) {
			$this->identityHash->setAction($user, IdentityHash::ACTION_RELOAD);
		}
		else {
			//this may not happen
			throw new Trejjam\Authorization\User\ManagerException;
		}
	}

	public function isEnableLogin($user, $type = 'username') {
		$user = $this->getUser($user, $type);

		if ($user->status != $this->tables['users']['status']['accept']) {
			throw new Trejjam\Authorization\User\ManagerException('The user is not enable.', Trejjam\Authorization\User\ManagerException::NOT_ENABLE);
		}
		else if ($user->activated != $this->tables['users']['activated']['accept']) {
			throw new Trejjam\Authorization\User\ManagerException('The user is not activated.', Trejjam\Authorization\User\ManagerException::NOT_ACTIVATED);
		}
		else {
			return TRUE;
		}
	}

	/**
	 * @param string $username
	 * @param string $activated
	 * @param string $type [username|id]
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function setActivated($username, $activated = NULL, $type = 'username') {
		if (is_null($activated)) {
			$activated = $this->tables['users']['activated']['accept'];
		}

		if (!in_array($activated, $this->getActivatedOptions())) {
			throw new Trejjam\Authorization\User\ManagerException('Activated has unknown value.', Trejjam\Authorization\User\ManagerException::UNKNOWN_VALUE);
		}

		$user = $this->getUser($username, $type);

		$user->{static::ROW}->update([
			$this->getTableCell('activated') => $activated,
		]);
	}
	/**
	 * @param        $username
	 * @param string $type [username|id]
	 * @return string
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function getActivated($username, $type = 'username') {
		$user = $this->getUser($username, $type);

		return $user->activated;
	}

	/**
	 * @return array
	 */
	public function getActivatedOptions() {
		return $this->tables['users']['activated']['options'];
	}

	/**
	 * @param string $username
	 * @param string $status
	 * @param string $type [username|id]
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function setStatus($username, $status, $type = 'username') {
		if (!in_array($status, $this->getStatusOptions())) {
			throw new Trejjam\Authorization\User\ManagerException('Status has unknown value.', Trejjam\Authorization\User\ManagerException::UNKNOWN_VALUE);
		}

		$user = $this->getUser($username, $type);

		$user->{static::ROW}->update([
			$this->getTableCell('status') => $status,
		]);
	}

	/**
	 * @param        $username
	 * @param string $type [username|id]
	 * @return string
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function getStatus($username, $type = 'username') {
		$user = $this->getUser($username, $type);

		return $user->status;
	}

	/**
	 * @return array
	 */
	public function getStatusOptions() {
		return $this->tables['users']['status']['options'];
	}

	/**
	 * @param string $username
	 * @param string $type [auto|activeRow|username|id]
	 * @return \stdClass
	 * @throws Trejjam\Authorization\User\ManagerException
	 */
	public function getUserInfo($username, $type = 'auto') {
		if ($type == 'auto') {
			if ($username instanceof Nette\Database\Table\IRow) {
				$type = 'iRow';
			}
			else {
				$type = 'username';
			}
		}

		switch ($type) {
			case 'iRow':
			case 'activeRow':
			case 'username':
			case 'id':
				$user = $this->getUser($username, $type);

				break;
			default:
				throw new Trejjam\Authorization\User\ManagerException('Unrecognized type', Trejjam\Authorization\User\ManagerException::UNRECOGNIZED_TYPE);
		}

		$baseUser = $user;
		unset($baseUser->{Trejjam\Utils\Helpers\Database\ABaseList::ROW});
		unset($baseUser->password);

		return $baseUser;
	}

	/**
	 * @param        $username
	 * @param bool   $all
	 * @param string $type [username|id]
	 * @return array
	 */
	public function getIdentityHash($username, $all = FALSE, $type = 'username') {
		$user = $this->getUser($username, $type);

		$out = [];

		foreach ($this->identityHash->getUserHashes($user, $all ? [
			IdentityHash::ACTION_NONE,
			IdentityHash::ACTION_RELOAD,
			IdentityHash::ACTION_DESTROYED,
			IdentityHash::ACTION_LOGOUT,
		] : [
			IdentityHash::ACTION_NONE,
			IdentityHash::ACTION_RELOAD,
		]) as $v) {
			$out[$v->hash] = $v->ip;
		}

		return $out;
	}


	/**
	 * @param array $sort
	 * @param array $filter
	 * @param null  $limit
	 * @param null  $offset
	 * @return \stdClass[]
	 *
	 * @deprecated
	 */
	public function getUsersList(array $sort = NULL, array $filter = NULL, $limit = NULL, $offset = NULL) {
		return $this->getList($sort, $filter, $limit, $offset);
	}

	/**
	 * @param array|NULL $filter
	 * @return int
	 *
	 * @deprecated
	 */
	public function getUserCount(array $filter = NULL) {
		return $this->getCount($filter);
	}
}
