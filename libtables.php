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

$input_reserved = array('view', 'required', 'alt-*');

if (filemtime('index.php') > filemtime('queries.txt')) {
  $fp = fopen('queries.txt', 'w');
  fclose($fp);
}
$tables = 0;
$lines = file('queries.txt', FILE_IGNORE_NEW_LINES);
foreach ($lines as $query) $queries[md5($query)] = $query;
unset($lines);
function lt_query_write($query) {
  $fp = fopen('queries.txt', 'a');
  fwrite($fp, $query . "\n");
  fclose($fp);
  $GLOBALS['queries'][md5($query)] = $query;
}

function lt_script() {
  print <<<END
<script>

var link = []
var unlink = []
var numpad_value = null;

function doEdit(cell, id, target, refresh) {
  if (document.getElementById('editbox')) return;
  var content = cell.innerHTML;
  var width = cell.offsetWidth;
  cell.innerHTML = '<input type="text" id="editbox" name="input" value="' + content + '">';
  edit = document.getElementById('editbox');
  edit.style.width = (width-15) + "px";
  window.onkeyup = function(evt){
    if ((evt.keyCode != 9) && (evt.keyCode != 13) && (evt.keyCode != 27)) return;
    window.onkeyup = null;
    if ((evt.keyCode == 9) || (evt.keyCode == 13)) {
      var xhr = new XMLHttpRequest();
      xhr.open("post", "edit.php", true);
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onreadystatechange = function(){
        if ((xhr.readyState == 4) && (xhr.status == 200)) {
          if (xhr.responseText) alert(xhr.responseText);
          cell.style.backgroundColor = "white";
          if (refresh != 'false') {
            if (refresh == 'true') location.reload(true);
            else location.href = refresh;
          }
        }
      }
      xhr.send('mode=inlineedit&target=' + target + '&id=' + id + '&value=' + encodeURIComponent(edit.value));
      cell.style.backgroundColor = "#ffa0a0";
    }
    edit.onblur = null;
    if (evt.keyCode == 27) cell.innerHTML = content;
    else cell.innerHTML = edit.value;
    if ((evt.keyCode == 9) && cell.nextSibling) {
      event = document.createEvent('Events');
      event.initEvent('click', true, false);
      cell.nextSibling.dispatchEvent(event);
    }
  }
  edit.onblur = function(){
    cell.innerHTML = content;
  }
  edit.focus();
}
function doEditSelect(cell, queryid, targetid, target, query, refresh) {
  if (document.getElementById('editselect')) return;
  var content = cell.innerHTML;
  var width = cell.offsetWidth;
  var xhr = new XMLHttpRequest();
  xhr.open("GET", "edit.php?mode=selectbox&hash=" + query + "&id=" + queryid + "&target=" + target.replace(".", ":"), true);
  xhr.onreadystatechange = function(){
    if ((xhr.readyState == 4) && (xhr.status == 200)) {
      if (xhr.getResponseHeader('Content-type').indexOf('application/json') == -1) {
        alert(xhr.responseText);
        return;
      }
      var data = JSON.parse(xhr.responseText);
      var items = data['items'];
      var html = '<select id="editselect">';
      var selected = 0;
      if (data['null'] == 1) html += '<option value=""></option>';
      if (items[0].length == 3) {
        for (var i = 0; items[i]; i++) html += '<option value="' + items[i][0] + '">' + items[i][1] + ' (' + items[i][2] + ')</option>';
      }
      else {
        for (var i = 0; items[i]; i++) {
          if (items[i][1] == content) {
            html += '<option value="' + items[i][0] + '" selected>' + items[i][1] + '</option>';
            selected = 1;
          }
          else html += '<option value="' + items[i][0] + '">' + items[i][1] + '</option>';
        }
      }
      html += '</select>';
      cell.style.backgroundColor = "white";
      cell.innerHTML = html;
      edit = document.getElementById('editselect');
      if (!selected) edit.selectedIndex = -1;
      edit.style.width = (width-15) + "px";
      edit.onblur = function(){
        cell.innerHTML = content;
      }
      edit.onchange = function(evt) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "edit.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function(){
          if ((xhr.readyState == 4) && (xhr.status == 200)) {
            if (xhr.responseText) alert(xhr.responseText);
            cell.style.backgroundColor = "white";
            if (refresh != 'false') {
              if (refresh == 'true') location.reload(true);
              else location.href = refresh;
            }
          }
        }
        xhr.send('mode=inlineedit&target=' + target + '&id=' + targetid + '&value=' + edit.options[edit.selectedIndex].value);
        cell.style.backgroundColor = "#ffa0a0";
        edit.onblur = null;
        cell.innerHTML = edit.options[edit.selectedIndex].text;
      }
      edit.focus();
    }
  }
  xhr.send();
  cell.style.backgroundColor = "#ffa0a0";
}

