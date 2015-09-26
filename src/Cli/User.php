<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 6.12.14
 * Time: 2:25
 */

namespace Trejjam\Authorization\Cli;

use Symfony\Component\Console\Input\InputArgument,
	Symfony\Component\Console\Input\InputOption,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Nette;

class User extends Helper
{
	protected function configure() {
		$this->setName('Auth:user')
			 ->setDescription('Edit user')
			 ->addArgument(
				 'username',
				 InputArgument::REQUIRED,
				 'Enter username'
			 )->addArgument(
				'password',
				InputArgument::OPTIONAL,
				'Enter password'
			)->addOption(
				'create',
				'c',
				InputOption::VALUE_NONE,
				'Create new user',
				NULL
			)->addOption(
				'change-password',
				'p',
				InputOption::VALUE_NONE,
				'Change password',
				NULL
			)->addOption(
				'status',
				's',
				InputOption::VALUE_REQUIRED,
				'Set status',
				NULL
			)->addOption(
				'activated',
				'a',
				InputOption::VALUE_OPTIONAL,
				'Set activated',
				NULL
			)->addOption(
				'roles',
				'r',
				InputOption::VALUE_NONE,
				'Show user roles'
			)->addOption(
				'role_add',
				't',
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'Add role to user',
				[]
			)->addOption(
				'role_remove',
				'd',
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'Remove role from user',
				[]
			);
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$create = $input->getOption('create');
		$changePassword = $input->getOption('change-password');
		$status = $input->getOption('status');
		$activated = $input->getOption('activated');
		$role_add = $input->getOption('role_add');
		$role_remove = $input->getOption('role_remove');
		$roles = $input->getOption('roles');

		$username = $input->getArgument('username');
		$password = $input->getArgument('password');

		if ($create && !is_null($password)) {
			try {
				if (!$this->userManager->add($username, $password)) {
					throw new UserManagerException('User already exist');
				}
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}
		if ($changePassword && !is_null($password)) {
			try {
				$this->userManager->changePassword($username, $password);
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}

		if (!is_null($status)) {
			try {
				$this->userManager->setStatus($username, $status);

				$userId = $this->userManager->getUserId($username);
				$this->userManager->setUpdated($userId, "id");
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}
		if (!is_null($activated)) {
			try {
				$this->userManager->setActivated($username, $activated);

				$userId = $this->userManager->getUserId($username);
				$this->userManager->setUpdated($userId, "id");
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}

		if (!is_null($role_add)) {
			try {
				$userId = $this->userManager->getUserId($username);
				foreach ($role_add as $v) {
					$this->acl->addUserRole($userId, $v);
				}

				$this->userManager->setUpdated($userId, "id");
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}
		if (!is_null($role_remove)) {
			try {
				$userId = $this->userManager->getUserId($username);
				foreach ($role_remove as $v) {
					$this->acl->removeUserRole($userId, $v);
				}

				$this->userManager->setUpdated($userId, "id");
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}

		if ($roles) {
			try {
				$roles = $this->acl->getUserRoles($this->userManager->getUserId($username));

				$output->writeln("User roles:");
				$output->writeln($roles);
			}
			catch (UserManagerException $e) {
				$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
			}
		}
	}
}
