<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 1:45
 */

namespace Trejjam\Authorization\Cli;

use Symfony\Component\Console\Command\Command,
	Trejjam,
	Nette;

abstract class Helper extends Command
{
	/**
	 * @var Trejjam\Authorization\User\Manager
	 */
	protected $userManager;
	/**
	 * @var Trejjam\Authorization\Acl\Acl
	 */
	protected $acl;

	public function __construct(Trejjam\Authorization\User\Manager $userManager, Trejjam\Authorization\Acl\Acl $acl) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->acl = $acl;
	}
}