function checkboxAll(box, table) {
  var boxes = document.getElementsByClassName('cb_tbl' + table);
  for (var i = 0; i < boxes.length; i++) {
    if (boxes[i].checked != box.checked) {
      boxes[i].checked = box.checked;
      var event = document.createEvent('Events');
      event.initEvent('change', true, false);
      boxes[i].dispatchEvent(event);
    }
  }
}
function checkboxClick(box, table, linkid) {
  if (box.checked) {
    if (!unlink[table]) {
      link[table] = [];
      unlink[table] = [];
    }
    if (unlink[table][linkid]) {
      delete unlink[table][linkid];
      box.parentNode.style.backgroundColor = "#ffffff";
    }
    else {
      link[table][linkid] = 1;
      box.parentNode.style.backgroundColor = "#ffa0a0";
    }
  }
  else {
    if (!link[table]) {
      link[table] = [];
      unlink[table] = [];
    }
    if (link[table] && link[table][linkid]) {
      delete link[table][linkid];
      box.parentNode.style.backgroundColor = "#ffffff";
    }
    else {
      unlink[table][linkid] = 1;
      box.parentNode.style.backgroundColor = "#ffa0a0";
    }
  }
}
function checkboxUpdate(table, linkhash, unlinkhash, baseid) {
  var linkstr = "";
  var unlinkstr = "";

  for (i in link[table]) {
    if (linkstr.length) linkstr += ",";
    linkstr += i;
  }
  for (i in unlink[table]) {
    if (unlinkstr.length) unlinkstr += ",";
    unlinkstr += i;
  }

  if (linkstr.length || unlinkstr.length) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "edit.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function(){
      if ((xhr.readyState == 4) && (xhr.status == 200)) {
        if (xhr.responseText) alert(xhr.responseText);
        else {
          var boxes = document.getElementsByClassName('cb_tbl' + table);
          for (var i = 0; i < boxes.length; i++) boxes[i].parentNode.style.backgroundColor = "#ffffff";
        }
      }
    }
    xhr.send('mode=link&linkhash=' + linkhash + '&unlinkhash=' + unlinkhash + '&baseid=' + baseid + '&linkids=' + linkstr + '&unlinkids=' + unlinkstr);
  }
}
function tab_click(tab, panel_id) {
  var panels = document.getElementsByClassName('buttongrid_panel');
  for (var i = 0; i < panels.length; i++) panels[i].style.zIndex = "0";
  var selectPanel = document.getElementById('buttongrid_panel_' + panel_id);
  selectPanel.style.zIndex = "10";
}
function button_click(target, value, linktarget, linkvalue, counttarget, countdefault) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "edit.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhr.onreadystatechange = function(){
    if ((xhr.readyState == 4) && (xhr.status == 200)) {
      if (xhr.getResponseHeader('Content-type').indexOf('text/plain') >= 0) {
        alert(xhr.responseText);
        return;
      }
      document.getElementById('factuur_items').innerHTML = xhr.responseText;
    }
  }
  if (countdefault >= 0) {
    if (numpad_value) countvalue = numpad_value;
    else countvalue = countdefault;
    countquery = '';
  }
  else {
    countquery = countdefault;
    countvalue = '';
  }
  numpad_value = null;
  if (display = document.getElementById('numpad_display')) display.innerHTML = '';
  xhr.send('mode=insert&target=' + target + '&value=' + value + '&linktarget=' + linktarget + '&linkvalue=' + linkvalue + '&counttarget=' + counttarget + '&countvalue=' + countvalue + '&countquery=' + countquery);
}

function numpad_click(value) {
  if (numpad_value && (value != null)) numpad_value += value;
  else numpad_value = value;
  if (numpad_value) document.getElementById('numpad_display').innerHTML = numpad_value;
  else document.getElementById('numpad_display').innerHTML = '';
}

function openOverlay(url) {
  if (document.getElementById('overlay')) return;
  var div = document.createElement('DIV');
  div.id = 'overlay';
  div.style.top = (window.innerHeight/2-300) + 'px';
  div.style.left = (window.innerWidth/2-400) + 'px';
  document.getElementById('body').appendChild(div);
  var innerdiv = document.createElement('DIV');
  var img = document.createElement('IMG');
  img.id = 'overlay_close';
  img.src = 'close_cross.png';
  img.onclick = closeOverlay;
  div.appendChild(img);
  div.appendChild(innerdiv);

  var xhr = new XMLHttpRequest();
  xhr.open("GET", url + '&overlay=1', true);
  xhr.onreadystatechange = function() {
    if ((xhr.readyState == 4) && (xhr.status == 200)) {
      if (xhr.getResponseHeader('Content-type').indexOf('text/plain') >= 0) {
        alert(xhr.responseText);
        return;
      }
      innerdiv.innerHTML = xhr.responseText;
    }
  }
  xhr.send();
}
function closeOverlay() {
  var overlay = document.getElementById('overlay');
  overlay.parentNode.removeChild(overlay);
  if (jQuery) {
    $('.fc').fullCalendar('refetchEvents');
  }
}

function runDelete(data) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "edit.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhr.onreadystatechange = function() {
    if ((xhr.readyState == 4) && (xhr.status == 200)) {
      if (xhr.getResponseHeader('Content-type').indexOf('text/plain') >= 0) {
        alert(xhr.responseText);
        return;
      }
      location.reload();
    }
  }
  xhr.send('mode=delete&data=' + data);
}
</script>
END;
}

function lt_insert($query) {
  if (!($res = mysql_query($query))) {
    print 'MySQL error: ' . mysql_error();
    exit;
  }
  return mysql_insert_id();
}

