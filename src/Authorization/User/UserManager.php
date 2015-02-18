<?php

namespace Trejjam\Authorization;

use Nette,
	Nette\Security\Passwords;


/**
 * Users management.
 */
class UserManager extends Nette\Object implements Nette\Security\IAuthenticator
{
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var Acl
	 */
	protected $acl;

	protected $tables;

	/**
	 * @param Nette\Database\Context $database
	 * @param Acl                    $acl
	 */
	public function __construct(Nette\Database\Context $database, Acl $acl) {
		$this->database = $database;
		$this->acl = $acl;
	}
	/**
	 * @param array $tables
	 */
	public function setTables(array $tables) {
		$this->tables = $tables;
	}

	/**
	 * Performs an authentication.
	 * @param array $credentials
	 * @return Nette\Security\Identity|Nette\Security\IIdentity
	 */
	public function authenticate(array $credentials) {
		list($username, $password) = $credentials;

		$row = $this->database->table($this->tables["users"]["table"])
							  ->where(is_null($password) ? $this->tables["users"]["id"] : $this->tables["users"]["username"]["name"], $username)
							  ->fetch();

		if (!$row) {
			throw new UserManagerException('The username is incorrect.', UserManagerException::NOT_EXIST_USERNAME);

		}
		elseif (!is_null($password) && !Passwords::verify($password, $row[$this->tables["users"]["password"]])) {
			throw new UserManagerException('The password is incorrect.', UserManagerException::INVALID_CREDENTIAL);

		}
		elseif (!is_null($password) && Passwords::needsRehash($row[$this->tables["users"]["password"]])) {
			$row->update(array(
				$this->tables["users"]["password"] => Passwords::hash($password),
			));
		}

		if ($row[$this->tables["users"]["status"]["name"]] != $this->tables["users"]["status"]["accept"]) {
			throw new UserManagerException('The user is not enable.', UserManagerException::NOT_ENABLE);
		}
		if ($row[$this->tables["users"]["activated"]["name"]] != $this->tables["users"]["activated"]["accept"]) {
			throw new UserManagerException('The user is not activated.', UserManagerException::NOT_ACTIVATED);
		}

		$arr = $row->toArray();
		unset($arr[$this->tables["users"]["password"]]);
		$arr["login_time"] = new Nette\Utils\DateTime();
		$role = $this->acl->getUserRoles($row[$this->tables["users"]["id"]]);

		return new Nette\Security\Identity($row[$this->tables["users"]["id"]], $role, $arr);
	}

	/**
	 * @param $username
	 * @return bool
	 * @throws UserManagerException
	 */
	public function isUsernameValid($username) {
		if (strlen($username) > $this->tables["users"]["username"]["length"]) {
			throw new UserManagerException("The username is too long", UserManagerException::LONG_USERNAME);
		}

		switch ($this->tables["users"]["username"]["match"]) {
			case "email":
				if (!Nette\Utils\Validators::isEmail($username)) {
					throw new UserManagerException("The username is not an email", UserManagerException::NOT_VALID_USERNAME);
				}

				break;
			default:
				if (!preg_match($this->tables["users"]["username"]["match"], $username)) {
					throw new UserManagerException("The username is not match regex " . $this->tables["users"]["username"]["match"], UserManagerException::NOT_VALID_USERNAME);
				}
		}

		return TRUE;
	}

	/**
	 * @param $username
	 * @return int
	 * @throws UserManagerException
	 */
	public function getUserId($username) {
		$this->isUsernameValid($username);

		$user = $this->database->table($this->tables["users"]["table"])
							   ->where([$this->tables["users"]["username"]["name"] => $username])->fetch();

		if ($user) {
			return $user[$this->tables["users"]["id"]];
		}
		else {
			throw new UserManagerException("The user is not exist", UserManagerException::NOT_EXIST_USERNAME);
		}
	}
	/**
	 * @param        $username
	 * @param string $type [username|id]
	 * @return Nette\Database\Table\ActiveRow
	 * @throws UserManagerException
	 */
	protected function getUser($username, $type = "username") {
		switch ($type) {
			case "username":
				$this->isUsernameValid($username);

				$user = $this->database->table($this->tables["users"]["table"])
									   ->where([$this->tables["users"]["username"]["name"] => $username])->fetch();
				break;
			case "id":
				$user = $this->database->table($this->tables["users"]["table"])
									   ->where([$this->tables["users"]["id"] => $username])->fetch();
				break;
			default:
				throw new UserManagerException("Unrecognized type", UserManagerException::UNRECOGNIZED_TYPE);
		}

		if ($user) {
			return $user;
		}
		else {
			throw new UserManagerException("The user is not exist", UserManagerException::NOT_EXIST_USERNAME);
		}
	}

