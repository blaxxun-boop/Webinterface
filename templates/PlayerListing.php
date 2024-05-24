<?php
/**
 * @var \ValheimServerUI\Proto\PlayerList $table
 * @var string[] $stats
 * @var bool $canManage
 */

if (!function_exists("formatStat")) {
	function formatStat($stat) {
		return preg_replace("((?|([a-z])(?=[A-Z])|([a-zA-Z])(?=[0-9])|([0-9])(?=[a-zA-Z])))", "$1 ", $stat);
	}
}

$availableStats = ["Position"];
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
        <?php if ($canManage): ?>
			<form action="./players/stat/add" style="float: right;" method="post">
				<label>
					Add Stat column:
					<select name="stat">
						<?php foreach (array_diff($availableStats, $stats) as $stat) echo "<option value='$stat'>", formatStat($stat), "</option>"; ?>
					</select>
				</label>
				<input type="submit" />
			</div>
        <?php endif; ?>
        <table class="fancyTable sortable">
            <thead>
				<tr>
					<th class="textLeft">ID</th>
					<th class="textLeft">Name</th>
					<?php foreach ($stats as $stat): ?>
					<th class="textRight"><?=htmlspecialchars(formatStat($stat)); if ($canManage): ?><form style="display: inline;" action="./players/stat/remove" method="post"><button type="submit" name="stat" value="<?=htmlspecialchars($stat)?>" title="Remove" class="decent-remove-button">X</button></form><?php endif; ?></th>
					<?php endforeach; ?>
					<th class="textRight">Last online</th>
				</tr>
            </thead>
			<tbody>
				<?php foreach ($table->getPlayerList() as $player): /** @var \ValheimServerUI\Proto\WebinterfacePlayer $player */ ?>
					<?php
					echo "<tr>
					<td>{$player->getId()}</td>
					<td>{$player->getName()}</td>";
					$playerStats = $player->getStatistics()->getStats();
					foreach ($stats as $stat)
					{
						echo "<td class='textRight'>", $stat === "Position" ? round($player->getPosition()->getX()) . ", " . round($player->getPosition()->getY()) . ", " . round($player->getPosition()->getZ()) : ($playerStats[$stat] ?? 0), "</td>";
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
