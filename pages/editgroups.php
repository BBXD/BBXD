<?php
if (!defined('BLARG')) die();

CheckPermission('admin.editgroups');

$title = __('Edit groups');
MakeCrumbs([actionLink('admin') => __('Admin'), '' => __('Edit groups')]);

$gtypes = [
	0 => __('Primary'),
	1 => __('Secondary')
];

$gdisplays = [
	-1 => __('Hidden'),
	0 => __('Regular'),
	1 => __('Staff')
];

if (!$http->post('saveaction')) {
	$groups = Query("SELECT * FROM {usergroups} WHERE rank<={0} ORDER BY type, rank", $loguserGroup['rank']);
	$gdata = [];

	while ($group = Fetch($groups)) {
		$gtitle = htmlspecialchars($group['title']);
		if (!$group['type'])
			$gtitle = '<span class="userlink" style="color:'.htmlspecialchars($group['color_unspec']).';">'.$gtitle.'</span>';

		$gdata[] = actionLinkTag($gtitle, 'editgroups', $group['id']);
	}

	RenderTemplate('grouplist', ['groups' => $gdata]);
}

if (isset($http->get('id'))) {
	$gid = (int)$http->get('id');
	$group = Fetch(Query("SELECT * FROM {usergroups} WHERE id={0}", $gid));
	if (!$group)
		Kill(__('Invalid group ID.'));

	if ($group['rank'] > $loguserGroup['rank'])
		Kill(__('You may not edit this group.'));

	MakeCrumbs([actionLink('admin') => __('Admin'), actionLink('editgroups') => __('Edit groups'), '' => htmlspecialchars($group['title'])]);

	$canPromoteHigher = $loguser['root'] && ($gid == $loguserGroup['id']);
} else {
	MakeCrumbs([actionLink('admin') => __('Admin'), actionLink('editgroups') => __('Edit groups')]);
	Alert(__('Select a group above to edit it.'), __('Notice'));
	return;
}

$error = '';
if ($http->post('saveaction')) {
	if ($http->post('token') !== $loguser['token'])
		Kill(__('No.'));

	// save shit
} else
	$_POST = $group;

if ($error)
	Alert($error, __('Error'));

$permlist = [];
$fpermlist = [];

echo '
	<form action="" method="POST" onsubmit="saveaction.disabled = true; return true;">
		<table class="outline margin">
			<tr class="header1"><th colspan="2">'.__('Editing group ').htmlspecialchars($group['title']).'</th></tr>
			<tr>
				<td class="cell2 center" style="width:150px;">'.__('Title').'</td>
				<td class="cell1"><input type="text" name="title" value="'.htmlspecialchars($http->post('title')).'" style="width:98%;" maxlength="256"></td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Rank').'</td>
				<td class="cell1">
					<input type="text" name="rank" value="'.htmlspecialchars($http->post('rank')).'" size="8" maxlength="8">
					'.($canPromoteHigher ? '' : 'Maximum value: '.$loguserGroup['rank']).'
				</td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Type').'</td>
				<td class="cell1">'.makeSelect('type', $http->post('type'), $gtypes).'</td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Display').'</td>
				<td class="cell1">'.makeSelect('display', $http->post('display'), $gdisplays).'</td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Male name color').'</td>
				<td class="cell1"><input type="text" name="color_male" value="'.htmlspecialchars($http->post('color_male')).'" size="8" maxlength="8"></td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Female name color').'</td>
				<td class="cell1"><input type="text" name="color_female" value="'.htmlspecialchars($http->post('color_female')).'" size="8" maxlength="8"></td>
			</tr>
			<tr>
				<td class="cell2 center">'.__('Unspec name color').'</td>
				<td class="cell1"><input type="text" name="color_unspec" value="'.htmlspecialchars($http->post('color_unspec')).'" size="8" maxlength="8"></td>
			</tr>
		</table>
		<table class="outline margin">
			<tr>
				<td class="cell1 center"><input type="submit" name="saveaction" value="'.__('Save all changes').'"></td>
			</tr>
		</table>
		<table style="width:100%;"><tr><td style="vertical-align:top;width:50%;padding-right:0.5em;">
		<table class="outline margin">
			<tr class="header1"><th colspan="2">'.__('General permissions').'</th></tr>';

$perms = Query("SELECT * FROM {permissions} WHERE applyto=0 AND id={0} AND perm!={1} AND arg=0 ORDER BY perm", $gid, 'forum.viewforum');
while ($perm = Fetch($perms))
	$permlist[$perm['perm']] = $perm['value'];

PermTable('user');
PermTable('forum');
PermTable('mod');
PermTable('admin');

echo '
		</table>
		</td><td style="vertical-align:top;padding-left:0.5em;">
		<table class="outline margin">
			<tr class="header1"><th colspan="2">'.__('Per-forum permissions').'</th></tr>';

