<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 26.9.15
 * Time: 21:41
 */

namespace Trejjam\Authorization\User;

use Nette,
	Trejjam;

class Authenticator implements Nette\Security\IAuthenticator
{
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var Trejjam\Authorization\Acl\Acl
	 */
	protected $acl;
	/**
	 * @var IdentityHash
	 */
	protected $identityHash;

	public function __construct(Manager $manager, Trejjam\Authorization\Acl\Acl $acl, IdentityHash $identityHash) {
		$this->manager = $manager;
		$this->acl = $acl;
		$this->identityHash = $identityHash;
	}

	/**
	 * Performs an authentication against e.g. database.
	 * and returns IIdentity on success or throws AuthenticationException
	 * @var array $credentials
	 * @return Nette\Security\IIdentity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials) {
		list($username, $password) = $credentials;

		if (is_null($password)) {
			$user = $this->manager->getItem($username);
		}
		else {
			$user = $this->manager->getUserByUsername($username);

			if (is_null($user->password)) {
				throw new Trejjam\Authorization\User\AuthenticatorException('User has not have defined password.', Trejjam\Authorization\User\AuthenticatorException::NOT_DEFINED_PASSWORD);
			}
			else if (!Nette\Security\Passwords::verify($password, $user->password)) {
				throw new Trejjam\Authorization\User\AuthenticatorException('The password is incorrect.', Trejjam\Authorization\User\AuthenticatorException::INVALID_CREDENTIAL);
			}
			else if (Nette\Security\Passwords::needsRehash($user->password)) {
				$this->manager->changePassword($user, $password);
			}
		}

		if ($this->manager->isEnableLogin($user)) {
			$baseUserArr = (array)$this->manager->getUserInfo($user);

			$baseUserArr["login_time"] = new Nette\Utils\DateTime();

			$role = $this->acl->getUserRoles($user);

			return new Identity($user->id, $role, $baseUserArr);
		}
		else {
			//this may not happen
			throw new Trejjam\Authorization\User\AuthenticatorException;
		}
	}
}
