Authorization
=============

Library for 
<ul>
<li>authorization</li>
<li>roles</li>
<li>resource</li>
</ul>
in [Nette](http://nette.org) over database

Installation
------------

The best way to install Trejjam/Authorization is using  [Composer](http://getcomposer.org/):

```sh
$ composer require trejjam/authorization:dev-master
```

Configuration
-------------

.neon
```yml
extensions:
	authorization: Trejjam\DI\AuthorizationExtension

authorization:
	tables:
		users:
			table	 : users__users
			id	    : id #column name
			status    : 
				name   : status #column name
				accept : enable            
			activated : 
				name : activated #column name
				yes  : yes            
			username  : 
				name   : username #column name
				match  : '/^[a-zA-Z_]+$/' #email is special value (validate by Nette\Utils\Validators:isEmail)
				length : 60            
			password  : password #column name
			timestamp : 
				created   : date_created #column name
				edited    : date_edited #column name
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
	cache : 
		use     : false
		name    : authorization
		timeout : 10 minutes    
	debugger:false #not implemented yet
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
	public $authorization;
	/**
	* @var \Trejjam\Authorization\UserManager @inject
	*/
	public $userManager;
	
	function renderDefault() {
		
		
	}
```