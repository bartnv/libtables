libtables
=========

**Please note: this is libtables version 1 and no longer being maintained -- new projects are strongly encouraged to start using [libtables 3](https://github.com/bartnv/libtables3/)**

A PHP-toolkit for web-based database applications

This toolkit is intended to help developers who have a firm grasp of SQL to quickly develop web-applications by getting rid of writing the tedious HTML FORM and TABLE code. Instead, PHP functions are called with the required queries, their output modified by declarations in the form of PHP arrays. The main functions are lt_input(), which generates a form to INSERT one or more rows into the database; and lt_display(), which generates a table to show the data returned from a SELECT query. The idea is that the programmer produces an index.php which uses the toolkit functions along with some basic HTML/PHP to create the application. The most basic form of such an index.php is shown here:

```
<?php

include('db.php');
include('libtables.php');

?><!DOCTYPE html>
<html>
<head>
<title>libtables test page</title>
<meta charset="utf-8">
<link rel="stylesheet" type="text/css" href="style.css">
<? lt_script(); ?>
</head>
<body>
lt_input('Add person to friends',
  array(
    array('name' => 'Name', 'target' => 'friends.name'),
    array('name' => 'Nick', 'target' => 'friends.nick')
  )
);
lt_display('My friends', "SELECT * FROM friends");
</body>
</html>
```

This example assumes you've used db.php to set up a connection to a database which has a table 'friends' with the columns 'name' and 'nick' in it. You can add rows to the table now and see the result displayed.