$fperms = Query("SELECT p.*, f.title ftitle FROM {permissions} p LEFT JOIN {forums} f ON f.id=p.arg
	WHERE p.applyto=0 AND p.id={0} AND (SUBSTR(p.perm,1,6)={1} OR SUBSTR(p.perm,1,4)={2}) AND p.arg!=0 ORDER BY p.arg, p.perm", 
	$gid, 'forum.', 'mod.');
while ($fperm = Fetch($fperms)) {
	$fpermlist[$fperm['arg']][$fperm['perm']] = $fperm['value'];
	if (!$fpermlist[$fperm['arg']]['_ftitle'])
		$fpermlist[$fperm['arg']]['_ftitle'] = $fperm['ftitle'];
}

if (!empty($fpermlist)) {
	foreach ($fpermlist as $fid=>$fpl)
		ForumPermTable($fid, $fpl);
} else
	echo '
			<tr class="cell1"><td>'.__('No permissions.').'</td></tr>';

echo '
		</table>
		<table class="outline margin">';
ForumPermTable(0);
echo '
		</table>
		</td></tr></table>
		<table class="outline margin">
			<tr>
				<td class="cell1 center"><input type="submit" name="saveaction" value="'.__('Save all changes').'"></td>
			</tr>
		</table>
		<input type="hidden" name="token" value="'.htmlspecialchars($loguser['token']).'">
	</form>';


// yay copypasta
function makeSelect($fieldName, $checkedIndex, $choicesList, $extras = "")
{
	foreach($choicesList as $key=>$val)
		$options .= format("
						<option value=\"{0}\"{1}>{2}</option>", $key, ($key==$checkedIndex)?' selected="selected"':'', $val);
	$result = format(
"
					<select id=\"{0}\" name=\"{0}\" size=\"1\" {1} >{2}
					</select>", $fieldName, $extras, $options);
	return $result;
}

function PermSwitch($field, $val) {
	return '
					<label><input type="radio" name="'.$field.'" value="-1"'.(($val==-1) ? ' checked="checked"':'').'> '.__('Deny').'</label>
					<label><input type="radio" name="'.$field.'" value="0"'.(($val==0) ? ' checked="checked"':'').'> '.__('Neutral').'</label>
					<label><input type="radio" name="'.$field.'" value="1"'.(($val==1) ? ' checked="checked"':'').'> '.__('Allow').'</label>';
}

function PermTable($cat) {
	global $permlist, $permCats, $permDescs;

	echo '
			<tr class="header0">
				<th colspan="2">'.htmlspecialchars($permCats[$cat]).'</th>
			</tr>';

	foreach ($permDescs[$cat] as $permid=>$permname) {
		if ($permid == 'forum.viewforum') continue;

		$pkey = 'perm_'.str_replace('.', '_', $permid);

		echo '
			<tr>
				<td class="cell2 center" style="width: 250px;">'.htmlspecialchars($permname).'</td>
				<td class="cell1">'.PermSwitch($pkey, isset($http->post($pkey)) ? $http->post($pkey) : $permlist[$permid]).'</td>
			</tr>';
	}
}

function ForumPermTable($fid, $fpl=[]) {
	global $permCats, $permDescs;

	if (!$fid) {
		echo '
			<tr class="header0">
				<th colspan="2">'.__('Add permission set').'</th>
			</tr>
			<tr>
				<td class="cell2 center">Forum</td>
				<td class="cell1">'.makeForumList('newforumid', 0).'</td>
			</tr>
			<tr class="header0">
				<th colspan="2" style="height:6px;"></th>
			</tr>';
	} else {
		echo '
			<tr class="header0">
				<th colspan="2">'.htmlspecialchars($fpl['_ftitle']).'</th>
			</tr>';
		unset($fpl['_ftitle']);
	}

	$lastcat = -1;
	$pd = ['forum' => $permDescs['forum'], 'mod' => $permDescs['mod']];
	foreach ($pd as $cat=>$perms) {
		if ($lastcat != $cat) {
			if ($lastcat != -1)
				echo '
			<tr class="header0">
				<th colspan="2" style="height:6px;"></th>
			</tr>';
			$lastcat = $cat;
		}

		foreach ($perms as $permid=>$permname) {
			$pkey = 'fperm_'.$fid.'_'.str_replace('.', '_', $permid);

			echo '
			<tr>
				<td class="cell2 center" style="width: 250px;">'.htmlspecialchars($permname).'</td>
				<td class="cell1">'.PermSwitch($pkey, isset($http->post($pkey)) ? $http->post($pkey) : $fpl[$permid]).'</td>
			</tr>';
		}
	}

	if (!$fid) {
		echo '
			<tr class="header0">
				<th colspan="2" style="height:6px;"></th>
			</tr>
			<tr>
				<td class="cell2">&nbsp;</td>
				<td class="cell1"><input type="submit" name="addfpermaction" value="'.__('Add permissions').'"></td>
			</tr>';
	}
}