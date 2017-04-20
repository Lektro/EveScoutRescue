<?php 
include_once '../includes/auth-inc.php'; 
require_once '../class/db.class.php';
$db = new Database();
?>
<!DOCTYPE html>
<html>

<head>
	<?php 
	$pgtitle = 'SAR Requests';
	include_once '../includes/head.php'; 
	?>
</head>

<body>
<div class="container">

<div class="row" id="header" style="padding-top: 10px;">
	<?php include_once '../includes/top-left.php'; ?>
	<div class="col-sm-8" style="text-align: center; height: 100px; vertical-align: middle;">
		<br /><span style="font-size: 125%; font-weight: bold; color: white;">
			Search and Rescue Requests</span>
	</div>
	<?php include_once '../includes/top-right.php'; ?>
</div>
<div class="ws"></div>
<?php
// display result for the selected system
if (isset($_REQUEST['reqid']) && !empty($_REQUEST['reqid'])):
	$db->query("SELECT * FROM sar_requests WHERE ID = :reqid");
	$db->bind(':reqid', $_REQUEST['reqid']);
	$row = $db->single();
	
	//only display the following if we got some results back
	if (!empty($row)): ?>
		<div class="row" id="reqtable">
			<div class="col-sm-12">
				<!-- DETAIL RECORD -->
				<table class="table" style="width: auto;">
					<thead>
						<tr>
							<th colspan="4"></th>
							<th colspan="3" style="background-color: #f0f0f0; text-align:center;">
								K-space</th>
							<th colspan="6"></th>
						</tr>
						<tr>
							<th class="white">System</th>
							<th class="white">Pilot</th>
							<th class="white">Status</th>
							<th class="white">Date</th>
							<th class="white">Connecting<br/>System</th>
							<th class="white">Sig</th>
							<th class="white">Sig<br/>Alive?</th>
							<th class="white">Ship</th>
							<th class="white">Probe Launcher?</th>
							<th class="white">Fitting Service?</th>
							<th class="white">Asset Value</th>
							<th class="white">Contacted Others?</th>
							<th class="white">Signal Cartel Rep</th>
						</tr>
					</thead>
					<tbody>
						<?php
						echo '<tr>';
						echo '<td class="white">'. $row['System'] .'</td>';
						echo '<td class="white">'. $row['Pilot'] .'</td>';
						echo '<td class="white">'. $row['Status'] .'</td>';
						echo '<td class="white">'. date("Y-M-d", strtotime($row['RequestDate'])) .'</td>';
						echo '<td class="white">'. $row['KConnect'] .'</td>';
						echo '<td class="white">'. $row['KSig'] .'</td>';
						echo '<td class="white">'. $row['KSigAlive'] .'</td>';
						echo '<td class="white">'. $row['Ship'] .'</td>';
						echo '<td class="white">'. $row['ProbeLauncher'] .'</td>';
						echo '<td class="white">'. $row['FittingService'] .'</td>';
						echo '<td class="white">'. $row['AssetValue'] .'</td>';
						echo '<td class="white">'. $row['ContactedOthers'] .'</td>';
						echo '</tr>';
						$strNotes = $row['Notes'];
						?>
					</tbody>
				</table>
			</div>
		</div>
		<?php 
		if (!empty($strNotes)): ?>
			<div class="ws"></div>
			<div class="row" id="reqnotes">
				<div class="col-sm-12">
					<!-- DETAIL RECORD NOTE(S) -->
					<table class="table" style="width: auto;">
						<thead>
							<tr>
								<th class="white">Notes</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="white"><?php echo $strNotes; ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		<?php 
		endif;
	//no results returned
	else:
		echo '<div class="row">';
		echo '<div class="col-sm-12">';
		echo '<div style="padding-top: 10px;">No results returned.
			  <a href="?" class="btn btn-link" role="button">clear result</a>'; //"clear result" link
		echo '</div></div></div>';
	endif;

// no system selected, so show summary stats
else: ?>
<div class="row" id="allsystable">
	<!-- SAR REQUEST LIST: ALL -->
	<div class="col-sm-12">
		<table class="table" style="width: auto;">
			<thead>
				<tr>
					<th class="white">System</th>
					<th class="white">Pilot</th>
					<th class="white">Status</th>
					<th class="white">Request Date</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$db = new Database();
				$db->query("SELECT * FROM sar_requests ORDER BY RequestDate DESC");
				$rows = $db->resultset();
				
				foreach ($rows as $value) {
					echo '<tr>';
					echo '<td style="background-color: #cccccc;">
						  <a href="?reqid='. $value['ID'] .'">'. $value['System'] .'</a></td>';
					echo '<td class="white">'. $value['Pilot'] .'</td>';
					echo '<td class="white">'. $value['Status'] .'</td>';
					echo '<td class="white">'. date("Y-M-d", strtotime($value['RequestDate'])) .'</td>';
					echo '</tr>';
				}
				?>
			</tbody>
		</table>
	</div>
</div>
<?php 
endif; ?>
</div>
</body>
</html>