function lt_input($title, $cols, $options = array()) {
  print "<p><table><form action=\"index.php\" method=\"post\">\n";
  print "<input type=\"hidden\" name=\"view\" value=\"" . $_GET['view'] . "\">\n";
  print "<tr><th colspan=\"" . (sizeof($cols)+1) . "\">" . $title . "</tr>\n<tr>";
  for ($c = 0; $c < sizeof($cols); $c++) {
    if ((!isset($cols[$c]['type'])  || empty($cols[$c]['type']) || ($cols[$c]['type'] != "hidden")) && (!isset($cols[$c]['name']) || empty($cols[$c]['name']))) {
      print "<p>No valid name found for column " . ($c + 1) . "</p>\n";
      return;
    }
    print "<td class=\"head\">" . $cols[$c]['name'];
  }
  print "<td class=\"head\">Invoeren</tr>\n<tr>";
  $required = array();
  $required_groups = array();
  $required_classes = array();
  $required_set = 0;
  for ($c = 0; $c < sizeof($cols); $c++) {
    if (!isset($cols[$c]['target']) || empty($cols[$c]['target']) || (strpos($cols[$c]['target'], '.') === false)) {
      print "<p>No valid target found for column " . $cols[$c]['name'] . "</p>\n";
      return;
    }
    $cols[$c]['target'] = str_replace('.', ':', $cols[$c]['target']);
    print "<td";
    if (isset($cols[$c]['required'])) {
      if ($cols[$c]['required'] === true) {
        print ' class="required"';
        $required[] = $cols[$c]['target'];
      }
      else {
        if (!isset($required_classes[$cols[$c]['required']])) $required_classes[$cols[$c]['required']] = ++$required_set;
        print ' class="required_set' . $required_classes[$cols[$c]['required']] . '"';
        if (isset($required_groups[$cols[$c]['required']])) $required_groups[$cols[$c]['required']][] = $cols[$c]['target'];
        else $required_groups[$cols[$c]['required']] = array($cols[$c]['target']);
      }
    }
    print ">";
    if (isset($cols[$c]['query'])) {
      if (!($result = mysql_query($cols[$c]['query']))) {
        print "[1] Query failed: " . mysql_error();
        return;
      }
      print "<select name=\"" . $cols[$c]['target'] . "\"";
      if (isset($cols[$c]['focus']) && $cols[$c]['focus']) print " autofocus=\"autofocus\"";
      if (isset($cols[$c]['width'])) print " style=\"width: " . $cols[$c]['width'] . ";\"";
      print ">\n<option value=\"\" selected>--</option>\n";
      while ($row = mysql_fetch_row($result)) {
        if (isset($row[3])) print "<option value=\"" . $row[0] . "\">" . $row[1] . " (" . $row[2] . ", " . $row[3] . ")</option>\n";
        elseif (isset($row[2])) print "<option value=\"" . $row[0] . "\">" . $row[1] . " (" . $row[2] . ")</option>\n";
        else print "<option value=\"" . $row[0] . "\">" . $row[1] . "</option>\n";
      }
      print "</select>\n";
    }
    elseif (isset($cols[$c]['type']) && ($cols[$c]['type'] == "date")) {
      print "<input type=\"date\" name=\"" . $cols[$c]['target'] . "\"";
      if (isset($cols[$c]['prefill'])) print " value=\"" . date('Y-m-d', strtotime($cols[$c]['prefill'])) . "\"";
      if (isset($cols[$c]['focus']) && $cols[$c]['focus']) print " autofocus=\"autofocus\"";
      if (isset($cols[$c]['width'])) print " style=\"width: " . $cols[$c]['width'] . ";\"";
      print ">\n";
    }
    elseif (isset($cols[$c]['type']) && ($cols[$c]['type'] == "datetime")) {
      print "<input type=\"datetime-local\" name=\"" . $cols[$c]['target'] . "\"";
      if (isset($cols[$c]['prefill'])) print " value=\"" . date('Y-m-d H:i', strtotime($cols[$c]['prefill'])) . "\"";
      if (isset($cols[$c]['focus']) && $cols[$c]['focus']) print " autofocus=\"autofocus\"";
      if (isset($cols[$c]['width'])) print " style=\"width: " . $cols[$c]['width'] . ";\"";
      print ">\n";
    }
    elseif (isset($cols[$c]['type']) && ($cols[$c]['type'] == "check")) {
      print "<input type=\"checkbox\" name=\"" . $cols[$c]['target'] . "\" value=\"1\"";
      if (isset($cols[$c]['prefill'])) print " checked";
      if (isset($cols[$c]['focus']) && $cols[$c]['focus']) print " autofocus=\"autofocus\"";
      if (isset($cols[$c]['width'])) print " style=\"width: " . $cols[$c]['width'] . ";\"";
      print ">\n";
    }
    elseif (isset($cols[$c]['type']) && ($cols[$c]['type'] == "hidden")) print "<input type=\"hidden\" name=\"" . $cols[$c]['target'] . "\" value=\"" . $cols[$c]['value'] . "\">\n";
    else {
      print "<input type=\"text\" name=\"" . $cols[$c]['target'] . "\"";
      if (isset($cols[$c]['prefill'])) print " value=\"" . $cols[$c]['prefill'] . "\"";
      if (isset($cols[$c]['focus']) && $cols[$c]['focus']) print " autofocus=\"autofocus\"";
      if (isset($cols[$c]['width'])) print " style=\"width: " . $cols[$c]['width'] . ";\"";
      print ">\n";
    }
    if (isset($cols[$c]['alternative'])) print "<input type=\"hidden\" name=\"alt-" . $cols[$c]['target'] . "\" value=\"" . str_replace('.', ':', $cols[$c]['alternative']) . "\">\n";
    if (isset($cols[$c]['insert_id'])) print "<input type=\"hidden\" name=\"insertid-" . strtok($cols[$c]['target'], ':') . "\" value=\"" . str_replace('.', ':', $cols[$c]['insert_id']) . "\">\n";
  }
  if (isset($options) && isset($options['redirect'])) print '<input type="hidden" name="redirect" value="' . $options['redirect'] . '">' . "\n";
  print "<td><input type=\"submit\"";
  if (isset($options) && isset($options['submit'])) print " value=\"" . $options['submit'] . "\"";
  print ">\n";
  if ($required || $required_groups) {
    print "<input type=\"hidden\" name=\"required\" value=\"";
    print implode(',', $required);
    foreach ($required_groups as $req) print '/' . implode(',', $req);
    print "\">\n";
  }
  print "</form></table></p>\n";
}

