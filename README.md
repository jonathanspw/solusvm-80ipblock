# solusvm-80ipblock
Cron job designed to be run on the SolusVM master.  It will automatically adjust IP block priorities to help you run their usage up to a predefined threshold.

Options:

define('DEFAULT_PRIORITY',0); Default blank priority to be used.  This should probably be 0.  Blocks above the threshold are reset to this.

define('HIGH_PRIORITY',100); priority to be assigned for blocks that should be used first.  Solus pulls first from the block with the highest numbered priority.

define('PERCENT_THRESHOLD',82); Set the percent used threshold that the script should let IP blocks go to.  After reaching this threshold their priority is reset to 0 unless they drop back below the threshold.

define('COUNT_RESERVED_AS_USED',true); Should reserved IPs be counted as used