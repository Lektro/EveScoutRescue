<?php
function test_input($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}

include_once '../includes/auth-inc.php';
include_once '../class/db.class.php';
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
                remote: '../includes/jsystems.php?query=%QUERY',
				minLength: 3, // send AJAX request only after user types in at least 3 characters
				limit: 8 // limit to show only 8 results
            });
        })
    </script>
</head>
<body>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST")
{ 
	$pilot = $system = $kconnect = $ksig = $ksigalive = $ship = $probelauncher = 
	$fittingsvc = $assetvalue = $contactedothers = $notes = $errmsg = "";
	
	$pilot 			= test_input($_POST["pilot"]);
	$system 		= test_input($_POST["system"]);
	$kconnect 		= test_input($_POST["kconnect"]);
	$ksig 			= test_input($_POST["ksig"]);
	$ksigalive 		= isset($_POST["ksigalive"]) ? test_input($_POST["ksigalive"]) : '';
	$ship 			= test_input($_POST["ship"]);
	$probelauncher 	= isset($_POST["probelauncher"]) ? test_input($_POST["probelauncher"]) : '';
	$fittingsvc 	= isset($_POST["fittingsvc"]) ? test_input($_POST["fittingsvc"]) : '';
	$assetvalue 	= test_input($_POST["assetvalue"]);
	$contactothers 	= isset($_POST["contactothers"]) ? test_input($_POST["contactothers"]) : '';
	$notes 			= test_input($_POST["notes"]);
	
//FORM VALIDATION
	// no system provided
	if (empty($system)) { 
		$errmsg = $errmsg . "You must enter or select a system.\n"; 
	}
//END FORM VALIDATION
	
//DB UPDATES
	if (empty($errmsg)) {
		$db = new Database();
		//check for duplicate entry
		$db->query("SELECT Pilot, System, Status, AssignedRep FROM sar_requests 
					WHERE System = :system AND Pilot = :pilot AND Status <> 'Closed'");
		$db->bind(':system', $system);
		$db->bind(':pilot', $pilot);
		$row = $db->single();
		if (!empty($row)) {
			// we found a duplicate, so prepare feedback to user
			// first, look up the assigned Signal Cartel rep for this rescue
			$rescuerep = '<a href="https://gate.eveonline.com/Profile/Thrice%20Hapus">
							Thrice Hapus</a>';
			if (!empty($row['AssignedRep'])) {
				$rep_url = urlencode($row['AssignedRep']);
				$rescuerep = '<a href="https://gate.eveonline.com/Profile/'. $rep_url.'">'.
							$row['AssignedRep'] .'</a>';
			}
			$errmsg = $errmsg . "Duplicate entry detected. There is already an open search 
								 request for ". $pilot ." in ". $system ."<br />For updated 
								 status, please contact ". $rescuerep;
		}
		else {
			// no duplicate found, so insert to [sar_request] table
			$db->query("INSERT INTO sar_requests (Pilot, System, KConnect, KSig, KSigAlive, 
							Ship, ProbeLauncher, FittingService, AssetValue, ContactedOthers,
							Notes)
						VALUES (:pilot, :system, :kconnect, :ksig, :ksigalive, :ship,
							:probelauncher, :fittingsvc, :assetvalue, :contactothers,
							:notes)");
			$db->bind(':pilot', $pilot);
			$db->bind(':system', $system);
			$db->bind(':kconnect', $kconnect);
			$db->bind(':ksig', $ksig);
			$db->bind(':ksigalive', $ksigalive);
			$db->bind(':ship', $ship);
			$db->bind(':probelauncher', $probelauncher);
			$db->bind(':fittingsvc', $fittingsvc);
			$db->bind(':assetvalue', $assetvalue);
			$db->bind(':contactothers', $contactothers);
			$db->bind(':notes', $notes);
			$db->execute();
			//clear POST values from previous form submission
			$_POST = array();
			$successurl = '<a href="/">Return home.</a>';
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
			<span style="font-size: 125%; font-weight: bold; color: white;">
			Search and Rescue Request</span><br /><br />
			<span class="white"><strong>Complete all fields below. If you do not 
			know an answer or a question does not apply, leave it blank.</strong></span>
		</div>
		<?php include_once '../includes/top-right.php'; ?>
	</div>
	<div class="ws"></div>
	
	<?php
	//display error message div if there is one to show
	if (!empty($errmsg)): ?>
		<div class="row" id="errormessage" style="background-color: #ff9999;">
			<div class="col-sm-12 message">
				<?php echo nl2br($errmsg); ?>
			</div>
		</div>
		<div class="ws"></div>
	<?php
	else:
		//display success message div if there is one to show
		if (!empty($successurl)): ?>
			<div class="row" id="successmessage" style="background-color: #ccffcc;">
				<div class="col-sm-12 message">
					Request entered successfully! An EvE-Scout Rescue representative will 
					connect with you via EVEMail within 24 hours. <?php echo $successurl; ?>
				</div>
			</div>
			<div class="ws"></div>
		<?php
		endif;
	endif;
	
	//hide form after successful entry
	if (empty($successurl)): ?>
		<form name="esrc" id="sar" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" 
			method='post' enctype='multipart/form-data'>
		<div class="row">
			<div class="sechead sower col-sm-12">
				Stranded Pilot: 
				<strong><?php echo isset($charname) ? $charname : 'charname_not_set' ?></strong>
				<input type="hidden" name="pilot" 
					value="<?php echo isset($charname) ? $charname : 'charname_not_set' ?>" />
			</div>
			<div class="sowerlight col-sm-12">
				<div class="row">
					<div class="sowerlight col-sm-4">
						<div class="form-group">
							<label class="control-label" for="system">
								System<span class="descr">Must be in format J######, 
								where # is any number.</span>
							</label>
							<input type="text" tabindex="1" name="system" size="30" 
								autoFocus="autoFocus" class="system" autocomplete="off" 
								placeholder="J######" 
								value="<?php echo isset($_POST['system']) ? $_POST['system']: '' ?>">
						</div>
						<div class="field">
							<label class="control-label" for="kconnect">
								K-space<span class="descr">What area in k-space does your 
								wormhole connect to?</span>
							</label>
							<input type="text" tabindex="4" class="form-control " 
								id="kconnect" name="kconnect" 
								value="<?php echo isset($_POST['kconnect']) ? htmlspecialchars($_POST['kconnect']) : '' ?>" />
						</div>
						<div class="field">
							<?php 
							$checkedY = (isset($_POST['probelauncher']) && $_POST['probelauncher'] == 'Y') ? ' checked="checked"' : ''; 
							$checkedN = (isset($_POST['probelauncher']) && $_POST['probelauncher'] == 'N') ? ' checked="checked"' : '';
							?>
							<label class="control-label" for="probelauncher">
								Probe Launcher?<span class="descr">Does your ship have 
								one fitted?</span>
							</label>
							<div class="radio-inline">
								<label for="probelauncher_1">
									<input id="probelauncher_1" tabindex="8" name="probelauncher" 
										type="radio" value="Y"<?php echo $checkedY; ?>>Yes
								</label>
							</div>
							<div class="radio-inline">
								<label for="probelauncher_2">
									<input id="probelauncher_2" tabindex="9" name="probelauncher" 
										type="radio" value="N"<?php echo $checkedN; ?>>No
								</label>
							</div>
						</div>
					</div>
					<div class="sowerlight col-sm-4">
						<div class="field">
							<label class="control-label" for="ship">
								Ship<span class="descr">Enter your ship type.</span>
							</label>
							<input type="text" tabindex="2" class="form-control " id="ship" 
								name="ship" 
								value="<?php echo isset($_POST['ship']) ? htmlspecialchars($_POST['ship']) : '' ?>" />
						</div>
						<div class="field">
							<label class="control-label" for="ksig">
								K-space Connection Signature ID
							</label>
							<input type="text" tabindex="5" class="form-control " id="ksig" 
								name="ksig" 
								value="<?php echo isset($_POST['ksig']) ? htmlspecialchars($_POST['ksig']) : '' ?>" />
						</div>
						<div class="field">
							<?php 
							$checkedY = (isset($_POST['fittingsvc']) && $_POST['fittingsvc'] == 'Y') ? ' checked="checked"' : ''; 
							$checkedN = (isset($_POST['fittingsvc']) && $_POST['fittingsvc'] == 'N') ? ' checked="checked"' : '';
							?>
							<label class="control-label" for="fittingsvc">
								Fitting Service?<span class="descr">Do you have a mobile depot, 
								or any other way to fit a probe launcher, in the wormhole with you?</span>
							</label>
							<div class="radio-inline">
								<label for="fittingsvc_1">
									<input id="fittingsvc_1" tabindex="10" name="fittingsvc" 
										type="radio" value="Y"<?php echo $checkedY; ?>>Yes
								</label>
							</div>
							<div class="radio-inline">
								<label for="fittingsvc_2">
									<input id="fittingsvc_2" tabindex="11" name="fittingsvc" 
										type="radio" value="N"<?php echo $checkedN; ?>>No
								</label>
							</div>
						</div>
					</div>
					<div class="sowerlight col-sm-4">
						<div class="field">
							<label class="control-label" for="assetvalue">
								Asset Value<span class="descr">Approximate sell value 
								of your ship and implants.</span>
							</label>
							<input type="text" tabindex="3" class="form-control " id="assetvalue" 
								name="assetvalue" 
								value="<?php echo isset($_POST['assetvalue']) ? htmlspecialchars($_POST['assetvalue']) : '' ?>" />
						</div>
						<div class="field">
							<?php 
							$checkedY = (isset($_POST['ksigalive']) && $_POST['ksigalive'] == 'Y') ? ' checked="checked"' : ''; 
							$checkedN = (isset($_POST['ksigalive']) && $_POST['ksigalive'] == 'N') ? ' checked="checked"' : '';
							?>
							<label class="control-label" for="ksigalive">K-space Sig Alive?</label>
							<div class="radio-inline">
								<label for="ksigalive_1">
									<input id="ksigalive_1" tabindex="6" name="ksigalive" 
										type="radio" value="Y"<?php echo $checkedY; ?>>Yes
								</label>
							</div>
							<div class="radio-inline">
								<label for="ksigalive_2">
									<input id="ksigalive_2" tabindex="7" name="ksigalive" 
										type="radio" value="N"<?php echo $checkedN; ?>>No
								</label>
							</div>
						</div>
						<div class="field">
							<?php 
							$checkedY = (isset($_POST['contactothers']) && $_POST['contactothers'] == 'Y') ? ' checked="checked"' : ''; 
							$checkedN = (isset($_POST['contactothers']) && $_POST['contactothers'] == 'N') ? ' checked="checked"' : '';
							?>
							<label class="control-label" for="contactothers">
								Contacted Others?<span class="descr">Have you made contact with 
								others in the wormhole?</span>
							</label>
							<div class="radio-inline">
								<label for="contactothers_1">
									<input id="contactothers_1" tabindex="12" name="contactothers" 
										type="radio" value="Y"<?php echo $checkedY; ?>>Yes
								</label>
							</div>
							<div class="radio-inline">
								<label for="contactothers_2">
									<input id="contactothers_2" tabindex="13" name="contactothers" 
										type="radio" value="N"<?php echo $checkedN; ?>>No
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-12"  style="padding-top: 10px;">
				<label class="control-label white" for="notes">
					Notes<span class="descr white">Is there any other important information 
					we need to know in order to serve you better?</span>
				</label>
				<textarea class="form-control" id="notes" tabindex="14" name="notes" rows="3">
					<?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?>
				</textarea>
			</div>
			<div class="form-actions col-sm-12" style="padding-top: 10px;">
			    <button type="submit" class="btn btn-lg" tabindex="15">Submit</button>
			</div>
		</div>
		</form>
	<?php 
	endif; ?>
</div>
</body>
</html>