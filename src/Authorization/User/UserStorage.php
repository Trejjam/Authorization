<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 11.1.15
 * Time: 16:25
 */

namespace Trejjam\Authorization;


use Nette,
	Trejjam;

class UserStorage extends Nette\Http\UserStorage
{
	/**
	 * @var Nette\Http\Session
	 */
	protected $sessionHandler;
	/**
	 * @var \Browser
	 */
	protected $browser;
	/**
	 * @var Nette\Database\Context
	 */
	protected $database;
	/**
	 * @var Nette\Http\Request
	 */
	protected $request;

	protected $tables;

	public function  __construct(Nette\Http\Session $sessionHandler, \Browser $browser, Nette\Database\Context $database, Nette\Http\Request $request) {
		parent::__construct($sessionHandler);

		$this->sessionHandler = $sessionHandler;
		$this->browser = $browser;
		$this->database = $database;
		$this->request = $request;
	}

	public function setTables(array $tables) {
		$this->tables = $tables;
	}

	/**
	 * Sets the user identity.
	 * @param Nette\Security\IIdentity $identity
	 * @return UserStorage
	 */
	public function setIdentity(Nette\Security\IIdentity $identity = NULL) {
		if (!is_null($identity)) {

			$identity->browser = $this->browser->getBrowser();
			$identity->browserVersion = $this->browser->getVersion();

			$identity->hash = $this->createHash($identity->getId());
		}

		return parent::setIdentity($identity);
	}
	/**
	 * Returns and initializes $this->sessionSection.
	 * @return Nette\Http\SessionSection
	 */
	protected function getSessionSection($need) {
		$ret = parent::getSessionSection($need);

		if (!is_null($ret)) {
			if ($ret->authenticated && $ret->identity->browser !== $this->browser->getBrowser() && $ret->identity->browserVersion !== $this->browser->getVersion()) {
				$ret->authenticated = FALSE;
				$this->sessionHandler->regenerateId();
				$ret->reason = self::MANUAL;
				$ret->authTime = NULL;
			}
		}

		return $ret;
	}

	protected function getIdentityHashTable() {
		return $this->database->table($this->tables['identityHash']['table']);
	}
	protected function createHash($userId) {
		do {
			$hash = Nette\Utils\Random::generate(32);
		} while ($this->getIdentityHashTable()->where([$this->tables['identityHash']['hash'] => $hash])->fetch());

		$this->getIdentityHashTable()->insert([
			$this->tables['identityHash']['userId'] => $userId,
			$this->tables['identityHash']['hash']   => $hash,
			$this->tables['identityHash']['ip'] => $this->request->getRemoteAddress(),
		]);

		return $hash;
	}
	public function getAction() {
		if (is_null($identity = $this->getIdentity())) {
			throw new UserStorageException("Identity not exist", UserStorageException::IDENTITY_NOT_EXIST);
		}

		$hash = $identity->hash;

		$row = $this->getIdentityHashTable()->where([$this->tables['identityHash']['hash'] => $hash])->fetch();

		if ($row) {
			$row->update([
				$this->tables['identityHash']['ip'] => $this->request->getRemoteAddress(),
			]);

			return $row->{$this->tables['identityHash']['action']['name']};
		}

		return NULL;
	}
	public function setAction($action) {
		if (!in_array($action, $this->tables['identityHash']['action']['option'])) {
			throw new UserStorageException("Action '$action' is not enabled.", UserStorageException::ACTION_NOT_ENABLED);
		}

		if (is_null($identity = $this->getIdentity())) {
			throw new UserStorageException("Identity not exist", UserStorageException::IDENTITY_NOT_EXIST);
		}

		$hash = $identity->hash;

		$this->getIdentityHashTable()->where([$this->tables['identityHash']['hash'] => $hash])->update([
			$this->tables['identityHash']['action']['name'] => $action,
		]);
	}
}
