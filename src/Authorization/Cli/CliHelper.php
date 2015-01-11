<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 1:45
 */

namespace Trejjam\Authorization;

use Symfony\Component\Console\Command\Command;

abstract class CliHelper extends Command
{
	/**
	 * @var UserManager
	 */
	protected $userManager;
	/**
	 * @var Acl
	 */
	protected $acl;

	public function __construct(UserManager $userManager, Acl $acl) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->acl = $acl;
	}
}