<?php
/**
 * Created by PhpStorm.
 * User: Jan
 * Date: 26. 10. 2014
 * Time: 17:38
 */

namespace Trejjam\Authorization\DI;

use Nette,
	Trejjam;

class AuthorizationExtension extends Trejjam\BaseExtension\DI\BaseExtension
{
	protected $classesDefinition = [
		'user.authenticator'      => 'Trejjam\Authorization\User\Authenticator',
		'user.manager'      => 'Trejjam\Authorization\User\Manager',
		'user.identityHash' => 'Trejjam\Authorization\User\IdentityHash',
		'acl.acl'           => 'Trejjam\Authorization\Acl\Acl',
		'user.request'      => 'Trejjam\Authorization\User\Request',
		'cache'             => 'Nette\Caching\Cache',
	];

	protected $default = [
		'tables'            => [
			'users'        => [
				'table'     => 'users__users',
				'status'    => [
					'accept'  => 'enable',
					'options' => [
						'enable',
						'disable',
					],
				],
				'activated' => [
					'accept'  => 'yes',
					'options' => [
						'yes',
						'no',
					],
				],
				'username'  => [
					'match'  => '/^[a-zA-Z_]+$/', //email is special value (validate by Validators:isEmail)
					'length' => '60'
				],

				'items'     => [
					'id',
					'status',
					'activated',
					'username',
					'password',
					'dateCreated' => 'date_created',
				]
			],
			'roles'        => [
				'table'    => 'users__roles',
				'id'       => 'id',
				'parentId' => 'parent_id',
				'roleName' => 'name',
				'info'     => 'info', //value FALSE disable usage
			],
			'userRoles'    => [
				'table'  => 'users__user_role',
				'id'     => 'id',
				'userId' => 'user_id',
				'roleId' => 'role_id',
			],
			'resource'     => [
				'table'          => 'users__resources',
				'id'             => 'id',
				'roleId'         => 'role_id',
				'resourceName'   => 'name',
				'resourceAction' => 'action', //default ALL
			],
			'userRequest'  => [
				'table'   => 'users__user_request',
				'id'      => 'id',
				'userId'  => 'user_id',
				'hash'    => [
					'name'   => 'hash',
					'length' => 10,
				],
				'type'    => [
					'name'   => 'type',
					'option' => [
						'activate',
						'lostPassword',
					]
				],
				'used'    => [
					'name'     => 'used',
					'positive' => 'yes',
				],
				'timeout' => [
					'name'    => 'timeout',
					'default' => '1 HOUR',
				],
			],
			'identityHash' => [
				'table'  => 'users__identity_hash',
				'id'     => 'id',
				'userId' => 'user_id',
				'hash'   => 'hash',
				'ip'     => 'ip',
				'action' => 'action',
			],
		],
		'reloadChangedUser' => TRUE, //for example admin edit user role, then users role will be changed (in user session)
		'cache'             => [
			"use"     => TRUE,
			"name"    => "authorization",
			"timeout" => "10 minutes"
		],
	];

	public function loadConfiguration() {
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();

		$builder->getDefinition('nette.userStorage')
				->setClass('Trejjam\Authorization\User\Storage')
				->setFactory('Trejjam\Authorization\User\Storage');

		$builder->getDefinition('security.user')
				->setClass('Trejjam\Authorization\User\User')
				->setFactory('Trejjam\Authorization\User\User');

		if (class_exists('\Symfony\Component\Console\Command\Command')) {
			$command = [
				'cliUser'     => 'User',
				'cliRole'     => 'Role',
				'cliResource' => 'Resource',
				'cliInstall'  => 'Install',
			];

			foreach ($command as $k => $v) {
				$builder->addDefinition($this->prefix($k))
						->setClass('Trejjam\Authorization\Cli\\' . $v)
						->addTag(\Kdyby\Console\DI\ConsoleExtension::COMMAND_TAG);
			}
		}
	}

	public function beforeCompile() {
		$builder = $this->getContainerBuilder();
		$config = $this->createConfig();
		$classes = $this->getClasses();

		$builder->getDefinition('security.user')->addSetup('setParams', [
			'reloadChangedUser' => $config['reloadChangedUser'],
		]);
		$classes['acl.acl']
			->addSetup('setTables', [
				'tables' => $config['tables'],
			])
			->addSetup("init");;
		$classes['user.request']->addSetup('setTables', [
			'tables' => $config['tables'],
		]);
		$classes['user.manager']->addSetup('setTables', [
			'tables' => $config['tables'],
		]);
		$classes['user.identityHash']->addSetup('setTables', [
			'tables' => $config['tables'],
		]);

		if ($config['cache']['use']) {
			$classes['cache']
				->setFactory('Nette\Caching\Cache')
				->setArguments(['@cacheStorage', $config['cache']['name']])
				->setAutowired(FALSE);

			$classes['acl.acl']->setArguments([$this->prefix('@cache')])
							   ->addSetup('setCacheParams', ['cacheParams' => [
								   Nette\Caching\Cache::EXPIRE => $config['cache']['timeout']
							   ]]);
		}
	}
}
