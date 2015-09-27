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

class AuthenticatorException extends Nette\Security\AuthenticationException implements ILogicException
{
	const
			INVALID_CREDENTIAL = 1,
			NOT_DEFINED_PASSWORD = 2;
}

class ResourceException extends LogicException
{
	const
		NOT_EXIST = 1,
		ALREADY_EXIST = 2;
}

class UserManagerException extends LogicException
{
	const
		NOT_EXIST_USERNAME = 1,
		INVALID_CREDENTIAL = 2,
		NOT_ENABLE = 4,
		NOT_ACTIVATED = 8,
		LONG_USERNAME = 16,
		NOT_VALID_USERNAME = 32,
		UNRECOGNIZED_TYPE = 64,
			ACTION_NOT_ENABLED = 128,
			UNKNOWN_VALUE = 256,
			ID_NOT_FOUND = 512;
}

class UserConfigurationException extends LogicException
{
	const
		INFO_IS_DISABLED = 1;
}

class UserRequestException extends LogicException
{
	const
		PERMISSION_DENIED = 1,
		CORRUPTED_HASH = 2,
		USED_HASH = 4,
		HASH_TIMEOUT = 8;
}

class UserStorageException extends LogicException
{
	const
		IDENTITY_NOT_EXIST = 1,
		ACTION_NOT_ENABLED = 2;
}
