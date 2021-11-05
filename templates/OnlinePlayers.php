<?php
/** @var \ValheimServerUI\Proto\WebinterfacePlayer[] $players */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
	<?php if (!$players): ?>
		<h1>No players online</h1>
    	<div class="bigcard">There are currently no players online that you can manage.</div>
	<?php else: ?>
		<form class="page-main" method="post">
			<table class="fancyTable sortable">
				<thead>
					<tr>
						<th class="nosort" style="width: 15px;"></th>
						<th class="textLeft">ID</th>
						<th class="textLeft">Name</th>
						<th class="textRight">Position</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($players as $player): ?>
						<tr>
							<td style="width: 15px;"><input type="checkbox" name="steamId" value="<?=$player->getId()?>"></td>
							<td><?=$player->getId()?></td>
							<td><?=$player->getName()?></td>
							<td class='textRight'><?=round($player->getPosition()->getX()), ", ", round($player->getPosition()->getY()), ", " , round($player->getPosition()->getZ())?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<small><a href="#" onclick="document.querySelectorAll('input[name=\'steamId\']').forEach(el => el.checked = true); return false;">Select all</a> / <a href="#" onclick="document.querySelectorAll('input[name=\'steamId\']').forEach(el => el.checked = false); return false;">Select none</a></small><br><br>
			<input type="text" name="message" style="width: 100%" placeholder="Message" /><br style="margin-bottom: 5px;"><input type="submit" formaction="players/sendmessage" value="Send message"> <input type="submit" formaction="players/kick" value="Kick with message">
		</form>
	<?php endif; ?>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>
