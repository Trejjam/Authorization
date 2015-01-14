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

class TableNotFoundException extends \Exception
{

}

class RoleException extends \LogicException
{

}

class ResourceException extends \LogicException
{

}

class UserManagerException extends \LogicException
{

}

class UserConfigurationException extends \LogicException
{

}

class UserRequestException extends \LogicException
{

}

class UserStorageException extends \LogicException
{

}
