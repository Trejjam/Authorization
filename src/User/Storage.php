<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 11.1.15
 * Time: 16:25
 */

namespace Trejjam\Authorization\User;


use Nette,
	Trejjam;

class Storage extends Nette\Http\UserStorage
{
	/**
	 * @var Nette\Http\Session
	 */
	protected $sessionHandler;
	/**
	 * @var IdentityHash
	 */
	protected $identityHash;
	/**
	 * @var Nette\Http\Request
	 */
	protected $request;

	protected $tables;

	public function  __construct(Nette\Http\Session $sessionHandler, IdentityHash $identityHash, Nette\Http\Request $request) {
		parent::__construct($sessionHandler);

		$this->sessionHandler = $sessionHandler;
		//$this->browser = $browser;
		$this->identityHash = $identityHash;
		$this->request = $request;
	}

	/**
	 * Sets the user identity.
	 * @param Nette\Security\IIdentity $identity
	 * @return Storage
	 */
	public function setIdentity(Nette\Security\IIdentity $identity = NULL) {
		if (!is_null($identity)) {

			//$identity->browser = $this->browser->getBrowser();
			//$identity->browserVersion = $this->browser->getVersion();

			$identity->hash = $this->identityHash->createIdentityHash($identity->getId(), $this->request->getRemoteAddress());
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
			/*if ($ret->authenticated && $ret->identity->browser !== $this->browser->getBrowser() && $ret->identity->browserVersion !== $this->browser->getVersion()) {
				$ret->authenticated = FALSE;
				$this->sessionHandler->regenerateId();
				$ret->reason = static::MANUAL;
				$ret->authTime = NULL;
			}*/
		}

		return $ret;
	}
}