function lt_display($title, $query, $options = array()) {
  $GLOBALS['tables']++;

  if (isset($options['limit']) && is_numeric($options['limit']) && ($options['limit'] > 0)) {
    if (isset($_GET['page']) && !empty($_GET['page'])) {
      if (is_numeric($_GET['page']) && ($_GET['page'] >= 0)) {
        $limit_page = $_GET['page'];
        $limit = ' LIMIT ' . ($limit_page * $options['limit']) . ', ' . $options['limit'];
      }
      else $limit = '';
    }
    else {
      $limit_page = 0;
      $limit = ' LIMIT 0, ' . $options['limit'];
    }
    if (!($result = mysql_query('SELECT COUNT(*) FROM (' . $query . ') AS tmp'))) {
      print "[2] Query failed: " . mysql_error();
      return;
    }
    if (!($row = mysql_fetch_row($result))) {
      print "COUNT(*) query for table '$title' failed";
      return;
    }
    $maxrows = $row[0];
  }
  else $limit = '';
  if (!($result = mysql_query($query . $limit))) {
    print "[2] Query failed: " . mysql_error();
    return;
  }
  $cols = mysql_num_fields($result);
  $rows = mysql_num_rows($result);
  $span = $cols;
  if (isset($options['prependcell'])) $span += substr_count($options['prependcell'], '<td');
  if (isset($options['checkbox'])) $span++;
  if (isset($options['appendcell'])) $span += substr_count($options['appendcell'], '<td');
  if (isset($options['hidecolumn'])) $span -= count($options['hidecolumn']);
  print "<p";
  if (!empty($options['paragraph_id'])) print ' id="' . $options['paragraph_id'] . '"';
  print "><table>\n<tr><th colspan=\"$span\">";
  if ($limit) {
    if ($limit_page == 0) print '&lt;&lt; &lt; ';
    else print '<a href="?view=' . $_GET['view'] . '&page=0">&lt;&lt;</a> <a href="?view=' . $_GET['view'] . '&page=' . ($limit_page-1) . '">&lt;</a> ';
  }
  print $title;
  if ($limit) {
     print ' (' . ($limit_page * $options['limit'] + 1) . '-' . ($limit_page * $options['limit'] + $rows) . ' van <a href="?view=' . $_GET['view'] . '&page=*">' . $maxrows . '</a>)';
    if ($limit_page * $options['limit'] + $options['limit'] > $maxrows) print ' &gt; &gt;&gt;';
    else print ' <a href="?view=' . $_GET['view'] . '&page=' . ($limit_page+1) . '">&gt;</a> <a href="?view=' . $_GET['view'] . '&page=' . floor($maxrows/$options['limit']) . '">&gt;&gt;</a>';
  }
  print "</th></tr>\n<tr>";
  if (isset($options['prependhead'])) print $options['prependhead'];
  if (isset($options['checkbox'])) print '<td><input type="checkbox" onchange="checkboxAll(this, \'' . $GLOBALS['tables'] . '\');"></td>';
  for ($c = 0; $c < $cols; $c++) {
    $head[$c] = mysql_fetch_field($result);
    if (isset($options['hidecolumn']) && isset($options['hidecolumn']['#'.($c+1)])) continue;
    print '<td class="head">' . $head[$c]->name . '</td>';
  }
  if (isset($options['appendhead'])) print $options['appendhead'];
  print "</tr>\n";

  for ($r = 1; $row = mysql_fetch_row($result); $r++) {
    print "<tr>";
    if (isset($options['prependcell'])) {
      $str = $options['prependcell'];
      if (strstr($str, '#')) {
        for ($c = $cols+1; $c; $c--) $str = str_replace("#$c", $row[$c-1], $str);
        $str = str_replace("#0", $r, $str);
      }
      print $str;
    }
    if (isset($options['checkbox'])) {
      print '<td><input type="checkbox" class="cb_tbl' . $GLOBALS['tables'] . '" onchange="checkboxClick(this, \'' . $GLOBALS['tables'] . '\', \'' . $row[0] . '\');"';
      if (isset($options['checkbox']['checkedif'])) {
        $var = $options['checkbox']['checkedif']['variable'];
        if (strpos($var, '#') >= 0) {
          for ($c = $cols+1; $c; $c--) $var = str_replace("#$c", $row[$c-1], $var);
          $var = str_replace("#0", $r, $var);
          $var = str_replace("#c", $head[$c-1]->name, $var);
        }
        if ($var == $options['checkbox']['checkedif']['constant']) print ' checked';
      }
      print '></td>';
    }
    for ($c = 1; $c <= $cols; $c++) {
      if (isset($options['hidecolumn']) && isset($options['hidecolumn']['#'. $c])) continue;
      print '<td';
      if (isset($options['idformat'])) {
        $str = $options['idformat'];
        for ($i = $cols+1; $i; $i--) $str = str_replace("#$i", $row[$i-1], $str);
        $str = str_replace("#0", $r, $str);
        $str = str_replace("#c", $head[$i-1]->name, $str);
        print ' id="' . $str . '"';
      }
      if (isset($options['class']) && isset($options['class']["#$c"])) print ' class="' . $options['class']["#$c"] . '"';
      if (isset($options['edit']) && isset($options['edit']["#$c"])) {
        $refresh = 'false';
        if (is_array($options['edit']["#$c"])) {
          if (empty($options['edit']["#$c"]['target'])) print ' onclick="alert(\'No target specified for inline edit on column ' . $c . '\');"';
          else {
            if (isset($options['edit']["#$c"]['refresh'])) {
              if ($options['edit']["#$c"]['refresh'] === true) $refresh = 'true';
              elseif (is_string($options['edit']["#$c"]['refresh'])) {
                $refresh = $options['edit']["#$c"]['refresh'];
                if (strpos($refresh, '#') !== false) {
                  for ($i = $cols+1; $i; $i--) $refresh = str_replace("#$i", $row[$i-1], $refresh);
                  $refresh = str_replace("#0", $r, $refresh);
                  $refresh = str_replace("#c", $head[$i-1]->name, $refresh);
                }
              }
            }
            print ' onclick="doEdit(this, \'' . $row[0] . '\', \'' . $options['edit']["#$c"]['target'] . '\', \'' . $refresh . '\')"';
          }
        }
        else print ' onclick="doEdit(this, \'' . $row[0] . '\', \'' . $options['edit']["#$c"] . '\', \'' . $refresh . '\')"';
      }
      elseif (isset($options['editselect']) && isset($options['editselect']["#$c"])) {
        if (!isset($GLOBALS['queries'][md5($options['editselect']["#$c"]['query'])])) lt_query_write($options['editselect']["#$c"]['query']);
        if (isset($options['editselect']["#$c"]['query_id'])) {
          $column = str_replace('#', '', $options['editselect']["#$c"]['query_id']);
          if (!is_numeric($column)) print ' onclick="alert(\'Specified query_id for editselect is not numeric\');"';
          else $query_id = $row[--$column];
        }
        else $query_id = $row[0];
        if (isset($options['editselect']["#$c"]['target_id'])) {
          $column = str_replace('#', '', $options['editselect']["#$c"]['target_id']);
          if (!is_numeric($column)) print ' onclick="alert(\'Specified target_id for editselect is not numeric\');"';
          else $target_id = $row[--$column];
        }
        else $target_id = $row[0];
        $refresh = 'false';
        if (isset($options['editselect']["#$c"]['refresh'])) {
          if ($options['editselect']["#$c"]['refresh'] === true) $refresh = 'true';
          else $refresh = $options['editselect']["#$c"]['refresh'];
        }
        if (strpos($refresh, '#') !== false) {
          for ($i = $cols+1; $i; $i--) $refresh = str_replace("#$i", $row[$i-1], $refresh);
          $refresh = str_replace("#0", $r, $refresh);
          $refresh = str_replace("#c", $head[$i-1]->name, $refresh);
        }
        if (isset($query_id) && isset($target_id))
          print ' onclick="doEditSelect(this, \'' . $query_id . '\', \'' . $target_id . '\', \'' . $options['editselect']["#$c"]['target'] . '\', \'' . md5($options['editselect']["#$c"]['query']) . '\', \'' . $refresh . '\')"';
      }
      print '>';
      if (isset($options['symbol']) && isset($options['symbol']["#$c"])) print '<div style="float: left; text-align: left;">' . $options['symbol']["#$c"] . '&nbsp;</div>';
      print $row[$c-1] . '</td>';
      if ((isset($options['sum']) && $options['sum']["#$c"]) || (isset($options['avg']) && $options['avg']["#$c"])){
        if (is_numeric($row[$c-1])) $sums[$c] += $row[$c-1];
        elseif (isset($row[$c-1])) $sums[$c] = 'NaN';
        $avgs[$c]++;
      }
    }
    if (isset($options['functionifempty'])) {
      for ($n = 1; $n <= $cols; $n++) {
        if (!isset($row[$n-1]) && isset($options['functionifempty']["#$n"])) {
          $q = $options['functionifempty']["#$n"]['query'];
          if (strstr($q, '#')) {
            for ($c = $cols+1; $c; $c--) $q = str_replace("#$c", $row[$c-1], $q);
            $q = str_replace("#0", $r, $q);
          }
          if (!isset($GLOBALS['queries'][md5($q)])) lt_query_write($q);
          print '<td><form action="edit.php" method="post"><input type="hidden" name="mode" value="function">';
          print '<input type="hidden" name="query" value="' . md5($q) . '">';
          if (!empty($options['functionifempty']["#$n"]['redirect'])) print '<input type="hidden" name="redirect" value="'. $options['functionifempty']["#$n"]['redirect'] . '">';
          print '<input type="submit" value="' . $options['functionifempty']["#$n"]['name'] . '"></form></td>';
        }
      }
    }
    if (isset($options['appendcell'])) {
      $str = $options['appendcell'];
      if (strstr($str, '#')) {
        for ($c = $cols+1; $c; $c--) $str = str_replace("#$c", $row[$c-1], $str);
        $str = str_replace("#0", $r, $str);
      }
      print $str;
    }
    if (isset($options['appendifnotempty'])) {
      for ($n = 1; $n <= $cols; $n++) {
        if (!empty($row[$n-1]) && isset($options['appendifnotempty']["#$n"])) {
          $str = $options['appendifnotempty']["#$n"];
          if (strstr($str, '#')) {
            for ($c = $cols+1; $c; $c--) $str = str_replace("#$c", $row[$c-1], $str);
            $str = str_replace("#0", $r, $str);
          }
          print $str;
        }
      }
    }
    if (isset($options['appendifempty'])) {
      for ($n = 1; $n <= $cols; $n++) {
        if (!isset($row[$n-1]) && isset($options['appendifempty']["#$n"])) {
          $str = $options['appendifempty']["#$n"];
          if (strstr($str, '#')) {
            for ($c = $cols+1; $c; $c--) $str = str_replace("#$c", $row[$c-1], $str);
            $str = str_replace("#0", $r, $str);
          }
          print $str;
          break;	// Only apply the first occurring appendifempty entry; remove to break to apply all of them
        }
      }
    }
    if (isset($options['delete'])) {
      print '<td><input type="button"';
      if (!empty($options['delete']['submit'])) print ' value="' . $options['delete']['submit'] . '"';
      else print ' value="Delete"';
      if (!empty($options['delete']['warning'])) {
        $str = $options['delete']['warning'];
        if (strstr($str, '#')) {
          for ($c = $cols+1; $c; $c--) $str = str_replace("#$c", $row[$c-1], $str);
          $str = str_replace("#0", $r, $str);
        }
        $jsarray = array();
        for ($c = 0; isset($options['delete']['queries'][$c]); $c++) {
          if (!isset($GLOBALS['queries'][md5($options['delete']['queries'][$c]['query'])])) lt_query_write($options['delete']['queries'][$c]['query']);
          $deleteid = $options['delete']['queries'][$c]['id'];
          for ($d = $cols+1; $d; $d--) $deleteid = str_replace("#$d", $row[$d-1], $deleteid);
          $jsarray[] = array(md5($options['delete']['queries'][$c]['query']), $deleteid);
        }
        print ' onclick="if (confirm(\'' . $str . '\')) runDelete(\'' . base64_encode(json_encode($jsarray)) . '\');"></td>';
      }
    }
    print "</tr>\n";
  }
  if (isset($options['sum'])) {
    print '<tr>';
    $start = 1;
    $done = 0;
    if (isset($options['checkbox'])) print '<td style="padding-left: 12px;">&#8595;</td>';
    for ($c = 1; $c <= $cols; $c++) {
      if (isset($options['hidecolumn']) && isset($options['hidecolumn']['#' . $c])) {
        $start++;
        continue;
      }
      if ($sums[$c]) {
        if ($c > $start) {
          if ($done) print '<td colspan="' . ($c-$start) . '"></td>';
          else print '<td class="sum" colspan="' . ($c-$start) . '">Totalen</td>';
          $done = 1;
        }
        $start = $c+1;
        if (isset($options['class']) && isset($options['class']["#$c"])) print '<td class="sum ' . $options['class']["#$c"] . '">';
        else print '<td class="sum">';
        if (isset($options['symbol']) && isset($options['symbol']["#$c"])) print '<div style="float: left; text-align: left;">' . $options['symbol']["#$c"] . '&nbsp;</div>';
        if (strpos($options['sum']["#$c"], '%') !== false) printf($options['sum']["#$c"] . '</td>', $sums[$c]);
        else print $sums[$c] . '</td>';
      }
    }
    print "</tr>\n";
  }
  if (isset($options['avg'])) {
    print '<tr>';
    $start = 1;
    $done = 0;
    if (isset($options['checkbox'])) print '<td style="padding-left: 12px;">&#8595;</td>';
    for ($c = 1; $c <= $cols; $c++) {
      if (isset($options['hidecolumn']) && isset($options['hidecolumn']['#' . $c])) {
        $start++;
        continue;
      }
      if ($avgs[$c]) {
        if ($c > $start) {
          if ($done) print '<td colspan="' . ($c-$start) . '"></td>';
          else print '<td class="sum" colspan="' . ($c-$start) . '">Gemiddelden</td>';
          $done = 1;
        }
        $start = $c+1;
        if (isset($options['class']) && isset($options['class']["#$c"])) print '<td class="sum ' . $options['class']["#$c"] . '">';
        else print '<td class="sum">';
        if (isset($options['symbol']) && isset($options['symbol']["#$c"])) print '<div style="float: left; text-align: left;">' . $options['symbol']["#$c"] . '&nbsp;</div>';
        if (strpos($options['avg']["#$c"], '%') !== false) printf($options['avg']["#$c"] . '</td>', $sums[$c]/$avgs[$c]);
        else print ($sums[$c]/$avgs[$c]) . '</td>';
      }
    }
    print "</tr>\n";
  }
  if (isset($options['checkbox'])) {
    print '<tr><td colspan="' . $span . '" style="padding-left: 12px;">&#8627; ';
    if ((!empty($options['checkbox']['link']) || !empty($options['checkbox']['unlink'])) && !empty($options['checkbox']['baseid'])) {
      if (empty($options['checkbox']['link'])) $options['checkbox']['link'] = "-";
      elseif (!isset($GLOBALS['queries'][md5($options['checkbox']['link'])])) lt_query_write($options['checkbox']['link']);
      if (empty($options['checkbox']['unlink'])) $options['checkbox']['unlink'] = "-";
      elseif (!isset($GLOBALS['queries'][md5($options['checkbox']['unlink'])])) lt_query_write($options['checkbox']['unlink']);
      print '<input type="submit"';
      if (isset($options['checkbox']['submit'])) print ' value="' . $options['checkbox']['submit'] . '"';
      print ' onclick="checkboxUpdate(\'' . $GLOBALS['tables'] . '\', \'' . md5($options['checkbox']['link']) . '\', \'' . md5($options['checkbox']['unlink']) . '\', \'' . $options['checkbox']['baseid'] . '\');"';
      print '>';
    }
    print '</td></tr>';
  }
  mysql_free_result($result);
  print '</table></p>';
}

