<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 11.1.15
 * Time: 20:34
 */

namespace Trejjam\Authorization\User;


use Nette,
	Trejjam;

class User extends Nette\Security\User
{
	/**
	 * @var Storage
	 */
	protected $storage;
	/**
	 * @var IdentityHash
	 */
	protected $identityHash;
	/**
	 * @var bool
	 */
	protected $reloadChangedUser;

	public function __construct(Storage $storage, Authenticator $authenticator = NULL, Nette\Security\IAuthorizator $authorizator = NULL, IdentityHash $identityHash) {
		parent::__construct($storage, $authenticator, $authorizator);

		$this->storage = $storage;
		$this->identityHash = $identityHash;
	}

	/**
	 * @param bool $reloadChangedUser
	 */
	public function setParams($reloadChangedUser) {
		$this->reloadChangedUser = $reloadChangedUser;

		if ($reloadChangedUser) {
			$this->checkIdentityAction();
		}
	}

	public function logout($clearIdentity = FALSE) {
		$identity = $this->storage->getIdentity();
		if (!is_null($identity)) {
			$this->identityHash->setAction($identity->getId(), IdentityHash::ACTION_DESTROYED);
		}

		parent::logout($clearIdentity);
	}
	protected function checkIdentityAction() {
		if ($this->storage->isAuthenticated()) {
			$identity = $this->storage->getIdentity();

			$action = $this->identityHash->getHashAction(is_null($identity) ? $identity : $identity->hash);

			if (is_null($action)) {
				parent::logout();
			}

			switch ($action) {
				case IdentityHash::ACTION_RELOAD:
					try {
						$this->login($this->getId(), NULL);
					}
					catch (Trejjam\Authorization\User\ManagerException $e) {
						$this->logout();
					}
					catch (Trejjam\Authorization\User\AuthenticatorException $e) {
						//this may not happen
						throw $e;
					}

					break;
				case IdentityHash::ACTION_LOGOUT:
					$this->logout();

					break;
				case IdentityHash::ACTION_DESTROYED:
					$this->logout();

					break;
			}
		}
	}
}
