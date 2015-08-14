<?php

/**
 * This script is intended to be run as a cron-job on a solusvm master.  It will run IP blocks up to a certain percentage threshold at which point it will re-prioritize the blocks.
 *
 * @author Jonathan Wright
 * @website https://www.knownhost.com
 * @email jonathan@effecthost.com
 * @version 1.0
 *
 */

// start config
	// Default blank priority to be used.  This should probably be 0.  Blocks above the threshold are reset to this.
	define('DEFAULT_PRIORITY',0);
	// priority to be assigned for blocks that should be used first.  Solus pulls first from the block with the highest numbered priority.
	define('HIGH_PRIORITY',100);
	// Set the percent used threshold that the script should let IP blocks go to.  After reaching this threshold their priority is reset to 0 unless they drop back below the threshold.
	define('PERCENT_THRESHOLD',82);
	// Should reserved IPs be counted as used
	define('COUNT_RESERVED_AS_USED',true);
// end config
// do not modify below this line

// lets parse solus's config file so we know we have proper db connection info
$solusinfo = fread(fopen('/usr/local/solusvm/includes/solusvm.conf',"r"),filesize('/usr/local/solusvm/includes/solusvm.conf'));
$solusinfo = explode(":",$solusinfo);

// If we didn't get a 4-item array from the above info we cannot connect to mysql
if(count($solusinfo) != 5)
	die('Unable to fetch SolusVM database connection info from SolusVM config file.');

// convert into named vars for readability
$solusdbname = $solusinfo[0];
$solusdbuser = $solusinfo[1];
$solusdbpass = $solusinfo[2];
$solusdbhost = $solusinfo[3];

$db = new mysqli($solusdbhost, $solusdbuser, $solusdbpass, $solusdbname);

if($db->connect_errno > 0){
	die('Unable to connect to SolusVM database [' . $db->connect_error . ']' . "\n");
}

/**
 * Fetches the blockid and name of all IP blocks
 * @return array blockid=>name
 */
function get_ipblocks($db){
	$sql = "select `blockid`,`name` from ipblocks";
	$result = $db->query($sql);
	$ipblocks = array();
	while($row = $result->fetch_assoc()){
		$ipblocks[$row['blockid']] = $row['name'];
	}

	return $ipblocks;
}

/**
 * Gets the percentage used of a block
 * @param $db
 * @param $blockid
 * @return int percentage of block used
 */
function block_used_percent($db,$blockid){
	// Lets find out how many IPs are in the block.  Not everyone has full /24s.
	$sql = "select * from `ipaddresses` where `blockid`='$blockid'";
	$total_ips = $db->query($sql)->num_rows;
	$sql = null;

	if(COUNT_RESERVED_AS_USED == true) {
		$sql = "select `ipaddressid` from `ipaddresses` where `blockid`='$blockid' and (`reserved` = 1 or `vserverid`!=0)";
	}else{
		$sql = "select `ipaddressid` from `ipaddresses` where `blockid`='$blockid' and `vserverid`!=0";
	}

	$used_ips = $db->query($sql)->num_rows;

	return round(($used_ips / $total_ips)*100,1);

}

/**
 * Simply checks of the used percent is greater than the threshold so we can know how to handle the block
 * @param $used_percent
 * @return bool true if above threshold, false if below
 */
function threshold_check($used_percent){
	if($used_percent > PERCENT_THRESHOLD)
		return true;
	return false;
}

/**
 * Updates the priority set on the IP block
 * @param $db
 * @param $blockid
 * @param $deploy_order
 */
function set_block_priority($db,$blockid,$deploy_order){
	$sql = "update `ipblocks` set `deploy_order`='$deploy_order' where `blockid`='$blockid'";
	$db->query($sql);
}

$ipblocks = get_ipblocks($db);
foreach($ipblocks as $blockid=>$name){
	if(threshold_check(block_used_percent($db,$blockid))){
		set_block_priority($db,$blockid,DEFAULT_PRIORITY);
	}else{
		set_block_priority($db,$blockid,HIGH_PRIORITY);
	}
}