function lt_parse_input() {
  if (isset($_POST['required']) && !empty($_POST['required'])) {
    $required_groups = explode('/', $_POST['required']);
    $required = explode(',', $required_groups[0]);
    array_splice($required_groups, 0, 1);
    foreach ($required_groups as $req) $required_groups_arrays[] = explode(',', $req);
  }

  $tables = array();

  foreach ($_POST as $name => $value) {
    if (($name == 'view') || ($name == 'required') || ($name == 'redirect') || fnmatch('alt-*', $name) || fnmatch('insertid-*', $name)) continue;

    list($table, $column) = explode(':', $name);
    if (!$table || !$column) {
      print "<p>Incorrect target specification in lt_input(): " . $name;
      return;
    }

    if ($_POST[$name] == "") {
      $fix = '';
      if (isset($_POST['alt-'.$name]) && !empty($_POST['alt-'.$name])) {
        $query = $_POST['alt-'.$name];
        $query = preg_replace_callback('/&([A-Za-z0-9_:-]+)/', function($matches) { return $_POST[$matches[1]]; }, $query);
        if (!strpos($query, '&')) {
          if (!($result = mysql_query($query))) {
            print "<p>MySQL error: " . mysql_error() . "</p><p>Query was: $query</p>\n";
            return;
          }
          if (!($row = mysql_fetch_row($result)) || !$row[0]) {
            print "<p>Alternative query for " . $name . " failed to return a result</p>";
            return;
          }
          $fix = $row[0];
        }
        else {
          print "<p>Error: token replacement in alternative query failed: " . $query . "</p>";
          return;
        }
      }
      if ($fix) $tables[$table]['columns'][$column] = $fix;
      else {
        if (in_array($name, $required)) {
          print 'Error: field ' . $name . ' is required';
          return;
        }
      }
    }
    else $tables[$table]['columns'][$column] = mysql_real_escape_string($value);

    if (isset($_POST['insertid-'.$table]) && !empty($_POST['insertid-'.$table])) {
      list($table2, $column2) = explode(':', $_POST['insertid-'.$table]);
      if (!$table2 || !$column2) {
        print "<p>Incorrect target specification in insert_id for column " . $name . ": " . $_POST['insertid-'.$table];
        return;
      }
      $tables[$table2]['insert_id_from'] = $table;
      $tables[$table]['insert_id_into'] = $column2;
    }
  }

  foreach ($tables as $name => $value) {
    if (!isset($tables[$name]['columns'])) continue;
    if (isset($tables[$name]['insert_id_from'])) {
      $insert_id = null;
      if (isset($tables[$tables[$name]['insert_id_from']]['columns'])) {
        $insert_id = lt_run_query($tables[$name]['insert_id_from'], $tables[$tables[$name]['insert_id_from']]);
        if (!$insert_id) {
          print "<p>lt_run_query() did not return an insert_id</p>";
          break;
        }
        unset($tables[$table['insert_id_from']]['columns']);
      }
      elseif (isset($tables[$tables[$name]['insert_id_from']]['insert_id'])) $insert_id = $tables[$tables[$name]['insert_id_from']]['insert_id'];

      if ($insert_id !== null) $tables[$name]['columns'][ $tables[$tables[$name]['insert_id_from']]['insert_id_into'] ] = $insert_id;
    }
    $tables[$name]['insert_id'] = lt_run_query($name, $tables[$name]);
    unset($tables[$name]['columns']);
  }

  if (!empty($_POST['redirect'])) {
    $redir = str_replace('#id', mysql_insert_id(), $_POST['redirect']);
    parse_str($redir, $_GET);
    print '<script>history.replaceState("", "Redirect", "' . $_SERVER['PHP_SELF'] . '?' . $redir . '");</script>';
  }
  else $_GET['view'] = $_POST['view'];
}

