<?php
/**
 * @var \ValheimServerUI\Proto\ModList $table
 * @var bool $canManage
 */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<script src="js/ace.js" type="text/javascript" charset="utf-8"></script>
<script src="js/mode-plain_text.js" type="text/javascript" charset="utf-8"></script>
<script src="js/mode-json.js" type="text/javascript" charset="utf-8"></script>
<script src="js/mode-yaml.js" type="text/javascript" charset="utf-8"></script>
<script src="js/theme-textmate.js" type="text/javascript" charset="utf-8"></script>

<div id="main" role="main">
    <div class="page-main">
        <table class="fancyTable sortable">
            <thead>
				<tr>
					<th class="textLeft">GUID</th>
					<th class="textLeft">Name</th>
					<th class="textRight">Version</th>
					<th class="textRight">Last Update</th>
					<th class="textLeft">Mod File</th>
					<th class="textLeft">Config File</th>
				</tr>
            </thead>
			<tbody>
				<?php foreach ($table->getModList() as $mod): /** @var \ValheimServerUI\Proto\WebinterfaceMod $mod */ ?>
					<?php
					echo "<tr data-modid='{$mod->getGuid()}'>
					<td class='textLeft'>{$mod->getGuid()}</td>
					<td class='textLeft mods_modname'>{$mod->getName()}</td>
					<td class='textRight'>{$mod->getVersion()}</td>
					<td class='textRight' data-num='{$mod->getLastUpdate()}'>", date("Y-m-d H:i", $mod->getLastUpdate()), "</td>
					<td class='textLeft'>{$mod->getModPath()}</td>
					<td class='textLeft mods_configpath'>{$mod->getConfigPath()}", $canManage ? " <img src='img/editicon.svg' width='8' height='8' alt='Edit config' title='Edit config' style='cursor: pointer' onclick='configEditorDialog(\"{$mod->getGuid()}\")'>" : "", "</td>
					</tr>";
					?>
				<?php endforeach; ?>
			</tbody>
        </table>
    </div>
</div>

<div id="dialogEditConfig" style="display: none">
	<h1>Editing config of <span id="dialogEditConfigName"></span></h1>
	<div id="dialogEditor"></div>
	<input type="button" value="Save" name="saveConfig" id="saveConfig" onclick="configEditorDialogSave()">
</div>

<div id="dialogEditorConfigDone" style="display: none">
	Successfully updated config for mod <span id="dialogEditorConfigDoneName"></span>.
</div>


<style>
	#dialogEditor {
		position: relative;
		width: 75vw;
		min-width: 200px;
		max-width: calc(100vw - 100px);
		height: 80vh;
		min-height: 100px;
		max-height: calc(100vh - 180px);
	}

	#globalOverlay[data-activeDialog="dialogEditConfig"] #globalDialogContainer { margin: 50px auto; }
</style>

<script>
	var editor = ace.edit(document.getElementById('dialogEditor'));
	editor.setTheme("ace/theme/textmate");

	var editedModconfig;
	function configEditorDialog(guid) {
		editedModconfig = guid;
		var name = document.querySelector("tr[data-modid='" + guid + "'] .mods_modname").textContent;
		var configpath = document.querySelector("tr[data-modid='" + guid + "'] .mods_configpath").textContent;
		displayGlobalLoadingDialog("Loading config file for mod " + name + " ...");
		fetchOrErrorDialog("ConfigFile/" + guid, {}, "Got error while loading config file for mod " + name, data => {
			if (configpath.endsWith(".json")) {
				editor.session.setMode("ace/mode/json");
			} else if (configpath.endsWith(".yml") || configpath.endsWith(".yaml")) {
				editor.session.setMode("ace/mode/yaml");
			} else {
				editor.session.setMode("ace/mode/plain_text")
			}
			displayGlobalOverlay("dialogEditConfig");
			document.getElementById("dialogEditConfigName").innerText = name;
			editor.session.setValue(data);
		}, true);
	}

	function configEditorDialogSave() {
		var name = document.querySelector("tr[data-modid='" + editedModconfig + "'] .mods_modname").textContent;
		displayGlobalLoadingDialog("Updating config for mod " + name + " ...");
		var form = new FormData();
		form.append("content", editor.getValue());
		fetchOrErrorDialog("ConfigFile/" + editedModconfig, { method: "PUT", body: form }, "Got error while updating config for mod " + name, data => {
			displayGlobalOverlay("dialogEditorConfigDone");
			document.getElementById("dialogEditorConfigDoneName").innerText = name;
		});
	}
</script>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>
