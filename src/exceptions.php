<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 15.1.15
 * Time: 0:06
 */

namespace Trejjam\Authorization;

use Nette,
	Trejjam;

interface IException
{
}

class Exception extends \Exception implements IException
{
}

interface ILogicException
{
}

class LogicException extends \LogicException implements ILogicException
{
}

class TableNotFoundException extends Exception
{
	const
		TABLE_NOT_FOUND = 1;
}

class RoleException extends LogicException
{
	const
		NOT_EXIST = 1,
		ALREADY_EXIST = 2,
		CREATE_CIRCLE = 4,
		ALREADY_IN_ROLE = 8;
}

class ResourceException extends LogicException
{
	const
		NOT_EXIST = 1,
		ALREADY_EXIST = 2;
}

class ConfigurationException extends LogicException
{
	const
		INFO_IS_DISABLED = 1;
}

namespace Trejjam\Authorization\User;

use Nette,
	Trejjam;

interface ILogicException extends Trejjam\Authorization\ILogicException
{
}

class LogicException extends Trejjam\Authorization\LogicException implements ILogicException
{
}

class ManagerException extends LogicException
{
	const
		NOT_EXIST_USERNAME = 1,
		ID_NOT_FOUND = 2,
		NOT_ENABLE = 4,
		NOT_ACTIVATED = 8,
		LONG_USERNAME = 16,
		NOT_VALID_USERNAME = 32,
		UNRECOGNIZED_TYPE = 64,
		UNKNOWN_VALUE = 128;
}

class_alias('Trejjam\Authorization\User\ManagerException', 'Trejjam\Authorization\UserManagerException');

class IdentityHashException extends LogicException
{
	const
		ACTION_NOT_ENABLED = 1;
}

class_alias('Trejjam\Authorization\User\IdentityHashException', 'Trejjam\Authorization\IdentityHashException');

class AuthenticatorException extends Nette\Security\AuthenticationException implements ILogicException
{
	const
		INVALID_CREDENTIAL = 1,
		NOT_DEFINED_PASSWORD = 2;
}

class_alias('Trejjam\Authorization\User\AuthenticatorException', 'Trejjam\Authorization\AuthenticatorException');

class RequestException extends LogicException
{
	const
		PERMISSION_DENIED = 0b00001,
		CORRUPTED_HASH = 0b00010,
		USED_HASH = 0b00100,
		HASH_TIMEOUT = 0b01000,
		INVALID_INPUT = 0b10000;
}

class_alias('Trejjam\Authorization\User\RequestException', 'Trejjam\Authorization\UserRequestException');

class StorageException extends LogicException
{
}

class_alias('Trejjam\Authorization\User\StorageException', 'Trejjam\Authorization\UserStorageException');
