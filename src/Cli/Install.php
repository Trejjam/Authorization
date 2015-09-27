<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 2:25
 */

namespace Trejjam\Authorization\Cli;

use Symfony\Component\Console\Command\Command,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Nette,
	Trejjam;

class Install extends Command
{
	const
		FILE_USERS_TABLE = "users__users",
		FILE_ROLES_TABLE = "users__roles",
		FILE_RESOURCES_TABLE = "users__resources",
		FILE_USER_ROLE_TABLE = "users__user_role",
		FILE_USER_REQUEST_TABLE = "users__user_request",
		FILE_ROLES_DATA_TABLE = "users__roles-data",
		FILE_IDENTITY_HASH_TABLE = "users__identity_hash";


	/**
	 * @var \Nette\Database\Context @inject
	 */
	public $database;

	protected function configure() {
		$this->setName('Auth:install')
			 ->setDescription('Install default tables');
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$connection = $this->database->getConnection();

		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_USERS_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_ROLES_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_RESOURCES_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_USER_ROLE_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_USER_REQUEST_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_ROLES_DATA_TABLE));
		Nette\Database\Helpers::loadFromFile($connection, $this->getFile(static::FILE_IDENTITY_HASH_TABLE));
	}
	protected function getFile($file) {
		return __DIR__ . "/../../../sql/" . $file . ".sql";
	}
}