function lt_run_query($table, $data) {
    $query = 'INSERT INTO ' . $table . ' (' . implode(',', array_keys($data['columns'])) . ') VALUES ("' . implode('","', array_values($data['columns'])) . '")';
    $query = str_replace('""', 'NULL', $query);

    if (!($result = mysql_query($query))) print "<p>[3] Query failed: " . mysql_error() . "</p><p>Query was: $query</p>\n";
    elseif (mysql_affected_rows() != 1) print "<p>[4] Query failed: mysql_affected_rows() == " . mysql_affected_rows() . "</p>";
    else print "<p>Rij toegevoegd aan tabel $table</p>";

    return mysql_insert_id();
}

function lt_count($query) {
  if (!($result = mysql_query('SELECT COUNT(*) FROM (' . $query . ') AS tmp'))) return -1;
  if (!($row = mysql_fetch_row($result))) return -1;
  if (!is_numeric($row[0])) return -1;
  return $row[0]+0;
}

function lt_buttongrid($title, $groups, $options = array()) {
  print '<div ';
  if (isset($options['cssid'])) print 'id="' . $options['cssid'] . '" ';
  print 'class="buttongrid';
  if (isset($options['class'])) print ' ' . $options['class'];
  print '"><div class="buttongrid_tabbar">';

  for ($n = 0; isset($groups[$n]); $n++) {
    print '<span class="buttongrid_tab" onclick="tab_click(this, ' . $n . ');">' . $groups[$n]['name'] . '</span>';
  }
  print '</div><div class="buttongrid_panels">';
  for ($n = count($groups)-1; $n >= 0; $n--) {
    if (!($res = mysql_query($groups[$n]['query']))) {
      print 'MySQL error: ' . mysql_error();
      exit;
    }
    print '<div id="buttongrid_panel_' . $n . '" class="buttongrid_panel">';
    while ($row = mysql_fetch_assoc($res)) {
      print '<div style="display: inline-block; position: relative;">';
      print '<input type="button" value="';
      for ($i = 1; isset($row['line'.$i]); $i++) {
        if ($i > 1) print "\n";
        print $row['line'.$i];
      }
      if (isset($options['count_query'])) {
        if (!isset($GLOBALS['queries'][md5($options['count_query'])])) lt_query_write($options['count_query']);
        print '" class="buttongrid_button" onclick="button_click(\'' . $options['target'] . '\', ' . $row['id'] . ', \'' . $options['link_target'] . '\', \'' . $options['link_value'] . '\', \'';
        print $options['count_target'] . '\', \'' . md5($options['count_query']) . '\')"';
      }
      else {
        print '" class="buttongrid_button" onclick="button_click(\'' . $options['target'] . '\', ' . $row['id'] . ', \'' . $options['link_target'] . '\', \'' . $options['link_value'] . '\', \'';
        print $options['count_target'] . '\', \'' . $options['count_default'] . '\')"';
      }
      if (isset($row['stock']) && ($row['stock'] <= 0)) print ' style="opacity: 0.2;"';
      elseif (isset($row['stock']) && ($row['stock'] <= 10)) print ' style="opacity: 0.6;"';
      print '>';
      if (isset($row['topleft'])) print '<span class="buttongrid_topleft">' . $row['topleft'] . '</span>';
      if (isset($row['topright'])) print '<span class="buttongrid_topright">' . $row['topright'] . '</span>';
      if (isset($row['bottomleft'])) print '<span class="buttongrid_bottomleft">' . $row['bottomleft'] . '</span>';
      if (isset($row['bottomright'])) print '<span class="buttongrid_bottomright">' . $row['bottomright'] . '</span>';
      print '</div>';
    }
    print '</div>';
  }
  print '</div></div>';
}

