<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 11.1.15
 * Time: 20:34
 */

namespace Trejjam\Authorization;


use Nette,
	Trejjam;

class User extends Nette\Security\User
{
	/**
	 * @var UserStorage
	 */
	protected $storage;

	protected $reloadChangedUser;

	public function __construct(UserStorage $storage, Nette\Security\IAuthenticator $authenticator = NULL, Nette\Security\IAuthorizator $authorizator = NULL) {
		parent::__construct($storage, $authenticator, $authorizator);

		$this->storage = $storage;
	}

	public function setParams($reloadChangedUser) {
		$this->reloadChangedUser = $reloadChangedUser;

		if ($reloadChangedUser) {
			$this->checkIdentityAction();
		}
	}

	public function logout($clearIdentity = FALSE) {
		$this->storage->setAction('destroyed');

		parent::logout($clearIdentity);
	}
	protected function checkIdentityAction() {
		if ($this->storage->isAuthenticated()) {
			$action = $this->storage->getAction();

			if (is_null($action)) {
				parent::logout();
			}

			switch ($action) {
				case 'reload':
					$this->login($this->getId(), NULL);

					break;
				case 'logout':
					$this->logout();

					break;
				case 'destroyed':
					$this->logout();

					break;
			}
		}
	}
}