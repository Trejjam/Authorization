Authorization
=============

Library for 
<ul>
<li>authorization</li>
<li>roles</li>
<li>resource</li>
</ul>
in [Nette](http://nette.org) using database

Installation
------------

The best way to install Trejjam/Authorization is using  [Composer](http://getcomposer.org/):

```sh
$ composer require trejjam/authorization:v0.10
```

Configuration
-------------

.neon
```yml
extensions:
	authorization: Trejjam\Authorization\DI\AuthorizationExtension

authorization:
	tables:
		users:
			table	 : users__users
			id	    : id #column name
			status    : 
				accept : enable 
				options:
					enable
					disable
			activated : 
				accept: yes    
				options:
					yes
					no
			username  : 
				match  : '/^[a-zA-Z_]+$/' #email is special value (validate by Nette\Utils\Validators:isEmail)
				length : 60
			items:
				- id
				- status
				- activated
				- username
				- password
				dateCreated: date_created
		roles:
			table    : users__roles
			id       : id #column name
			parentId : parent_id #column name, foreign key to role.id
			roleName : name #column name
			info     : info #column name, value FALSE disable usage        
		userRoles:
			table  : users__user_role
			id     : id #column name
			userId : user_id #column name, foreign key to users.id
			roleId : role_id #column name, foreign key to roles.id
		resource : 
			table          : users__resources
			id             : id #column name
			roleId         : role_id #column name, foreign key to role.id
			resourceName   : name #column name
			resourceAction : action #column name, default ALL
	reloadChangedUser: true
	cache : 
		use     : true
		name    : authorization
		timeout : 10 minutes    
	debugger:false #not implemented yet
	
services:
	- Browser
```
Config
------

The best way for configuration is using [Kdyby/Console](https://github.com/kdyby/console)

```sh
$ composer require kdyby/console
```

Read how to install [Kdyby/Console](https://github.com/Kdyby/Console/blob/master/docs/en/index.md)

```sh
php index.php
```

After successful installation display:

```
Available commands:
Auth
	Auth:install    Install default tables
	Auth:resource   Edit resource
	Auth:role       Edit role
	Auth:user       Edit user
	help            Displays help for a command
	list            Lists commands
```

Config database
---------------

Create default tables:
```sh
php index.php Auth:install
```

Config role
-----------

Add role:
```sh
php index.php Auth:role -c [-r] roleName [parentRole [info]]
```

Move role to other parent:
```sh
php index.php Auth:role -m [-r] roleName [parentRole]
```

Delete role:
options -f delete all child roles and their resource
```sh
php index.php Auth:role -d [-f] roleName
```

List all role:
```sh
php index.php Auth:role -r
```

Config resource
---------------

Add resource:
```sh
php index.php Auth:resource -c [-r] resourceName[:resourceAction] parentRole
```

Move resource to other role:
```sh
php index.php Auth:resource -m [-r] resourceName[:resourceAction] parentRole
```

Delete resource:
```sh
php index.php Auth:resource -d resourceName[:resourceAction]
```

List all resource:
```sh
php index.php Auth:resource -r
```

Config user
-----------

Add user:
```sh
php index.php Auth:user -c username password
```

Change password:
```sh
php index.php Auth:user -p username password
```

Set user status:
```sh
php index.php Auth:user -s status username
```
default status values [enable|disable]

Set user activated:
```sh
php index.php Auth:user -a activated username
```
default activated values [yes|no]

Show user roles:
```sh
php index.php Auth:user -r username
```

Add user role:
```sh
php index.php Auth:user [-r] -t roleName username
```

Remove user role:
```sh
php index.php Auth:user [-r] -d roleName username
```

Usage
-----

Presenter:

```php
	/**
	* @var \Trejjam\Authorization\Acl @inject
	*/
	public $acl;
	/**
	* @var \Trejjam\Authorization\UserManager @inject
	*/
	public $userManager;
	/**
	* @var \Trejjam\Authorization\UserRequest @inject
	*/
	public $userRequest;
	
	function renderDefault() {
		dump($this->acl->getTrees()->getRootRoles()); //get all roles without parent
		dump($this->acl->getTrees()->getRoles()); //get all roles
		dump($this->acl->getTrees()->getResources()); //get all resource
		
		$this->acl->createRole($name, $parent, $info);
		$this->acl->deleteRole($name);
		$this->acl->moveRole($name, $parent);
		
		dump($this->acl->getRoleByName($roleName)); //return AclRole with "name"
	
		$this->acl->createResource($name, $action, $roleName);
        $this->acl->deleteResource($name, $action);
        $this->acl->moveResource($name, $action, $roleName);
        
        dump($this->acl->getResourceById($id)); //return AclResource
        
        dump($this->acl->getUserRoles($userId)); //return AclRole[] 
        $this->acl->addUserRole($userId, $roleName);
        $this->acl->removeUserRole($userId, $roleName);
        
        //--------------userManager--------------
        
        $this->userManager->add($username, $password);
        $this->userManager->changePassword($username, $password, $type = "username"); //$type could be username|id
        $this->userManager->setUpdated($username, $type = "username"); //next user request user session will be reload (if "reloadChangedUser: true")
        $this->userManager->setStatus($username, $status, $type = "username"); //$status could be enable|disable - if user with disable status try login, login function return exception
        $this->userManager->setActivated($username, $activated = NULL, $type = "username"); //$activated could be yes|no - if user with 'no' activated try login, login function return exception
        dump($this->userManager->getUserId($username)); //return id of user
        $this->userManager->getUserInfo($username, $type = "auto"); //return all information about user except password
        $this->userManager->getUsersList(); //return getUserInfo[] for all users
        
        //--------------userRequest--------------
        
        dump($this->userRequest->generateHash($userId, $type)); //return hash for public usage, $type could be activate|lostPassword 
        dump($this->userRequest->getType($userId, $hash, $invalidateHash = FALSE)); //return TRUE - hash was used|$type|FALSE - user hasn't this hash, $invalidateHash=TRUE - disable future hash usage
	}
```
