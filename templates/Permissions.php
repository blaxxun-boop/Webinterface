<?php
/**
 * @var array{group_id: string, groupname: string}[] $groups
 * @var int $group_id
 * @var \ValheimServerUI\Permission[] $permissions
 */
use ValheimServerUI\Permission;
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>
<div id="main" role="main">
    <div class="page-main" style="display: flex; gap: 40px;">
        <div>
			<style>
				.permissionLink { color: black; text-decoration: none !important; display: block; padding: .5em; border-top: 1px solid #0097cb; letter-spacing: 0.05em; }
				.permissionLink:hover { background: #f2f2f2; }
			</style>
			<form action="permissions/new" method="post" style="border-bottom: 1px solid #0097cb; margin-bottom: 1em; padding-bottom: 1em;">
				Create a new group<br>
				<input type="text" id="groupname" name="groupname"><br>
				<input type="submit" value="Create" style="display: block; margin-top: 5px;">
			</form>
			<div style="text-align: center; padding-bottom: .5em;">Edit an existing group</div>
			<?php foreach ($groups as $group): ?>
				<a class="permissionLink" href="permissions/<?=$group["group_id"]?>"><?=htmlspecialchars($group["groupname"])?></a>
			<?php endforeach; ?>
        </div>
		<form action="permissions/<?=$groups[$group_id]["group_id"]?>" method="post" id="permissionForm">
			<style>
				#renamegroup, .groupnameedit h1 { display: none; }
				.groupnameedit #renamegroup { display: block }
				#permissionForm th, #permissionForm td { padding: 0 30px; }
			</style>
			<h1><?=htmlspecialchars($groups[$group_id]["groupname"])?> <img src="img/editicon.svg" width="16" height="16" alt="Edit Name" title="Edit Name" style="cursor: pointer" onclick="document.getElementById('permissionForm').classList.add('groupnameedit')"></h1>
			<input type="text" value="<?=htmlspecialchars($groups[$group_id]["groupname"])?>" name="groupname" id="renamegroup" class="h1-appearance" style="margin: -2px;">
			<table class="fancyTable sortable" style="width: auto;">
				<thead>
					<tr>
						<th class="textLeft">Name</th>
						<th class="textLeft">Yes</th>
						<th class="textLeft">No</th>
					</tr>
				</thead>
				<?php foreach (Permission::cases() as $permission): ?>
					<?php if ($permission != Permission::Admin): ?>
						<tr>
							<td><?=strtr($permission->name, "_", " ")?></td>
							<td><input type="radio" name="<?=$permission->name?>" value="yes" <?=in_array($permission, $permissions) || $group_id == 1 ? "checked" : ""?> <?=$group_id == 1 ? "disabled" : ""?>></td>
							<td><input type="radio" name="<?=$permission->name?>" value="no" <?=!in_array($permission, $permissions) && $group_id != 1 ? "checked" : ""?> <?=$group_id == 1 ? "disabled" : ""?>></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</table>
			<input type="submit" value="Save">
			<?php if ($group_id > 1): ?>
				<button type="submit" name="group_id" value="<?=$group_id?>" formaction="permissions/delete" onclick="return confirm('Confirm deletion of the group \'<?=addcslashes(htmlspecialchars($groups[$group_id]["groupname"]), "\\'")?>\'')" style="float: right;">Delete</button>
			<?php endif; ?>
		</form>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>
