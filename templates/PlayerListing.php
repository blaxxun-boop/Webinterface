<?php
/**
 * @var \ValheimServerUI\Proto\PlayerList $table
 * @var string[] $stats
 */
$availableStats = [];
if ($table->getPlayerList()->count()) {
	foreach ($table->getPlayerList()[0]->getStatistics()->getStats() as $stat => $_) {
		$availableStats[] = $stat;
	}
}
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
    <div class="page-main">
		<form action="./players/stat/add" style="float: right;" method="post">
			<label>
				Add Stat column:
				<select name="stat">
					<?php foreach ($availableStats as $stat) echo "<option>$stat</option>"; ?>
				</select>
			</label>
			<input type="submit" />
		</div>
        <table class="fancyTable sortable">
            <thead>
				<tr>
					<th class="textLeft">ID</th>
					<th class="textLeft">Name</th>
					<th class="textRight">Position</th>
					<?php foreach ($stats as $stat): ?>
					<th class="textRight"><?=htmlspecialchars($stat)?><form style="display: inline;" action="./players/stat/remove" method="post"><button type="submit" name="stat" value="<?=htmlspecialchars($stat)?>" title="Remove" class="decent-remove-button">X</button></form></th>
					<?php endforeach; ?>
					<th class="textRight">Last online</th>
				</tr>
            </thead>
			<tbody>
				<?php foreach ($table->getPlayerList() as $player): /** @var \ValheimServerUI\Proto\WebinterfacePlayer $player */ ?>
					<?php
					echo "<tr>
					<td>{$player->getId()}</td>
					<td>{$player->getName()}</td>
					<td class='textRight'>", round($player->getPosition()->getX()), ", ", round($player->getPosition()->getY()), ", " , round($player->getPosition()->getZ()), "</td>";
					$playerStats = $player->getStatistics()->getStats();
					foreach ($stats as $stat)
					{
						echo "<td class='textRight'>", $playerStats[$stat] ?? 0, "</td>";
					}
					if ($player->getStatistics()->getLastTouch() == 0)
					{
						echo "<td class='textRight' data-num='", time(), "'>online</td>";
					}
					else
					{
						$formatter = new Wookieb\RelativeDate\Formatters\BasicFormatter();
						$calculator = Wookieb\RelativeDate\Calculators\TimeAgoDateDiffCalculator::full();
						echo "<td class='textRight' data-num='{$player->getStatistics()->getLastTouch()}'>" . $formatter->format($calculator->compute((new DateTime)->setTimestamp($player->getStatistics()->getLastTouch()), new DateTime())) . "</td>";
					}
					echo "</tr>";
					?>
				<?php endforeach; ?>
			</tbody>
        </table>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>
