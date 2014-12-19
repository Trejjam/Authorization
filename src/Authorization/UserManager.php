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

	public function __construct(Nette\Database\Context $database, Acl $acl) {
		$this->database = $database;
		$this->acl = $acl;
	}
	public function setTables(array $tables) {
		$this->tables = $tables;
	}
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
	}

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
	protected function getUser($username) {
		$this->isUsernameValid($username);

		$user = $this->database->table($this->tables["users"]["table"])
							   ->where([$this->tables["users"]["username"]["name"] => $username])->fetch();

		if ($user) {
			return $user;
		}
		else {
			throw new \Exception("The user is not exist");
		}
	}

	public function setUpdated($id) {
		$user = $this->database->table($this->tables["users"]["table"])->get($id);

		if ($user) {
			$user->update([
				$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
			]);
		}
		else {
			throw new \Exception("The user with ID $id not exist");
		}
	}
	public function add($username, $password) {
		$this->isUsernameValid($username);

		$this->database->table($this->tables["users"]["table"])->insert([
			$this->tables["users"]["username"]["name"]    => $username,
			$this->tables["users"]["password"]            => Passwords::hash($password),
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}
	public function changePassword($username, $password) {
		$user = $this->getUser($username);

		$user->update([
			$this->tables["users"]["password"]            => Passwords::hash($password),
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}
	public function setStatus($username, $status) {
		$user = $this->getUser($username);

		$user->update([
			$this->tables["users"]["status"]["name"]      => $status,
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}
	public function setActivated($username, $activated = NULL) {
		if (is_null($activated)) {
			$activated = $this->tables["users"]["activated"]["yes"];
		}

		$user = $this->getUser($username);

		$user->update([
			$this->tables["users"]["activated"]["name"]   => $activated,
			$this->tables["users"]["timestamp"]["edited"] => new Nette\Database\SqlLiteral('NOW()'),
		]);
	}

	public function getUsersList() {
		$out=[];

		$usersTable= $this->tables["users"];
		$cells=[
			$usersTable["id"],
			$usersTable["status"]["name"],
			$usersTable["activated"]["name"],
			$usersTable["username"],
			$usersTable["timestamp"]["created"],
			$usersTable["timestamp"]["edited"],
		];

		foreach ($this->database->table($this->tables["users"]["table"]) as $k=>$v) {
			$out[$k]=[];
			foreach ($cells as $v2) {
				$out[$k][$v2]=$v->{$v2};
			}
		}

		return $out;
	}
}
