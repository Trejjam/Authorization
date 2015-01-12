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
	Symfony\Component\Console\Input\ArrayInput,
	Nette;

class CliResource extends CliHelper
{
	protected function configure() {
		$this->setName('Auth:resource')
			 ->setDescription('Edit resource')
			 ->addArgument(
				 'resource',
				 InputArgument::OPTIONAL,
				 'Enter role',
				 NULL
			 )->addArgument(
				'parentRole',
				InputArgument::OPTIONAL,
				'Enter parent role'
			)->addOption(
				'resource',
				'r',
				InputOption::VALUE_NONE,
				'View resource'
			)->addOption(
				'create',
				'c',
				InputOption::VALUE_NONE,
				'Create new resource'
			)->addOption(
				'move',
				'm',
				InputOption::VALUE_NONE,
				'Connect resource to role'
			)->addOption(
				'delete',
				'd',
				InputOption::VALUE_NONE,
				'Remove resource'
			);
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$viewResource = $input->getOption('resource');
		$create = $input->getOption('create');
		$move = $input->getOption('move');
		$delete = $input->getOption('delete');

		$resource = $input->getArgument('resource');
		$parentRole = $input->getArgument('parentRole');

		if ($viewResource) {
			$output->writeln("Resource:");

			foreach ($this->acl->getTrees()->getResources() as $v) {
				$output->writeln($v->getNameRaw() . ":" . $v->getActionRaw());
			}
		}

		if ($create) {
			if (!is_null($resource)) {
				if (!is_null($parentRole)) {
					try {
						$rArr = explode(":", $resource);
						$rName = $rArr[0];
						$rAction = isset($rArr[1]) ? $rArr[1] : NULL;

						$this->acl->createResource($rName, $rAction, $parentRole);
					}
					catch (\Exception $e) {
						$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
					}
				}
				else {
					$output->writeln("<error>Argument role required</error>");
				}
			}
			else {
				$output->writeln("<error>Argument resource required</error>");
			}
		}
		if ($move) {
			if (!is_null($resource)) {
				if (!is_null($parentRole)) {
					try {
						$rArr = explode(":", $resource);
						$rName = $rArr[0];
						$rAction = isset($rArr[1]) ? $rArr[1] : NULL;

						$this->acl->moveResource($rName, $rAction, $parentRole);
					}
					catch (\Exception $e) {
						$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
					}
				}
				else {
					$output->writeln("<error>Argument role required</error>");
				}
			}
			else {
				$output->writeln("<error>Argument resource required</error>");
			}
		}
		if ($delete) {
			if (!is_null($resource)) {
				try {
					$rArr = explode(":", $resource);
					$rName = $rArr[0];
					$rAction = isset($rArr[1]) ? $rArr[1] : NULL;

					$this->acl->deleteResource($rName, $rAction);
				}
				catch (\Exception $e) {
					$output->writeln("<error>Error: " . $e->getMessage() . ", code: " . $e->getCode() . "</error>");
				}
			}
			else {
				$output->writeln("<error>Argument resource required</error>");
			}
		}

		if ($viewResource) {
			$output->writeln("");
			$arguments = array(
				'-r' => TRUE
			);
			$roleInput = new ArrayInput($arguments);
			$role = new CliRole($this->userManager, $this->acl);

			$role->run($roleInput, $output);
		}
	}
}
