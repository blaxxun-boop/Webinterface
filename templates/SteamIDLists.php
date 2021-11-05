<?php
/**
 * @var array{"permittedlist.txt": bool, "bannedlist.txt": bool, "adminlist.txt": bool, webinterface: string}[] $steamIds
 * @var bool $canManage
 */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
	<div class="page-main">
		<?php if (isset($error)): ?>
			<div class="error" style="color: darkred; text-align: center; padding-bottom: 5px"><?=$error?></div>
		<?php endif; ?>
		<form action="" method="post">
			<table class="fancyTable sortable">
				<thead>
					<tr>
						<th class="textLeft">ID</th>
						<th class="textLeft">Permitted</th>
						<th class="textLeft">Banned</th>
						<th class="textLeft">Admin</th>
						<th class="textLeft">Webinterface</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($steamIds as $steamId => $listData): ?>
						<?php
						echo "<tr>
						<td class='textLeft'>$steamId</td>
						<td class='textLeft'><input type='checkbox' name='permittedlist.txt' value='$steamId' ", $listData["permittedlist.txt"] ? "checked" : "", ($canManage ? "" : " disabled"), "></td>
						<td class='textLeft'><input type='checkbox' name='bannedlist.txt' value='$steamId' ", $listData["bannedlist.txt"] ? "checked" : "", ($canManage ? "" : " disabled"), "></td>
						<td class='textLeft'><input type='checkbox' name='adminlist.txt' value='$steamId' ", $listData["adminlist.txt"] ? "checked" : "", ($canManage ? "" : " disabled"), "></td>
						<td class='textLeft'>{$listData["webinterface"]}</td>";
						echo "</tr>";
						?>
					<?php endforeach; ?>
					<?php if ($canManage): ?>
						<tr class="sortfixed">
							<td class='textLeft'><input type="text" placeholder="New entry" name="newEntryID" id="newEntryId"></td>
							<td class='textLeft'><input type='checkbox' name='newEntry' value='permittedlist.txt'></td>
							<td class='textLeft'><input type='checkbox' name='newEntry' value='bannedlist.txt'></td>
							<td class='textLeft'><input type='checkbox' name='newEntry' value='adminlist.txt'></td>
							<td></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ($canManage): ?>
				<input type="submit" value="Save">
			<?php endif; ?>
		</form>
	</div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>
