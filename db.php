<?

if (!mysql_connect('localhost', 'username', 'password')) die("Can't connect to database");
if (!mysql_select_db('database')) die("Can't select database");
mysql_set_charset('utf8');
mysql_query("SET lc_time_names = 'nl_NL'");
