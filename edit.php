<?
/* libtables: a PHP-toolkit for web-based database applications
 * Copyright (C) 2013  Bart Noordervliet <bart@mmvi.nl>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

include('db.php');
include('libtables.php');

function error($txt) {
  header('Content-type: text/plain');
  print $txt;
  exit;
}
function warning($txt) {
  header('Content-type: text/plain');
  print $txt;
}

if (!empty($_POST['mode'])) $mode = $_POST['mode'];
elseif (!empty($_GET['mode'])) $mode = $_GET['mode'];
else error('No processing-mode specified');

switch ($mode) {
  case 'inlineedit':
    if (empty($_POST['target'])) error("No target specified");
    if (empty($_POST['id'])) error("No id specified");
    if (!is_numeric($_POST['id'])) error("Invalid id specified");
    if (!isset($_POST['value']) && empty($_POST['delete'])) error("No value or delete command specified");
    if (isset($_POST['delete']) && ($_POST['delete'] !== "true")) error("Invalid delete command specified");

    if (isset($_POST['value'])) {
      list($table, $column) = explode(".", $_POST['target']);
      if (!$column) error("No valid target specified");
    }
    else $table = $_POST['target'];

    if (isset($_POST['delete'])) {
      if (!($res = mysql_query("DELETE FROM $table WHERE id = {$_POST['id']}"))) error("MySQL-error: " . mysql_error());
    }
    else {
      if ($_POST['value'] == "") {
        if (!($res = mysql_query("UPDATE $table SET $column = NULL WHERE id = {$_POST['id']}"))) error("MySQL-error: " . mysql_error());
      }
      else {
        if (!($res = mysql_query("UPDATE $table SET $column = '{$_POST['value']}' WHERE id = {$_POST['id']}"))) error("MySQL-error: " . mysql_error());
      }
    }
  break;
  case 'selectbox':
    if (!isset($_GET['hash'])) error("No query-hash specified");

    if (empty($_GET['target'])) error("No target specified");
    list($table, $column) = split(':', $_GET['target']);
    if (empty($table) || empty($column)) error("No valid target specified");
    if (!($res = mysql_query("DESC $table $column"))) error("MySQL-error: " . mysql_error());
    if (mysql_num_rows($res) != 1) error('editselect target query returned invalid results');
    $row = mysql_fetch_assoc($res);
    if (empty($row['Null'])) error('editselect target query did not contain a "Null" column');
    if ($row['Null'] == "YES") $nullallowed = 1;
    elseif ($row['Null'] == "NO") $nullallowed = 0;
    else error('editselect target query returned invalid "Null" column');

    $lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $query) $queries[md5($query)] = $query;
    if (!isset($queries[$_GET['hash']])) error("Query not found in queryfile");

    if (strpos($queries[$_GET['hash']], '#id') != FALSE) {
      if (empty($_GET['id'])) error("#id in query but no id specified");
      $queries[$_GET['hash']] = str_replace('#id', $_GET['id'], $queries[$_GET['hash']]);
    }
    if (!($res = mysql_query($queries[$_GET['hash']]))) error("MySQL-error: " . mysql_error());
    if (mysql_num_rows($res) < 1) error('editselect query returned no results');

    header('Content-type: application/json; charset=utf-8');
    $json = "{\n  \"items\" :\n    [ \n";
    if (mysql_num_fields($res) == 3) {
      while ($row = mysql_fetch_row($res)) {
        $json .= '      [ "' . $row[0] . '", "' . $row[1] . '", "' . $row[2] . '" ],' . "\n";
      }
    }
    else {
      while ($row = mysql_fetch_row($res)) {
        $json .= '      [ "' . $row[0] . '", "' . $row[1] . '" ],' . "\n";
      }
    }
    $json = rtrim($json, ",\n");
    $json .= "\n    ],\n  \"null\" : $nullallowed \n}\n";
    print $json;
  break;
  case 'link':
    if (empty($_POST['linkhash'])) error("No link query-hash specified");
    if (empty($_POST['unlinkhash'])) error("No unlink query-hash specified");
    if (empty($_POST['baseid']) || !is_numeric($_POST['baseid'])) error("No or invalid baseid specified");
    if (!isset($_POST['linkids']) || !isset($_POST['unlinkids'])) error("No (un)link-ID's specified");
    if (empty($_POST['linkids']) && empty($_POST['unlinkids'])) error("Both linkids and unlinkids are empty");

    $lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $query) $queries[md5($query)] = $query;

    if (!empty($_POST['linkids'])) {
      if (!isset($queries[$_POST['linkhash']])) error("Query for link not found in queryfile");
      $query = $queries[$_POST['linkhash']];
      $query = str_replace('#baseid', $_POST['baseid'], $query);
      if (strpos($queries[$_POST['linkhash']], '#linkids') != FALSE) {
        $query = str_replace('#linkids', $_POST['linkids'], $query);
        if (!($res = mysql_query($query))) error("MySQL-error: " . mysql_error());
        if (mysql_affected_rows() < 1) error("Query did not affect any rows: " . $query);
      }
      else {
        $ids = explode(',', $_POST['linkids']);
        foreach ($ids as $id) {
          $aquery = str_replace('#linkid', $id, $query);
          if (!($res = mysql_query($aquery))) error("MySQL-error: " . mysql_error());
          if (mysql_affected_rows() < 1) error("Query did not affect any rows: " . $aquery);
        }
      }
    }
    if (!empty($_POST['unlinkids'])) {
      if (!isset($queries[$_POST['unlinkhash']])) error("Query for unlink not found in queryfile");
      $query = $queries[$_POST['unlinkhash']];
      $query = str_replace('#baseid', $_POST['baseid'], $query);
      if (strpos($queries[$_POST['unlinkhash']], '#unlinkids') != FALSE) {
        $query = str_replace('#linkids', $_POST['unlinkids'], $query);
        if (!($res = mysql_query($query))) error("MySQL-error: " . mysql_error());
        if (mysql_affected_rows() < 1) error("Query did not affect any rows: " . $query);
      }
      else {
        $ids = explode(',', $_POST['unlinkids']);
        foreach ($ids as $id) {
          $aquery = str_replace('#linkid', $id, $query);
          if (!($res = mysql_query($aquery))) error("MySQL-error: " . mysql_error());
          if (mysql_affected_rows() < 1) error("Query did not affect any rows: " . $aquery);
        }
      }
    }
  break;
  case 'insert':
    if (empty($_POST['target'])) error("No target specified");
    if (empty($_POST['value'])) error("No value specified");
    if (empty($_POST['linktarget'])) error("No link target specified");

    list($table, $column) = explode(".", $_POST['target']);
    if (!$column) error("No valid target specified");
    list($linktable, $linkcolumn) = explode(".", $_POST['linktarget']);
    if (!$linkcolumn) error("No valid link target specified");

    list($counttable, $countcolumn) = explode(".", $_POST['counttarget']);
    if (!$countcolumn) error("No valid count target specified");

    if (($table != $linktable) || ($table != $counttable)) error("Not implemented yet (error code 135819y42)");

    if (!empty($_POST['countquery'])) {
      $lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
      foreach ($lines as $query) $queries[md5($query)] = $query;

      if (!isset($queries[$_POST['countquery']])) error("Query for count not found in queryfile");
      if (!($res = mysql_query($queries[$_POST['countquery']]))) error("MySQL error: " . mysql_error());
      if (!($row = mysql_fetch_row($res))) error("Query for count value returned no results");
      $countvalue = $row[0];
    }
    else {
      if (empty($_POST['countvalue'])) error("No count value specified");
      $countvalue = $_POST['countvalue'];
    }

    $query = "INSERT INTO `$table` (`$column`, `$linkcolumn`, `$countcolumn`) VALUES ('{$_POST['value']}', '{$_POST['linkvalue']}', '$countvalue')";
    if (!($res = mysql_query($query))) error("MySQL error: " . mysql_error());
    if (mysql_affected_rows() != 1) error("Query did not affect any rows: " . $query);

    header('Content-type: text/html; charset=utf-8');
    $id = $_POST['linkvalue'];
  break;
  case 'delete':
    if (empty($_POST['data'])) error('No data specified');
    $deletes = json_decode(base64_decode($_POST['data']));
    if (empty($deletes)) error('No valid queries decoded from post data');

    $lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $query) $queries[md5($query)] = $query;

    foreach ($deletes as $delete) {
      $q = str_replace("#id", $delete[1], $queries[$delete[0]]);
      if (!($res = mysql_query($q))) error("MySQL error: " . mysql_error());
//      if (mysql_affected_rows() < 1) warning("Delete query did not affect any rows: " . $q);
    }
  break;
  case 'function':
    if (empty($_POST['query'])) error("No query specified");

    $lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $query) $queries[md5($query)] = $query;

    if (!isset($queries[$_POST['query']])) error("Query not found in queries files");
    if (!($res = mysql_query($queries[$_POST['query']]))) error("MySQL error: " . mysql_error());
  break;
}

if (!empty($_POST['redirect'])) header('Location: ' . $_POST['redirect']);
