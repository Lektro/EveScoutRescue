<?php
function test_input($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}

include_once '../includes/auth-inc.php';
include_once '../class/db.class.php';

$locopts = array('See Notes','Star','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV','XV','XVI','XVII','XVIII','XIX','XX');
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php
$pgtitle = 'SAR Request';
include_once '../includes/head.php'; 
?>
	<script>
        $(document).ready(function() {
            $('input.system').typeahead({
                name: 'system',
                remote: '../esrc/jsystems.php?query=%QUERY',
				minLength: 3, // send AJAX request only after user type in at least 3 characters
				limit: 8 // limit to show only 8 results
            });
        })
    </script>
</head>
<body>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST")
{ 
	$pilot = $system_sower = $system_tender = $system_adjunct = $location = $alignedwith = $distance = $password = $status = $aidedpilot = $notes = $errmsg = $entrytype = $noteDate = $successmsg = $success_url = "";
	
	$pilot = test_input($_POST["pilot"]);
	$system_sower = test_input($_POST["system_sower"]);
	$system_tender = test_input($_POST["system_tender"]);
	$system_adjunct = test_input($_POST["system_adjunct"]);
	$location = test_input($_POST["location"]);
	$alignedwith = test_input($_POST["alignedwith"]);
	$distance = test_input($_POST["distance"]);
	$password = test_input($_POST["password"]);
	$status = isset($_POST["status"]) ? test_input($_POST["status"]) : '';
	$aidedpilot = test_input($_POST["aidedpilot"]);
	$notes = test_input($_POST["notes"]);
	
//FORM VALIDATION
	//see what kind of entry this is
	// NO system provided
	if (empty($system_sower) && empty($system_tender) && empty($system_adjunct)) { 
		$errmsg = $errmsg . "You must enter or select a system.\n"; 
	}
	// SOWER entry
	elseif (!empty($system_sower) && empty($system_tender) && empty($system_adjunct)) { 
		$entrytype = 'sower';
		
		if (empty($location) || empty($alignedwith) || empty($distance) || empty($password)) {
			$errmsg = $errmsg . "All fields in section 'SOWER' must be completed.\n";
		}
		
		if (preg_match('/\b[J][0-9]{6}\b/', $system_sower) != 1) { $errmsg = $errmsg . "System must be in the format: J######, where # is any number.\n"; }
		
		if (22000 >= (int)$distance || (int)$distance >= 50000) { $errmsg = $errmsg . "Distance must be a number between 22000 and 50000.\n"; }
	}
	// TENDER entry
	elseif (empty($system_sower) && !empty($system_tender) && empty($system_adjunct)) { // more than one system provided
		$entrytype = 'tender';
		
		if (empty($status)) { $errmsg = $errmsg . "You must indicate the status of the cache you are tending.\n"; }
	}
	// ADJUNCT entry
	elseif (empty($system_sower) && empty($system_tender) && !empty($system_adjunct)) { // more than one system provided
		$entrytype = 'adjunct';
		
		if (empty($aidedpilot)) { $errmsg = $errmsg . "You must indicate the name of the capsuleer who required rescue.\n"; }
	}
	// more than one system provided
	else { 
		$errmsg = $errmsg . "You must enter or select only one system.\n";
	}
//END FORM VALIDATION
	
//DB UPDATES
	if (empty($errmsg)) {
		$db = new Database();
		//begin db transaction
		$db->beginTransaction();
		// insert to [activity] table
		$db->query("INSERT INTO activity (Pilot, EntryType, System, AidedPilot, Note, IP) VALUES (:pilot, :entrytype, :system, :aidedpilot, :note, :ip)");
		$db->bind(':pilot', $pilot);
		$db->bind(':entrytype', $entrytype);
		$db->bind(':system', ${"system_$entrytype"});
		$db->bind(':aidedpilot', $aidedpilot);
		$db->bind(':note', $notes);
		$db->bind(':ip', $_SERVER['REMOTE_ADDR']);
		$db->execute();
		//get ID from newly inserted [activity] record to use in [cache] record insert/update below
		$newID = $db->lastInsertId();
		//end db transaction
		$db->endTransaction();
		$noteDate = '[' . date("Y-M-d", strtotime("now")) . '] ';
		$sqlRollback = "DELETE FROM activity WHERE ID = " . $newID; // in case we need to roll this back
		//handle each sort of entrytype
		switch ($entrytype) {
			// SOWER
			case 'sower':
				//1. check to make sure system name entered is a valid wormhole system
				$db->query("SELECT System FROM wh_systems WHERE System = :system");
				$db->bind(':system', $system_sower);
				$row = $db->single();
				if (empty($row)) {
					$errmsg = $errmsg . "Invalid wormhole system name entered. Please correct name and resubmit.";
					$_POST['system_sower'] = '';
					//roll back [activity] table commit
					$db->query($sqlRollback);
					$db->execute();
				}
				else {
					//2. check for duplicates - there can only be one non-expired cache per system
					$db->query("SELECT System FROM cache WHERE System = :system AND Status <> 'Expired'");
					$db->bind(':system', $system_sower);
					$row = $db->single();
					if (!empty($row)) {
						$errmsg = $errmsg . "Duplicate entry detected. Please tend existing cache before entering a new one for this system.";
						//roll back [activity] table commit
						$db->query($sqlRollback);
						$db->execute();
					}
					else {
						//3. check for "Do Not Sow" systems
						//   - when wormhole residents ask us not to sow caches in their
						//     holes, we agree to suspend doing so for three months
						$db->query("SELECT System, DoNotSowUntil FROM wh_systems WHERE System = :system AND DoNotSowUntil > CURDATE()");
						$db->bind(':system', $system_sower);
						$row = $db->single();
						if (!empty($row)) {
							$dateNoSow = date("Y-M-d", strtotime($row['DoNotSowUntil']));
							$errmsg = $errmsg . "Upon request of the current wormhole residents, caches are not to be sown in this system until ".$dateNoSow;
							//roll back [activity] table commit
							$db->query($sqlRollback);
							$db->execute();
						}
						else {
							//4. system name is valid and not a duplicate, so go ahead and insert
							$sower_note = $noteDate . 'Sown by '. $pilot;
							if (!empty($notes)) { $sower_note = $sower_note . '<br />' . $notes; }
							
							$db->query("INSERT INTO cache (CacheID, InitialSeedDate, System, Location, AlignedWith, Distance, Password, Status, ExpiresOn, Note) VALUES (:cacheid, :sowdate, :system, :location, :aw, :distance, :pw, :status, :expdate, :note)");
							$db->bind(':cacheid', $newID);
							$db->bind(':sowdate', date("Y-m-d H:i:s", strtotime("now")));
							$db->bind(':system', $system_sower);
							$db->bind(':location', $location);
							$db->bind(':aw', $alignedwith);
							$db->bind(':distance', $distance);
							$db->bind(':pw', $password);
							$db->bind(':status', 'Healthy');
							$db->bind(':expdate', date("Y-m-d H:i:s", strtotime("+30 days",time())));
							$db->bind(':note', $sower_note);
							$db->execute();
							//for user feedback message 
							$successcolor = '#ccffcc';
						}
					}
				}
				break;
			
			// TENDER
			case 'tender':
				//1. check to make sure system name entered is an eligible wormhole system - one with an active (non-expired) cache
				$db->query("SELECT System FROM cache WHERE System = :system AND Status <> 'Expired'");
				$db->bind(':system', $system_tender);
				$row = $db->single();
				if (empty($row)) {
					$errmsg = $errmsg . "Invalid wormhole system name entered. Please correct name and resubmit.";
					$_POST['system_tender'] = '';
					//roll back [activity] table commit
					$db->query($sqlRollback);
					$db->execute();
				}
				else {
					//2. system name is valid, so go ahead and insert
					$tender_note = '<br />' . $noteDate . 'Tended by '. $pilot;
					if (!empty($notes)) { $tender_note = $tender_note . '<br />' . $notes; }
					//handle each tender option
					switch ($status) {
						case 'Healthy':
							$db->query("UPDATE cache SET ExpiresOn = :expdate, Status = :status, Note = CONCAT(Note, :note) WHERE System = :system AND Status <> 'Expired'");
							$db->bind(':expdate', date("Y-m-d H:i:s", strtotime("+30 days",time())));
							$db->bind(':status', 'Healthy');
							$db->bind(':note', $tender_note);
							$db->bind(':system', $system_tender);
							$db->execute();
							break;
						case 'Upkeep Required':
							$db->query("UPDATE cache SET ExpiresOn = :expdate, Status = :status, Note = CONCAT(Note, :note) WHERE System = :system AND Status <> 'Expired'");
							$db->bind(':expdate', date("Y-m-d H:i:s", strtotime("+30 days",time())));
							$db->bind(':status', 'Upkeep Required');
							$db->bind(':note', $tender_note);
							$db->bind(':system', $system_tender);
							$db->execute();
							break;
						case 'Expired':
							//FYI: daily process to update expired caches in [cache] is running via cron-job.org
							$db->query("UPDATE cache SET Status = :status, Note = CONCAT(Note, :note) WHERE System = :system AND Status <> 'Expired'");
							$db->bind(':status', 'Expired');
							$db->bind(':note', $tender_note);
							$db->bind(':system', $system_tender);
							$db->execute();
							break;
					}
					//for user feedback message
					$successcolor = '#d1dffa';
				}
				break;
			
			// ADJUNCT
			case 'adjunct':
				//1. check to make sure system name entered is an eligible wormhole system - one with an active (non-expired) cache
				$db->query("SELECT System FROM cache WHERE System = :system AND Status <> 'Expired'");
				$db->bind(':system', $system_adjunct);
				$row = $db->single();
				if (empty($row)) {
					$errmsg = $errmsg . "Invalid wormhole system name entered. Please correct name and resubmit.";
					$_POST['system_adjunct'] = '';
					//roll back [activity] table commit
					$db->query($sqlRollback);
					$db->execute();
				}
				else {
					//2. system name is valid, so go ahead and insert
					$adj_note = '<br />' . $noteDate . 'Adjunct: '. $pilot . '; Aided: ' . $aidedpilot;
					if (!empty($notes)) { $adj_note = $adj_note . '<br />' . $notes; }
					
					$db->query("UPDATE cache SET Note = CONCAT(Note, :note) WHERE System = :system AND Status <> 'Expired'");
					$db->bind(':note', $adj_note);
					$db->bind(':system', $system_adjunct);
					$db->execute();
					//for user feedback message
					$successcolor = '#fffacd';
				}
				break;
		} //switch ($entrytype)
		
		//all good, so prepare success message(s) and clear previously submitted form values
		if (empty($errmsg)) {
			if (isset(${"system_$entrytype"})) {
				$success_url = '<a href="search.php?system='. ${"system_$entrytype"} .'">Confirm data entry</a> or <a href="'. htmlspecialchars($_SERVER['PHP_SELF']) .'">enter another one.</a>';
			}
			else {
				$success_url = '<a href="'. htmlspecialchars($_SERVER['PHP_SELF']) .'">Enter another one.</a>';
			}
			//clear POST values from previous form submission
			$_POST = array();
		}
	}
//END DB UPDATES
}
?>
<div class="container">
	<div class="ws"></div>
	<div class="row" id="formtop">
		<?php include_once '../includes/top-left.php'; ?>
		<div class="col-sm-8" style="text-align: center;">
			<span style="font-size: 125%; font-weight: bold; color: white;">Search and Rescue Request</span>
		</div>
		<?php include_once '../includes/top-right.php'; ?>
	</div>
	<div class="ws"></div>
	<div class="row" id="formtop2">
		<div class="col-sm-12" style="text-align: center;">
			<span class="white"><strong>Complete all fields below. If you do not know an answer or a question does not apply, leave it blank.</strong></span>
		</div>
	</div>
	
	<div class="ws"></div>
	<?php
	//display error message div if there is one to show
	if (!empty($errmsg)):
	?>
	<div class="row" id="errormessage" style="background-color: #ff9999;">
		<div class="col-sm-12 message">
			<?php echo nl2br($errmsg); ?>
		</div>
	</div>
	<div class="ws"></div>
	<?php
	else:
		//display success message div if there is one to show
		if (!empty($success_url)):
		?>
		<div class="row" id="successmessage" style="background-color: <?php echo $successcolor;?>">
			<div class="col-sm-12 message">
				<?php echo strtoupper($entrytype) . ' record entered successfully! ' . $success_url; ?>
			</div>
		</div>
		<div class="ws"></div>
		<?php
		endif;
	endif;
	?>
	<div class="row" id="formmain">
		<form name="esrc" id="sar" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method='post' enctype='multipart/form-data'>
		<div class="col-sm-12">
			<div class="sechead sower">Stranded Pilot: <strong><?php echo isset($charname) ? $charname : 'charname_not_set' ?></strong></div>
			<input type="hidden" name="pilot" value="<?php echo isset($charname) ? $charname : 'charname_not_set' ?>" />
			<div class="sowerlight col-sm-4">
				<?php
				if (isset($_POST['system'])) { 
					$system = htmlspecialchars($_POST['system']); 
				}
				?>
				<div class="form-group">
					<label class="control-label" for="system_sower">System<span class="descr">Must be in format J######, where # is any number.</span></label>
					<input type="text" tabindex="1" name="system" size="30" class="system" autocomplete="off" placeholder="J######" value="<?php echo isset($system) ? $system: '' ?>">
				</div>
				<div class="field">
					<label class="control-label" for="kconnect">K-space<span class="descr">What area in k-space does your wormhole connect to?</span></label>
					<input type="text" tabindex="4" class="form-control " id="kconnect" name="kconnect" value="<?php echo isset($_POST['kconnect']) ? htmlspecialchars($_POST['kconnect']) : '' ?>" />
				</div>
			</div>
			<div class="sowerlight col-sm-4">
				<div class="field">
					<label class="control-label" for="ship">Ship<span class="descr">Enter your ship type.</span></label>
					<input type="text" tabindex="2" class="form-control " id="ship" name="ship" value="<?php echo isset($_POST['ship']) ? htmlspecialchars($_POST['ship']) : '' ?>" />
				</div>
				<div class="field">
					<label class="control-label" for="ksig">K-space Connection Signature ID</label>
					<input type="text" tabindex="5" class="form-control " id="ksig" name="ksig" value="<?php echo isset($_POST['ksig']) ? htmlspecialchars($_POST['ksig']) : '' ?>" />
				</div>
			</div>
			<div class="sowerlight col-sm-4">
				<div class="field">
					<label class="control-label" for="assetvalue">Asset Value<span class="descr">Approximate sell value of your ship and implants.</span></label>
					<input type="text" tabindex="3" class="form-control " id="assetvalue" name="assetvalue" value="<?php echo isset($_POST['assetvalue']) ? htmlspecialchars($_POST['assetvalue']) : '' ?>" />
				</div>
				<div class="field">	
					<label class="control-label" for="ksigalive">K-space Sig Alive?</label>
					<div class="radio-inline">
						<label for="ksigalive_1"><input id="ksigalive_1" tabindex="6" name="ksigalive" type="radio" value="1">Yes</label>
					</div>
					<div class="radio-inline">
						<label for="ksigalive_2"><input id="ksigalive_2" tabindex="7" name="ksigalive" type="radio" value="0">No</label>
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-12"  style="padding-top: 10px;">
			<label class="control-label white" for="notes">Notes<span class="descr white">Is there any other important information we need to know?</span></label>
			<textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
		</div>
		<div class="form-actions col-sm-12" style="padding-top: 10px;">
		    <button type="submit" class="btn btn-lg">Submit</button>
		</div>
		</form>
	</div>
</div>
</body>
</html>