function lt_numpad($title) {
  print '<div class="numpad">' . $title . '<br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_7" class="numpad_button" type="button" value="7" onclick="numpad_click(\'7\');">';
  print '<input id="numpad_button_8" class="numpad_button" type="button" value="8" onclick="numpad_click(\'8\');">';
  print '<input id="numpad_button_9" class="numpad_button" type="button" value="9" onclick="numpad_click(\'9\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_4" class="numpad_button" type="button" value="4" onclick="numpad_click(\'4\');">';
  print '<input id="numpad_button_5" class="numpad_button" type="button" value="5" onclick="numpad_click(\'5\');">';
  print '<input id="numpad_button_6" class="numpad_button" type="button" value="6" onclick="numpad_click(\'6\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_1" class="numpad_button" type="button" value="1" onclick="numpad_click(\'1\');">';
  print '<input id="numpad_button_2" class="numpad_button" type="button" value="2" onclick="numpad_click(\'2\');">';
  print '<input id="numpad_button_3" class="numpad_button" type="button" value="3" onclick="numpad_click(\'3\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_0" class="numpad_button" type="button" value="0" onclick="numpad_click(\'0\');">';
  print '<div id="numpad_display"></div>';
  print '<input id="numpad_button_c" class="numpad_button" type="button" value="C" onclick="numpad_click(null);">';
  print '</span><br>';
  print '</div>';
}