	/**
	 * @param string $username
	 * @param string $type [username|id]
	 * @throws UserManagerException
	 */
	public function setUpdated($username, $type = "username") {
		$user = $this->getUser($username, $type);

		if ($user) {
			$this->database->table($this->tables['identityHash']['table'])->where([
				$this->tables['identityHash']['userId']         => $user->{$this->tables["users"]["id"]},
				$this->tables['identityHash']['action']['name'] => 'none',
			])->update([
				$this->tables['identityHash']['action']['name'] => 'reload',
			]);
		}
		else {
			throw new UserManagerException("The user $username:$type not exist", UserManagerException::NOT_EXIST_USERNAME);
		}
	}
	/**
	 * @param $username
	 * @param $password
	 * @return bool
	 * @throws UserManagerException
	 */
	public function add($username, $password) {
		$this->isUsernameValid($username);

		try {
			$this->getUser($username);

			return FALSE;
		}
		catch (UserManagerException $e) {

		}

		$this->database->table($this->tables["users"]["table"])->insert([
			$this->tables["users"]["username"]["name"] => $username,
			$this->tables["users"]["password"]         => Passwords::hash($password),
		]);

		return TRUE;
	}
	/**
	 * @param string $username
	 * @param string $password
	 * @param string $type [username|id]
	 * @throws UserManagerException
	 */
	public function changePassword($username, $password, $type = "username") {
		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["password"] => Passwords::hash($password),
		]);
	}
	/**
	 * @param string $username
	 * @param string $status
	 * @param string $type [username|id]
	 * @throws UserManagerException
	 */
	public function setStatus($username, $status, $type = "username") {
		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["status"]["name"] => $status,
		]);
	}
	/**
	 * @param        $username
	 * @param string $type
	 * @return string
	 * @throws UserManagerException
	 */
	public function getStatus($username, $type = 'username') {
		$user = $this->getUser($username, $type);

		return $user->{$this->tables["users"]["status"]["name"]};
	}
	/**
	 * @return array
	 */
	public function getStatusOptions() {
		return $this->tables["users"]["status"]['options'];
	}

	/**
	 * @param string $username
	 * @param string $activated
	 * @param string $type [username|id]
	 * @throws UserManagerException
	 */
	public function setActivated($username, $activated = NULL, $type = "username") {
		if (is_null($activated)) {
			$activated = $this->tables["users"]["activated"]["accept"];
		}

		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["activated"]["name"] => $activated,
		]);
	}
	/**
	 * @param        $username
	 * @param string $type
	 * @return string
	 * @throws UserManagerException
	 */
	public function getActivated($username, $type = 'username') {
		$user = $this->getUser($username, $type);

		return $user->{$this->tables["users"]["activated"]["name"]};
	}
	/**
	 * @return array
	 */
	public function getActivatedOptions() {
		return $this->tables["users"]["activated"]['options'];
	}

	/**
	 * @return \stdClass[]
	 * @throws UserManagerException
	 */
	public function getUsersList() {
		$out = [];

		foreach ($this->database->table($this->tables["users"]["table"]) as $k => $v) {
			$out[$k] = $this->getUserInfo($v);
		}

		return $out;
	}

	/**
	 * @param string $username
	 * @param string $type [auto|activeRow|username|id]
	 * @return \stdClass
	 * @throws UserManagerException
	 */
	public function getUserInfo($username, $type = "auto") {
		if ($type == "auto") {
			if ($username instanceof Nette\Database\Table\ActiveRow) {
				$type = "activeRow";
			}
			else {
				$type = "username";
			}
		}

		switch ($type) {
			case "activeRow":
				$user = $username;

				break;
			case "username":
			case "id":
				$user = $this->getUser($username, $type);

				break;
			default:
				throw new UserManagerException("Unrecognized type", UserManagerException::UNRECOGNIZED_TYPE);
		}

		$usersTable = $this->tables["users"];
		$cells = [
			$usersTable["id"],
			$usersTable["status"]["name"],
			$usersTable["activated"]["name"],
			$usersTable["username"]["name"],
			$usersTable["timestamp"]["created"],
		];

		$out = new \stdClass;
		foreach ($cells as $v) {
			$out->{$v} = $user->{$v};
		}

		return $out;
	}
}
