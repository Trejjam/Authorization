<?php

namespace Trejjam\Authorization;

use Nette,
	Nette\Security\Passwords;


/**
 * Users management.
 */
class UserManager extends Nette\Object implements Nette\Security\IAuthenticator
{
	const
		NOT_ENABLE = 10,
		NOT_ACTIVATED = 11;

	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var Acl
	 */
	protected $acl;

	protected $tables;
	protected $reloadChangedUser;

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
	 * @param $reloadChangedUser
	 */
	public function setParams($reloadChangedUser) {
		$this->reloadChangedUser = $reloadChangedUser;
	}

	/**
	 * Performs an authentication.
	 * @param array $credentials
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials) {
		list($username, $password) = $credentials;

		$row = $this->database->table($this->tables["users"]["table"])
							  ->where($this->tables["users"]["username"]["name"], $username)->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('The username is incorrect.', self::IDENTITY_NOT_FOUND);

		}
		elseif (!Passwords::verify($password, $row[$this->tables["users"]["password"]])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);

		}
		elseif (Passwords::needsRehash($row[$this->tables["users"]["password"]])) {
			$row->update(array(
				$this->tables["users"]["password"] => Passwords::hash($password),
			));
		}

		if ($row[$this->tables["users"]["status"]["name"]] != $this->tables["users"]["status"]["accept"]) {
			throw new Nette\Security\AuthenticationException('The user is not enable.', self::NOT_ENABLE);
		}
		if ($row[$this->tables["users"]["activated"]["name"]] != $this->tables["users"]["activated"]["yes"]) {
			throw new Nette\Security\AuthenticationException('The user is not activated.', self::NOT_ACTIVATED);
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
	 * @throws \Exception
	 */
	public function isUsernameValid($username) {
		if (strlen($username) > $this->tables["users"]["username"]["length"]) {
			throw new \Exception("The username is too long");
		}

		switch ($this->tables["users"]["username"]["match"]) {
			case "email":
				if (!Nette\Utils\Validators::isEmail($username)) {
					throw new \Exception("The username is not an email");
				}

				break;
			default:
				if (!preg_match($this->tables["users"]["username"]["match"], $username)) {
					throw new \Exception("The username is not match regex " . $this->tables["users"]["username"]["match"]);
				}
		}

		return TRUE;
	}

	/**
	 * @param $username
	 * @return int
	 * @throws \Exception
	 */
	public function getUserId($username) {
		$this->isUsernameValid($username);

		$user = $this->database->table($this->tables["users"]["table"])
							   ->where([$this->tables["users"]["username"]["name"] => $username])->fetch();

		if ($user) {
			return $user[$this->tables["users"]["id"]];
		}
		else {
			throw new \Exception("The user is not exist");
		}
	}
	/**
	 * @param        $username
	 * @param string $type [username|id]
	 * @return Nette\Database\Table\ActiveRow
	 * @throws \Exception
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
				throw new \Exception("Unrecognized type");
		}

		if ($user) {
			return $user;
		}
		else {
			throw new \Exception("The user is not exist");
		}
	}

	/**
	 * @param string $username
	 * @param string $type [username|id]
	 * @throws \Exception
	 */
	public function setUpdated($username, $type = "username") {
		$user = $this->getUser($username, $type);

		if ($user) {
			$user->update([
				$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
			]);
		}
		else {
			throw new \Exception("The user $username:$type not exist");
		}
	}
	/**
	 * @param $username
	 * @param $password
	 * @return bool
	 * @throws \Exception
	 */
	public function add($username, $password) {
		$this->isUsernameValid($username);

		try {
			$this->getUser($username);
			return false;
		}
		catch(\Exception $e) {}

		$this->database->table($this->tables["users"]["table"])->insert([
			$this->tables["users"]["username"]["name"]    => $username,
			$this->tables["users"]["password"]            => Passwords::hash($password),
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
		return true;
	}
	/**
	 * @param string $username
	 * @param string $password
	 * @param string $type [username|id]
	 * @throws \Exception
	 */
	public function changePassword($username, $password, $type = "username") {
		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["password"]            => Passwords::hash($password),
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}
	/**
	 * @param string $username
	 * @param string $status
	 * @param string $type [username|id]
	 * @throws \Exception
	 */
	public function setStatus($username, $status, $type = "username") {
		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["status"]["name"]      => $status,
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}
	/**
	 * @param string $username
	 * @param string $activated
	 * @param string $type [username|id]
	 * @throws \Exception
	 */
	public function setActivated($username, $activated = NULL, $type = "username") {
		if (is_null($activated)) {
			$activated = $this->tables["users"]["activated"]["yes"];
		}

		$user = $this->getUser($username, $type);

		$user->update([
			$this->tables["users"]["activated"]["name"]   => $activated,
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}

	/**
	 * @return \stdClass[]
	 * @throws \Exception
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
	 * @throws \Exception
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
				$user = $this->getUser($username);

				break;
			case "id":
				$user = $this->getUser($username, $type);

				break;
			default:
				throw new \Exception("Unrecognized type");
		}

		$usersTable = $this->tables["users"];
		$cells = [
			$usersTable["id"],
			$usersTable["status"]["name"],
			$usersTable["activated"]["name"],
			$usersTable["username"]["name"],
			$usersTable["timestamp"]["created"],
			$usersTable["timestamp"]["edited"],
		];

		$out = new \stdClass;
		foreach ($cells as $v) {
			$out->{$v} = $user->{$v};
		}

		return $out;
	}
}