function lt_calendar($title, $queries, $options) {
  if (empty($queries['select'])) {
    print "<p>lt_calendar() function needs to have a 'select'-query defined</p>";
    return;
  }
  if (!isset($GLOBALS['queries'][md5($queries['select'])])) lt_query_write($queries['select']);
  if (!empty($queries['update']) && !isset($GLOBALS['queries'][md5($queries['update'])])) lt_query_write($queries['update']);
  if (!empty($queries['insert']) && !isset($GLOBALS['queries'][md5($queries['insert'])])) lt_query_write($queries['insert']);
print <<<END

<div id="lt_calendar"></div>
<script>
$('#lt_calendar').fullCalendar({
END;
  if (!empty($queries['update'])) print ' editable: true,';
  else print ' editable: false,';
  if (!empty($queries['insert'])) print ' selectable: true,';
  else print ' selectable: false,';
print <<<END
    defaultView: 'agendaWeek',
    allDayDefault: false,
    axisFormat: 'HH:mm',
    timeFormat: 'HH:mm{ - HH:mm}',
    eventSources: [
      {
        url: 'calendar.php',
        data: {
          mode: 'select',
END;
  if (!empty($options['url'])) print ' url: "' . $options['url'] . '",';
  print ' query: "' . md5($queries['select']) . '"';
print <<<END
        },
        error: function() { alert('error getting json feed for lt_calendar'); }
      }
    ],
END;
  if (!empty($options['url']) && isset($options['overlay']) && ($options['overlay'] === true)) print ' eventClick: function(event) { openOverlay(event.url); return false; },';
print <<<END
    eventDrop: function(event, dayDelta, minuteDelta, allDay, revertFunc) {
      $.ajax({
        url: 'calendar.php',
        type: 'POST',
        data: {
          mode: 'update',
END;
  print ' query: "' . md5($queries['update']) . '",';
print <<<END
          id: event.id,
          start: event.start.getTime()/1000,
          end: event.end.getTime()/1000
        },
        error: function(jqXHR, textStatus, errorThrown) {
          alert(errorThrown);
          revertFunc();
        }
      });
    },
    eventResize: function(event, dayDelta, minuteDelta, revertFunc) {
      $.ajax({
        url: 'calendar.php',
        type: 'POST',
        data: {
          mode: 'update',
END;
  print ' query: "' . md5($queries['update']) . '",';
print <<<END
          id: event.id,
          start: event.start.getTime()/1000,
          end: event.end.getTime()/1000
        },
        error: function(jqXHR, textStatus, errorThrown) {
          alert(errorThrown);
          revertFunc();
        }
      });
    },
    select: function(startDate, endDate, allDay) {
      $.ajax({
        url: 'calendar.php',
        type: 'POST',
        data: {
          mode: 'insert',
END;
  print ' query: "' . md5($queries['insert']) . '",';
print <<<END
          start: startDate.getTime()/1000,
          end: endDate.getTime()/1000
        },
        success: function() { // Temporary workaround for Chrome asynchronous prefetching bug
          window.setTimeout("$('#lt_calendar').fullCalendar('refetchEvents');", 100);
        },
        error: function(jqXHR, testStatus, errorThrown) {
          alert(errorThrown);
          revertFunc();
        }
      });
    }
});
</script>

END;
}
