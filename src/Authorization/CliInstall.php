<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 2:25
 */

namespace Trejjam\Authorization;

use Symfony\Component\Console\Input\InputArgument,
	Symfony\Component\Console\Input\InputOption,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Nette;

class CliInstall extends CliHelper
{
	const
		FILE_USERS_TABLE = "users__users",
		FILE_ROLES_TABLE = "users__roles",
		FILE_RESOURCES_TABLE = "users__resources",
		FILE_USER_ROLE_TABLE = "users__user_role";

	/**
	 * @var \Nette\Database\Context @inject
	 */
	public $database;

	protected function configure() {
		$this->setName('Auth:install')
			 ->setDescription('Install default tables')
			 ->addOption(
				 'users',
				 'u',
				 InputOption::VALUE_NONE,
				 'Create table for users',
				 FALSE
			 )->addOption(
				'roles',
				'r',
				InputOption::VALUE_NONE,
				'Create table for roles',
				FALSE
			)->addOption(
				'resource',
				'e',
				InputOption::VALUE_NONE,
				'Create table for resource',
				FALSE
			)->addOption(
				'user-roles',
				'm',
				InputOption::VALUE_NONE,
				'Create table for users roles m2n',
				FALSE
			);
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$users = $input->getOption('users');
		$roles = $input->getOption('roles');
		$resource = $input->getOption('resource');
		$userRole = $input->getOption('user-roles');

		if ($users || $roles || $resource || $userRole) {
			if ($users) {
				$this->database->query($this->getFile(self::FILE_USERS_TABLE));
			}
			if ($roles) {
				$this->database->query($this->getFile(self::FILE_ROLES_TABLE));
			}
			if ($resource) {
				$this->database->query($this->getFile(self::FILE_RESOURCES_TABLE));
			}
			if ($userRole) {
				$this->database->query($this->getFile(self::FILE_USER_ROLE_TABLE));
			}
		}
	}
	protected function getFile($file) {
		return file_get_contents(__DIR__."/../../sql/". $file);
	}
}