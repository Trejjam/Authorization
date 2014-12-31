<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 26. 10. 2014
 * Time: 17:38
 */

namespace Trejjam\DI;

use Nette;

class AuthorizationExtension extends Nette\DI\CompilerExtension
{
	private $defaults = [
		'tables'            => [
			'users'       => [
				'table'     => 'users__users',
				'id'        => 'id',
				'status'    => [
					'name'   => 'status',
					'accept' => 'enable',
				],
				'activated' => [
					'name' => 'activated',
					'yes'  => 'yes',
				],
				'username'  => [
					'name'   => 'username',
					'match'  => '/^[a-zA-Z_]+$/', //email is special value (validate by Validators:isEmail)
					'length' => '60'
				],
				'password'  => 'password',
				'timestamp' => [
					'created' => 'date_created',
					'edited'  => 'date_edited',
				]
			],
			'roles'       => [
				'table'    => 'users__roles',
				'id'       => 'id',
				'parentId' => 'parent_id',
				'roleName' => 'name',
				'info'     => 'info', //value FALSE disable usage
			],
			'userRoles'   => [
				'table'  => 'users__user_role',
				'id'     => 'id',
				'userId' => 'user_id',
				'roleId' => 'role_id',
			],
			'resource'    => [
				'table'          => 'users__resources',
				'id'             => 'id',
				'roleId'         => 'role_id',
				'resourceName'   => 'name',
				'resourceAction' => 'action', //default ALL
			],
			'userRequest' => [
				'table'  => 'users__user_request',
				'id'     => 'id',
				'userId' => 'user_id',
				'hash'   => [
					'name'   => 'hash',
					'length' => 10,
				],
				'type'   => [
					'name'   => 'type',
					'option' => [
						'activate',
						'lostPassword',
					]
				]
			]
		],
		'reloadChangedUser' => TRUE, //not implemented yet, for example admin edit user role, then users role will be changed
		'cache'             => [
			"use"     => FALSE,
			"name"    => "authorization",
			"timeout" => "10 minutes"
		],
		'debugger'          => FALSE, //not implemented yet
	];

	public function loadConfiguration() {
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$accessControlList = $builder->addDefinition($this->prefix('acl'))
									 ->setClass('Trejjam\Authorization\Acl')
									 ->addSetup("setTables", [
										 "tables" => $config["tables"],
									 ])->addSetup("init");

		$userRequest = $builder->addDefinition($this->prefix('userRequest'))
							   ->setClass('Trejjam\Authorization\UserRequest')
							   ->addSetup("setTables", [
								   "tables" => $config["tables"],
							   ]);

		$userManager = $builder->addDefinition($this->prefix('userManager'))
							   ->setClass('Trejjam\Authorization\UserManager')
							   ->addSetup("setTables", [
								   "tables" => $config["tables"],
							   ])->addSetup("setParams", [
				"reloadChangedUser" => $config["reloadChangedUser"],
			]);

		if (class_exists('\Symfony\Component\Console\Command\Command')) {
			$command = [
				"cliUser"     => "CliUser",
				"cliRole"     => "CliRole",
				"cliResource" => "CliResource",
				"cliInstall"  => "CliInstall",
			];

			foreach ($command as $k => $v) {
				$builder->addDefinition($this->prefix($k))
						->setClass('Trejjam\Authorization\\' . $v)
						->addTag("kdyby.console.command");
			}
		}

		if ($config["cache"]["use"]) {
			$builder->addDefinition($this->prefix("cache"))
					->setFactory('Nette\Caching\Cache')
					->setArguments(['@cacheStorage', $config["cache"]["name"]])
					->setAutowired(FALSE);

			$accessControlList->setArguments([$this->prefix("@cache")])
							  ->addSetup("setCacheParams", ["cacheParams" => [
								  Nette\Caching\Cache::EXPIRE => $config["cache"]["timeout"]
							  ]]);
		}

		if ($config["debugger"]) {
			$builder->addDefinition($this->prefix("panel"))
					->setClass('Trejjam\Authorization')
					->setAutowired(FALSE);

			$accessControlList->addSetup('injectPanel', array($this->prefix("@panel")));
		}
	}
}