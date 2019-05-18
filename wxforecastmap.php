<?php
############################################################################
# A Project of TNET Services, Inc. and Saratoga-Weather.org (Canada/World-ML template set)
############################################################################
#
#   Project:    Sample Included Website Design
#   Module:     sample.php
#   Purpose:    Sample Page
#   Authors:    Kevin W. Reed <kreed@tnet.com>
#               TNET Services, Inc.
#
# 	Copyright:	(c) 1992-2007 Copyright TNET Services, Inc.
############################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA
############################################################################
#	This document uses Tab 4 Settings
############################################################################
require_once("Settings.php");
require_once("common.php");
############################################################################
$TITLE = langtransstr($SITE['organ']) . " - " .langtransstr('NWS Forecast Map');
$showGizmo = true;  // set to false to exclude the gizmo
include("top.php");
############################################################################
?>
<link rel="stylesheet" href="NWS-forecast-map.css" />
<style type="text/css">
<!--
.graytitles {
  font-size:large;
  text-align:center;
  margin-top: 5px;
}
-->
</style>
</head>
<body>
<?php
############################################################################
include("header.php");
############################################################################
include("menubar.php");
############################################################################
?>

<div id="main-copy">

<?php
  $doInclude = true;
	include_once('NWS-forecast-map.php');
	
?>  
    
</div><!-- end main-copy -->

<?php
############################################################################
include("footer.php");
############################################################################
# End of Page
############################################################################
?>