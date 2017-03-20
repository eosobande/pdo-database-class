pdodbDb -- Simple PDO wrapper with prepared statements

### Table of Contents
**[Initialization](#initialization)**  
**[Insert Query](#insert-query)**  
**[Update Query](#update-query)**  
**[Select Query](#select-query)**  
**[Delete Query](#delete-query)**  
**[Running raw SQL queries](#running-raw-sql-queries)**  
**[Where Conditions](#where--having-methods)**  
**[Joining Tables](#join-method)**  
**[Subqueries](#subqueries)**  
**[Prepared Queries](#has-method)**  



### Installation
To utilize this class, first import sql_builder.php into your project, and require it then import pdodb.php into your project, and require it.

```php
require_once ('sql_builder.php');
require_once ('pdodb.php');
```

### Initialization
Simple initialization: make sure the following constants are defined DB_SERVER,DB_NAME,DB_USER,DB_PASS, the initialization is automated with variable name $db
```php
$db = new PDOdb(DB_SERVER,DB_NAME,DB_USER,DB_PASS);
```

SQLITE initialization: provide the filepath to your sqlite db file as the first param only, the file does not have to exist to be passed
```php
$db = new PDOdb('mysqlitedb');
```

Also it is possible to reuse already connected pdodb object:
```php
$pdodb = new pdodb ('host', 'username', 'password', 'databaseName');
$db = new pdodbDb ($pdodb);
```

If you need to get already created pdodb object from another class or function use
```php
    Class myclass {
        use PDOdbRef;

        function __construct() {
            $this->__db_instance_ref(); // public attribute $this->db has be created
        }
    }
    ...
    function myfunc () {
        global $db;
        // global $db var imported to local
    }
```


### Insert Query
Simple example

$db->insert('users', [$username, $email], 'username,email');
if ($db->execute())
    echo 'user was created. Id=' . $db->last_insert_id();
else
    echo 'insert failed: ' . $db->error_info();
```

Insert with ignore
```php
    $values = [$username, $email];
    $cols = 'username,email';

    $db->insert('users', $values, $cols, true);
    $db->execute();
```

### Update Query
```php
$data = [   'username'=>'emmanuel',
            'email'=>'eosobande@gmail.com'  ];

$db->where(['id', 1]); OR $db->where('id=1');
$db->update ('users', $data);
if ($db->execute())
    echo $db->row_count() . ' records were updated';
else
    echo 'update failed: ' . $db->error_info();
```

`update()` also support limit parameter:
```php
$db->update('users', $data, 5);
// Gives: UPDATE users SET ... LIMIT 5
```

### Select Query
After any select function calls amount or returned rows is stored in $count variable
```php
$db->select('users'); 
$users = $db->fetch(); // contains an Array of all users
$users = $db->select('users', 10); //contains an Array 10 users
```

or select with custom columns set. Functions also could be used

```php
$cols = "id,name,email";
$db->select("users", $cols);
$users = $db->fetch();
```

or select just one row

```php
$db->where (["id", 1]);
$db->select('users');
$user = $db->fetch(PDOdb::_FETCH_ONE);
echo $user['id'];

or select one column value or function result

$db->select("users", "count(*)");
$count = $db->fetch(PDOdb::_FETCH_ONE_FIELD);
echo "{$count} users found";
```

or select one column with direct array acces:
```php
$db->select("users", "username");
$usernames = $db->fetch(PDOdb::_FETCH_FIRST_FROM_EACH_ROW);
foreach ($usernames as $username) {
    echo $username.'<br>';
}
```

or select with keywords:
```php

$keywords = [
    'limit'=>5,
    'order_by'=>'username',
    'offset'=>10,
    'group_by'=> '',
    'having'=> ''
]

$db->select("users", "login", $keywords);
$users = $db->fetch();
```

or select distinct
```php
$db->select("users", "login", null, true);
$users = $db->fetch();
```

### Running raw SQL queries
```php
$db->raw_query('SELECT * from users');
$users = $db->fetch();
```

### Where Methods

each $db->where() paramenter must be either an array or a string

Regular = operator with variables:
```php
$db->where (['id', 1]);
$db->select ('users');
// Gives: SELECT * FROM users WHERE id=1 AND login='admin';
```

```php
$db->where (['id', 1], ['username', 'emmanuel', '=']);
$results = $db->select ('users');
// Gives: SELECT * FROM users WHERE id=1 AND username='emmanuel';
```

```php
$db->where ('id=1', ['username', 'emmanuel', '=', 'or']);
$results = $db->select ('users');
// Gives: SELECT * FROM users WHERE id=1 OR username='emmanuel';
```

BETWEEN / NOT BETWEEN:
```php
$db->where (['id', [4, 20], 'between']);
$db->select('users');
$db->execute();
// Gives: SELECT * FROM users WHERE id BETWEEN 4 AND 20
```

IN / NOT IN:
```php
$db->where (['id', [4, 1, 2, 3, 6 20], 'in']);
$db->select('users');
$db->execute();

// Gives: SELECT * FROM users WHERE id IN (1, 5, 27, -1, 'd');
```

Also you can use raw where conditions:
```php
$db->where ("DATE(created) = DATE(lastLogin)");
$db->select("users");
$db->execute();
```

### Delete Query with limiter
```php
$db->where(['id', 1]);
$db->delete('users', 1);
if($db->execute()) echo 'successfully deleted';
```


Join table products with table users with LEFT JOIN by user_id
### JOIN method
```php
$tables = [
    
    ['products', 'as'='p'],
    ['users', 'as=>'u', 'join'=>'left', 'on'=>'p.user_id=u.user_id']

]

$db->select($tables);
$products = $db->fetch();
print_r ($products);
```


### Subqueries

```php
$sq = $db->sub_query();
$sq->select("users", 'user_id');

$db->where(['user_id', $sq, 'in']);
$db->select('products');
var_dump($db->fetch());
```

### Prepared queries with placeholders
```php

    $db->select('users');
    $users = $db->fetch();

    $db->where('user_id=?');
    $db->select('products', 'COUNT(*)');
    $db->prepare();

    foreach ($users as $key => $user) {
        $db->execute([$user['user_id']], TRUE);
        $users[$key]['product_count'] = $db->fetch(PDOdb::_FETCH_ONE, FALSE);
    }

    print_r($users);
```

