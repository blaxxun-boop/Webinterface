<?php
/** @var \ValheimServerUI\Proto\PlayerList $table */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
    <div class="page-main">
        <table class="fancyTable sortable">
            <thead>
				<tr>
					<th class="textLeft">ID</th>
					<th class="textLeft">Name</th>
					<th class="textRight">Position</th>
					<!-- <th class="textRight">Kills</th> -->
					<th class="textRight">Deaths</th>
					<th class="textRight">Crafts</th>
					<th class="textRight">Builds</th>
					<th class="textRight">Last online</th>
				</tr>
            </thead>
			<tbody>
				<?php foreach ($table->getPlayerList() as $player): /** @var \ValheimServerUI\Proto\WebinterfacePlayer $player */ ?>
					<?php
					echo "<tr>
					<td>{$player->getId()}</td>
					<td>{$player->getName()}</td>
					<td class='textRight'>", round($player->getPosition()->getX()), ", ", round($player->getPosition()->getY()), ", " , round($player->getPosition()->getZ()), "</td>
					<!-- <td class='textRight'>{$player->getStatistics()->getKills()}</td> -->
					<td class='textRight'>{$player->getStatistics()->getDeaths()}</td>
					<td class='textRight'>{$player->getStatistics()->getCrafts()}</td>
					<td class='textRight'>{$player->getStatistics()->getBuilds()}</td>";
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
