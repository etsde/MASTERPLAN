<?php
$info = null;
$infoclass = null;
$force = false;

// rights check
if(!isset($currentUser)) die();

$service = null;
$day = null;
$force = 0;
$forceNext = 0;

if(isset($_POST['force'])) {
	$force = $_POST['force'];
}
if(isset($_POST['forceResource']) && isset($_POST['resource'])) {
	if($_POST['forceResource'] != $_POST['resource']) {
		$force = 0;
	}
}
if(isset($_GET['day'])) {
	$day = $_GET['day'];
}
if(isset($_GET['service'])) {
	$service = $db->getService($_GET['service']);
} else {
	die('<div class="infobox red">Dienst nicht gefunden</div>');
}

if(isset($_POST['service']) && isset($_POST['day']) && isset($_POST['resource'])) {
	// check user rights
	if($perm->isUserAdminForRoster($currentUser, $service->roster_id)) {

		$aplan = new autoplan($db);
		if($force >= 1 || $aplan->checkResourceAlreadyAssignedToOtherService($_POST['resource'], $_POST['service'], $_POST['day'])) {

			if($db->createPlannedServiceResource($_POST['day'], $_POST['service'], $_POST['resource'])) {
				echo "<script>self.close()</script>";
				die();
			} else {
				$info = 'Ressource konnte nicht zugeordnet werden: '.$db->getLastStatement()->error;
				$infoclass = 'red';
			}

		} else {
			$info = 'Die Ressource "'.htmlspecialchars($db->getResource($_POST['resource'])->title).'" kann nicht für diesen Dienst eingeteilt werden, da sie bereits zur gleichen Zeit für einen anderen Dienst eingetragen ist. Führen Sie den Vorgang erneut aus, um die Einteilung zu erzwingen.';
			$infoclass = 'yellow';
			$forceNext = 1;
		}

	} else {
		$info = 'Sie besitzen keine Admin-Rechte für diesen Dienstplan';
		$infoclass = 'yellow';
	}
}
?>
<div class="contentbox small">
	<h2>Ressource zuweisen</h2>
	<?php if($info != null) { ?>
		<div class="infobox <?php echo $infoclass; ?>"><?php echo htmlspecialchars($info); ?></div>
	<?php } ?>
	<form method="POST" class="marginbottom">
		<input type="hidden" name="force" value="<?php echo $forceNext; ?>">
		<?php if(isset($_POST['resource'])) { ?>
			<input type="hidden" name="forceResource" value="<?php echo $_POST['resource']; ?>">
		<?php } ?>
		<table>
			<tr>
				<th>Tag:</th>
				<td>
					<input type="hidden" name="day" value="<?php echo htmlspecialchars($day); ?>">
					<input type="text" disabled="true" value="<?php echo htmlspecialchars( strftime(DATE_FORMAT, strtotime($day)) ); ?>">
				</td>
			</tr>
			<tr>
				<th>Dienst:</th>
				<td>
					<input type="hidden" name="service" value="<?php echo $service->id; ?>">
					<input type="text" disabled="true" value="<?php echo htmlspecialchars($service->shortname)." ".htmlspecialchars($service->title); ?>">
				</td>
			</tr>
			<tr>
				<th>Ressource:</th>
				<td>
					<select name="resource" autofocus="true">
						<?php
						$preselectResource = null;
						if(isset($_POST['resource'])) $preselectResource = $_POST['resource'];
						foreach($db->getResources() as $r) {
							echo "<option ".htmlinput::selectIf($r->id,$preselectResource).">".htmlspecialchars($r->type).": ".htmlspecialchars($r->title)."</option>";
						} ?>
					</select>
				</td>
			</tr>
			<tr>
				<th></th>
				<td><button><img src='img/ok.svg'>&nbsp;Zuweisen</button></td>
			</tr>
		</table>
	</form>
</div>
