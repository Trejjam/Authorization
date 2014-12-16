<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 2:25
 */

namespace Trejjam\Authorization;

use Symfony\Component\Console\Command\Command,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Nette;

class CliInstall extends Command
{
	const
		FILE_USERS_TABLE = "users__users",
		FILE_ROLES_TABLE = "users__roles",
		FILE_RESOURCES_TABLE = "users__resources",
		FILE_USER_ROLE_TABLE = "users__user_role",
		FILE_ROLES_DATA_TABLE = "users__roles-data";

	/**
	 * @var \Nette\Database\Context @inject
	 */
	public $database;

	protected function configure() {
		$this->setName('Auth:install')
			 ->setDescription('Install default tables');
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->database->query($this->getFile(self::FILE_USERS_TABLE));
		$this->database->query($this->getFile(self::FILE_ROLES_TABLE));
		$this->database->query($this->getFile(self::FILE_RESOURCES_TABLE));
		$this->database->query($this->getFile(self::FILE_USER_ROLE_TABLE));
		$this->database->query($this->getFile(self::FILE_ROLES_DATA_TABLE));
	}
	protected function getFile($file) {
		return file_get_contents(__DIR__."/../../sql/". $file.".sql");
	}
}