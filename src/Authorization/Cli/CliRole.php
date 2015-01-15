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

class CliRole extends CliHelper
{
	protected function configure() {
		$this->setName('Auth:role')
			 ->setDescription('Edit role')
			 ->addArgument(
				 'role',
				 InputArgument::OPTIONAL,
				 'Enter role',
				 NULL
			 )->addArgument(
				'parentRole',
				InputArgument::OPTIONAL,
				'Enter parent role'
			)->addArgument(
				'info',
				InputArgument::OPTIONAL,
				'Enter role info',
				""
			)->addOption(
				'role',
				'r',
				InputOption::VALUE_NONE,
				'View roles'
			)->addOption(
				'create',
				'c',
				InputOption::VALUE_NONE,
				'Create new role'
			)->addOption(
				'move',
				'm',
				InputOption::VALUE_NONE,
				'Connect role to parent'
			)->addOption(
				'delete',
				'd',
				InputOption::VALUE_NONE,
				'Remove role'
			)->addOption(
				'force_delete',
				'f',
				InputOption::VALUE_NONE,
				'Remove role and all child'
			);
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$viewRole = $input->getOption('role');
		$create = $input->getOption('create');
		$move = $input->getOption('move');
		$delete = $input->getOption('delete');
		$forceDelete = $input->getOption('force_delete');

		$role = $input->getArgument('role');
		$parentRole = $input->getArgument('parentRole');
		$info = $input->getArgument('info');

		if ($create) {
			if (!is_null($role)) {
				try {
					$this->acl->createRole($role, $parentRole, $info);
				}
				catch (RoleException $e) {
					$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
				}
			}
			else {
				$output->writeln("<error>Argument role required</error>");
			}
		}
		if ($move) {
			if (!is_null($role)) {
				try {
					$this->acl->moveRole($role, $parentRole);
				}
				catch (RoleException $e) {
					$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
				}
			}
			else {
				$output->writeln("<error>Argument role required</error>");
			}
		}
		if ($delete) {
			if (!is_null($role)) {
				try {
					$this->acl->deleteRole($role, $forceDelete);
				}
				catch (RoleException $e) {
					$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
				}
			}
			else {
				$output->writeln("<error>Argument role required</error>");
			}
		}

		if ($viewRole) {
			$output->writeln("Roles:");

			foreach ($this->acl->getTrees()->getRootRoles() as $v) {
				$this->drawRole($output, $v);
			}
		}
	}

	protected function drawRole(OutputInterface $output, Acl\Role $role, $depth = 0) {
		$output->writeln(Nette\Utils\Strings::padLeft('', $depth, ' ') . '\_ ' . $role->getName());

		$resource = [];
		foreach ($role->getResource() as $v) {
			$resource[] = $v->getNameRaw() . ":" . $v->getActionRaw();
		}

		if (count($resource) > 0) {
			$output->writeln(Nette\Utils\Strings::padLeft('', $depth + 2, ' ') . "-" . implode(", ", $resource));
		}

		foreach ($role->getChild() as $v) {
			$this->drawRole($output, $v, $depth + 1);
		}
	}
}
