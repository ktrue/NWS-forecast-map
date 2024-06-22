<?php
#################################################################################
#
#  NWS Weather Forecast with Leaflet Map
#
#  This program is based on WxForecastMap.php by Curly of ricksturf.com, and has
#  been completely rewritten to use Leaflet/OpenStreetMaps and api.weather.gov
#  for the data (instead of Google map/forecast.weather.gov/MapClick.php XML)
#  by Ken True - webmaster@saratoga-weather.org
#
#  NWS-forecast-map.php - 18-May-2019 - initial release
# 
#  Version 2.00 - 18-May-2019 - initial release
#  Version 2.10 - 19-May-2019 - added display of County Zone alerts and map polygons (where provided)
#  Version 2.20 - 14-Jul-2020 - switched to geo+json from ld+json calls to api.weather.gov
#  Version 2.30 - 20-Oct-2022 - added adjustment of -1,-1 to gridpoint forecast for problem WFOs
#  Version 2.40 - 21-Jun-2024 - update for change in icon URLs in api.weather.gov JSON + alert links
#  Version 2.41 - 22-Jun-2024 - fix for missing global for $NWSICONLIST+colors for polygon displays
#
#################################################################################
#  error_reporting(E_ALL);  // uncomment to turn on full error reporting
#
# script available at http://saratoga-weather.org/scripts.php
#  
# you may copy/modify/use this script as you see fit,
# no warranty is expressed or implied.
# Usage:
#  you can use this webpage standalone (customize the HTML portion below)
#  or you can include it in an existing page:
/*
<?php
  $doInclude = true;
  include("NWS-forecast-map.php");
?> 
*/
#
# settings: --------------------------------------------------------------------
# if you are using www.mapbox.com for map tiles, you
# need to acquire an API ke from that service
#
#  put this in the CALLING page for NWS-forecast-map.php script:
/*
  $setMapboxAPIkey = '-replace-this-with-your-API-key-here-'; 
*/
# Note: if using the Saratoga template set, put a new entry in Settings.php
/*

$SITE['mapboxAPIkey'] = '-replace-this-with-your-API-key-here-';

*/
# and you won't need to change the $mapAPI value above (nor any of the other
# settings in the script below.
# 
#  change myLat, myLong to your station latitude/longitude, 
#  set $ourTZ to your time zone
#    other settings are optional
#
#  set to station latitude/longitude (decimal degrees)
  $myLat = 37.2747;    //North=positive, South=negative decimal degrees
  $myLong = -122.0229;   //East=positive, West=negative decimal degrees
# The above settings are for saratoga-weather.org location
  $ourTZ = "America/Los_Angeles";  //NOTE: this *MUST* be set correctly to
# translate UTC times to your LOCAL time for the displays.
# Use https://www.php.net/manual/en/timezones.php to find the timezone suitable for
#  your location.
#
#  pick a format for the time to display ..uncomment one (or make your own)
# $timeFormat = 'D, Y-m-d H:i:s T';  // Fri, 2006-03-31 14:03:22 TZone
#  $timeFormat = 'D, d-M-Y g:i:s a T';  // Fri, 31-Mar-2006 4:03:22 am TZone
  $timeFormat = 'g:i a T M d, Y';  // 10:30 am CDT March 31, 2018
 
	# see: http://leaflet-extras.github.io/leaflet-providers/preview/ for additional maps
	# select ONE map tile provider by uncommenting the values below.
	
	$mapProvider = 'Esri_WorldTopoMap'; // ESRI topo map - no key needed
	#$mapProvider = 'OSM';     // OpenStreetMap - no key needed
	#$mapProvider = 'Terrain'; // Terrain map by stamen.com - no key needed
	#$mapProvider = 'OpenTopo'; // OpenTopoMap.com - no key needed
	#$mapProvider = 'Wikimedia'; // Wikimedia map - no key needed
# 
	#$mapProvider = 'MapboxSat';  // Maps by Mapbox.com - API KEY needed in $mapboxAPIkey 
	#$mapProvider = 'MapboxTer';  // Maps by Mapbox.com - API KEY needed in $mapboxAPIkey 
	$mapboxAPIkey = '--mapbox-API-key--';  
	# use this for the API key to MapBox
  $mapZoomDefault = 11;  // =11; default Leaflet Map zoom entry for display (1=world, 14=street)
	
# end of settings -------------------------------------------------------------

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain;charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}
// overrides from Settings.php if available
// if(file_exists("Settings.php")) {include_once("Settings.php");}
//if(file_exists("common.php"))   {include_once("common.php");}
global $SITE;
if (isset($SITE['latitude'])) 	     {$myLat = $SITE['latitude'];}
if (isset($SITE['longitude'])) 	     {$myLong = $SITE['longitude'];}
if (isset($SITE['cityname'])) 	     {$ourLocationName = $SITE['cityname'];}
if (isset($SITE['tz']))              {$ourTZ = $SITE['tz']; }
if (isset($SITE['timeFormat']))      {$timeFormat = $SITE['timeFormat'];}
if (isset($SITE['mapboxAPIkey']))    {$mapboxAPIkey = $SITE['mapboxAPIkey']; }
// end of overrides from Settings.php

// overrides from including page if any
if (isset($setMapZoomDefault))  { $mapZoomDefault = $setMapZoomDefault; }
if (isset($setDoLinkTarget))    { $doLinkTarget = $setDoLinkTarget; }
if (isset($setLatitude))        { $myLat = $setLatitude; }
if (isset($setLongitude))       { $myLong = $setLongitude; }
if (isset($setLocationName))    { $ourLocationName = $setLocationName; }
if (isset($setTimeZone))        { $ourTZ = $setTimeZone; }
if (isset($setTimeFormat))      { $timeFormat = $setTimeFormat; }
if (isset($setMapProvider))     { $mapProvider = $setMapProvider; }
if (isset($setMapboxAPIkey))    { $mapboxAPIkey = $setMapboxAPIkey; }
// ------ start of code -------
define('NWS_DETAIL_PRODUCT_URL','https://alerts-v2.weather.gov/#/?id='); // api site still beta // Jasiu fix 10-May-2020
if(!isset($mapboxAPIkey)) {
	$mapboxAPIkey = '--mapbox-API-key--';
}
$includeMode = (isset($doInclude) and $doInclude)?true:false;

# table of available map tile providers
$mapTileProviders = array(
  'OSM' => array( 
	   'name' => 'Street',
	   'URL' =>'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		 'attrib' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Points &copy 2012 LINZ',
		 'maxzoom' => 18
		  ),
  'Wikimedia' => array(
	  'name' => 'Street2',
    'URL' =>'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
	  'attrib' =>  '<a href="https://wikimediafoundation.org/wiki/Maps_Terms_of_Use">Wikimedia</a>',
	  'maxzoom' =>  18
    ),		
  'Esri_WorldTopoMap' =>  array(
	  'name' => 'Terrain',
    'URL' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
	  'attrib' =>  'Tiles &copy; <a href="https://www.esri.com/en-us/home" title="Sources: Esri, DeLorme, NAVTEQ, TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ordnance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User Community">Esri</a>',
	  'maxzoom' =>  18
    ),
	'Terrain' => array(
	   'name' => 'Terrain2',
		 'URL' =>'http://{s}.tile.stamen.com/terrain/{z}/{x}/{y}.jpg',
		 'attrib' => '<a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> <a href="https://stamen.com">Stamen.com</a> | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 14
		  ),
	'OpenTopo' => array(
	   'name' => 'Topo',
		 'URL' =>'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
		 'attrib' => ' &copy; <a href="https://opentopomap.org/">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>) | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 15
		  ),
	'MapboxTer' => array(
	   'name' => 'Terrain3',
		 'URL' =>'https://api.mapbox.com/styles/v1/mapbox/outdoors-v10/tiles/256/{z}/{x}/{y}?access_token='.
		 $mapboxAPIkey,
		 'attrib' => '&copy; <a href="https://mapbox.com">MapBox.com</a> | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 18
		  ),
	'MapboxSat' => array(
	   'name' => 'Satellite',
		 'URL' =>'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v10/tiles/256/{z}/{x}/{y}?access_token='.
		 $mapboxAPIkey,
		 'attrib' => '&copy; <a href="https://mapbox.com">MapBox.com</a> | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 18
		  ),
			
	);


if(!$includeMode) { // emit only if full page is generated
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="viewport" content="initial-scale=1.0" />
<link rel="stylesheet" href="NWS-forecast-map.css" />
<title>NWS Forecast Map</title>
<style type="text/css">
<!--
body,td,th {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 12px;
}
.graytitles {
  font-size:large;
  text-align:center;
  margin-top: 5px;
}
-->
</style>
</head>
<body>
<?php } // end !$includeMode ?>
<div>
<p> </p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/leaflet.js" type="text/javascript"></script>
<?php

$centerlat = $myLat;
$centerlong=  $myLong;
$zoom = $mapZoomDefault;

global $Status;
$errorMessage = '';
 
$Status = "<!-- NWS-forecast-map.php - V2.41 - 22-Jun-2024 -->\n";

if(isset($_REQUEST['zoom']) and is_numeric($_REQUEST['zoom'])) {
	$zoom = $_REQUEST['zoom'];
}
if(isset($_REQUEST['llc'])) {
	list($centerlat,$centerlong) = explode(',',$_REQUEST['llc']);
}

if(isset($_REQUEST['map'])) {
	$reqMap = $_REQUEST['map'];
}
$doDebug = isset($_REQUEST['debug'])?true:false;

# for easy testing (part 1)
if(isset($_REQUEST['latlong'])) {
	$t = $_REQUEST['latlong'];
	if(preg_match('!^([\d\.]+),-([\d\.]+)$!',$t,$m)) {
		$centerlat = $m[1];
		$centerlong= $m[2];
		$centerlong= 0.0-$centerlong;
		$Status .= "<!-- using latlong=$centerlat,$centerlong -->\n";
	}
}
# for easy testing (part 2)
if(isset($_REQUEST['lat']) and is_numeric($_REQUEST['lat']) and
   isset($_REQUEST['lon']) and is_numeric($_REQUEST['lon']) ) {
		 $centerlat = $_REQUEST['lat'];
		 $centerlong= $_REQUEST['lon'];
		 $Status .= "<!-- using lat=$centerlat and long=$centerlong -->\n";
}

# for easy testing (part 3) - fudge the gridpoint number
if(isset($_REQUEST['adj'])) {
	$t = $_REQUEST['adj'];
	if(preg_match('!^([\-\d\.]+),([\-\d\.]+)$!',$t,$m)) {
		$adj1 = $m[1];
		$adj2 = $m[2];
		$Status .= "<!-- using adj=$adj1,$adj2 -->\n";
	}
} else {
	$adj1 = 0;
	$adj2 = 0;
}

# listing of current WFO gridpoint forecasts with +1,+1 offsets to correct  20-Oct-2022
#$offByOne = array('AFC','AER','ALU','AFG','AJK','BOX','CAE','DLH','FSD','HGX','HNX','LIX','LWX','MAF','MFR','MLB','MRX','MTR');
$offByOne = array('NIL'); 
$pointHTML = WXmap_fetchUrlWithoutHanging('https://api.weather.gov/points/'.$centerlat.','.$centerlong);
	$long = round($centerlong,4);
	$lat  = round($centerlat,4);
  $stuff = explode("\r\n\r\n",$pointHTML); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	//$Status .= "<!-- m=".print_r($m,true)." -->\n";
	//$Status .= "<!-- html=".print_r($html,true)." -->\n";
	if(isset($m[1])) {$lastRC = (string)$m[1];} else {$lastRC = '0';}

if($lastRC == '200') { // got a good return .. process
	$rawpointJSON = json_decode($content,true);
	$pointJSON = $rawpointJSON['properties'];
	$gridPointMeta = $pointJSON['gridId'].'/'.$pointJSON['gridX'].','.$pointJSON['gridY'];
	
	if($doDebug) {$Status .= "<!-- Point content\n".var_export($pointJSON,true)." -->\n";}
	
	$fcstURL = $pointJSON['forecast'];
	$ngpID = $pointJSON['gridId'];
	if(in_array($ngpID,$offByOne)) {
		print "<!-- WFO=$ngpID is +1,+1 offset, adjusted down by -1,-1 -->\n";
		$adj1 = -1;
		$adj2 = -1;
	}
	$ngpX  = $pointJSON['gridX']+$adj1;
	$ngpY  = $pointJSON['gridY']+$adj2;
	
	$fcstURL = "https://api.weather.gov/gridpoints/$ngpID/$ngpX,$ngpY/forecast";
	$gridPointMeta = $ngpID.'/'.$ngpX.','.$ngpY;
	           
	$fcstZoneURL = $pointJSON['forecastZone'];
	$fcstZone = get_zone($fcstZoneURL);
	$countyZoneURL = $pointJSON['county'];
	$countyZone = get_zone($countyZoneURL);
	$fireZoneURL = $pointJSON['fireWeatherZone'];
	$fireZone = get_zone($fireZoneURL);
	$cityname = $pointJSON['relativeLocation']['properties']['city'];
	$statename= $pointJSON['relativeLocation']['properties']['state'];
	$TZ       = $pointJSON['timeZone'];
	date_default_timezone_set($TZ);
	$distanceFrom = '';
	if (isset($pointJSON['relativeLocation']['properties']['distance']['value'])) {
		$distance = $pointJSON['relativeLocation']['properties']['distance']['value'];
		$distance = floor(0.000621371 * $distance); // truncate to nearest whole mile
		$Status.= "<!-- distance=$distance from " . $cityname . " -->\n";
		if ($distance >= 2) {
			$angle = $pointJSON['relativeLocation']['properties']['bearing']['value'];
			$compass = array('N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW');
			$direction = $compass[round($angle / 22.5) % 16];
			$t = $distance . ' ';
			$t.= ($distance > 1) ? "Miles" : "Mile";
			$t.= " $direction ";
			$distanceFrom = $t. ' from ';
		}
	}
	
	$Status .=  "<!-- fcstURL='$fcstURL' -->\n";
} else { //not a good return
  $errorMessage .= "<h2>Oops... unable to fetch the point forecast data RC=$lastRC.<br/>" .
	                "Please try again later</h2>";
	$distanceFrom = '';
	$cityname = 'n/a';
	$statename = 'n/a';
	$updateTime= 'n/a';
	$lat=$centerlat;
	$long=$centerlong;
}
// now get the actual forecast from the gridpoint URL

if(isset($fcstURL)) { // try only if the gridpoint URL was found

  $gridpointHTML = WXmap_fetchUrlWithoutHanging($fcstURL);
  $stuff = explode("\r\n\r\n",$gridpointHTML); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	//$Status .= "<!-- m=".print_r($m,true)." -->\n";
	//$Status .= "<!-- html=".print_r($html,true)." -->\n";
	if(isset($m[1])) {$lastRC = (string)$m[1]; } else { $lastRC = '0'; }
  if($lastRC == '200') {
	
		$rawgridpointJSON = json_decode($content,true);
		$gridpointJSON = $rawgridpointJSON['properties'];
	  if($doDebug) {$Status .= "<!-- gridpoint content\n".var_export($gridpointJSON,true)." -->\n";}
		$updateTime = date($timeFormat,strtotime($gridpointJSON["updateTime"]));
/* ld+json extract	
		if(isset($gridpointJSON['geometry'])) {
			$g = $gridpointJSON['geometry'];
		# "geometry": "GEOMETRYCOLLECTION(POINT(-122.0220167 37.2668315),POLYGON((-122.0385572 37.275548,-122.0330058 37.2536879,-122.005479 37.2581132,-122.011025 37.2799738,-122.0385572 37.275548)))",
			if(preg_match('|POINT\(([^\)]+)\)|i',$g,$m)) {
				list($long,$lat) = explode(' ',$m[1]);
				$long = round($long,4);
				$lat  = round($lat,4);
				$centerlat = $lat;
				$centerlong = $long;
				
			}
			if(preg_match('|POLYGON\(\(([^\)]+)\)\)|i',$g,$m)) {
				$poly = explode(',',$m[1]);
			}
*/			
		}

	$poly = array();
  if(isset($rawgridpointJSON['geometry']['coordinates'][0])) {
	  if($doDebug) {$Status .= "<!-- gridpoint geometry\n".var_export($rawgridpointJSON['geometry']['coordinates'][0],true)." -->\n";}
		foreach ($rawgridpointJSON['geometry']['coordinates'][0] as $i => $coords) {
			$poly[] = $coords[0].' '.$coords[1];
		}
		
		if($doDebug) {$Status .= "<!-- lat=$lat long=$long poly=".var_export($poly,true)." -->\n"; }
	} else {

		$errorMessage .= "<h2>Oops... unable to fetch the gridpoint forecast data RC=$lastRC.<br/>" .
										"Please try again later</h2>";
		$updateTime= 'n/a';
		$lat=$centerlat;
		$long=$centerlong;
	}
}

list($alertsList,$alertsJS) = WXmap_get_alerts($countyZoneURL,$fcstZone,$countyZone,$fireZone,$centerlat,$centerlong,
      "$cityname $statename");

if(!isset($zoom)) {$zoom = 11;}
print $Status;

?>
<div style="width:621px; margin:0px auto 0px auto;">
 <table cellspacing="0" cellpadding="0" 
   style="width:100%; margin:0px auto 0px auto; border:1px solid black; background-color:#F5F8FE">
  <tr>
   <td style="text-align:center; padding:5px 0px 5px 0px">Land forecasts for the United States from the National Weather Service</td>
  </tr>
 </table>
 <p> </p>
<table width="611" cellspacing="3" cellpadding="3" style="color:black; line-height:1.2em; margin: 0px auto 0px auto; border:solid 4px #006699; background-color:#FFF">
 <tr>
  <td colspan="3">
   <div style="margin: 6px auto 0px auto; text-align:center; width:570px; height:360px; border: outset #777 3px;" id="map_canvas">
    <noscript>
     <p><span style="font-size:14px;">Your JavaScript is disabled and preventing the map to load for you.</span></p>
     <p>Enable your JavaScript in your browser to view the map and select a forecast for any US location.</p>
    </noscript>
    <?php echo $errorMessage; ?>
   </div>
   <div style="text-align:center; font-size:12px;margin-top: 5px;">
     <span style="color:#009900;font-size:large;font-weight:bolder;">&#9744; </span> Green box outlines the forecast area</div>
  </td>
 </tr>
 <tr>
  <td style="font-size:12px; padding-left:16px; color:#030">Zoom or drag the map for a precise location<br />Double-click the location for the latest forecast.</td>
  <td style="width:32%; text-align:left; font-size:10px">LAT: &nbsp;<span id="latspan"><?php echo $lat; ?></span><br />LON: &nbsp;<span id="lngspan"><?php echo $long;?></span></td>
  <td style="float:right">
   <form action="" method="get">
   <div>
    <input type="hidden" id="latlongclicked" name="llc" />
    <input type="hidden" id="currentzoom" name="zoom" />
    <input type="hidden" id="currentmap"  name="map" />
    <input style="visibility:hidden;" id="theSubmitButton" type="submit" value=""/>
   </div>
   </form>
  </td>
 </tr>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><hr /></td>
 </tr>
 <tr>
  <td colspan="3" style="text-align:center; font-size: 1.5em;"><b><?php echo "$distanceFrom$cityname, $statename"; ?></b></td>
 </tr>
 <tr>
  <td colspan="3" style="padding-bottom: 6px; text-align:center; font-size: 0.8em">Updated <?php echo $updateTime;?></td>
 </tr>
</table>
<?php if(!empty($alertsList)) { // show the alerts box ?>
<table border="0" width="622" style="margin:5px auto 0px auto;border: thick solid red; background-color: #FF9;">
 <tr>
  <td class="alertbox" style="text-align: center;">
    <?php echo $alertsList; ?>
  </td>
</tr>
</table>
<?php } // end alert summary display ?>
<?php
// Generate the detailed forecasts from the gridpoint info:
/*
        {
            "number": 1,
            "name": "Today",
            "startTime": "2019-05-16T10:00:00-07:00",
            "endTime": "2019-05-16T18:00:00-07:00",
            "isDaytime": true,
            "temperature": 62,
            "temperatureUnit": "F",
            "temperatureTrend": null,
            "windSpeed": "10 to 15 mph",
            "windDirection": "WSW",
            "icon": "https://api.weather.gov/icons/land/day/tsra,50/tsra,60?size=medium",
            "shortForecast": "Showers And Thunderstorms Likely",
            "detailedForecast": "Showers and thunderstorms likely. Mostly cloudy, with a high near 62. West southwest wind 10 to 15 mph, with gusts as high as 20 mph. Chance of precipitation is 60%. New rainfall amounts between a tenth and quarter of an inch possible."
        },
*/

$dayStyle = 'vertical-align: top; border:solid 4px #006699; width: 311px; background-color:#FFFEE8; padding-bottom: 10px';
$nightStyle = 'vertical-align: top; border:solid 4px #006699; width: 311px; background-color:#F2F2F2; padding-bottom: 10px;';

if(isset($gridpointJSON['periods'][0])) { // only generate if we have data

global $NWSICONLIST;
load_lookups();

for ($i=0;$i<count($gridpointJSON['periods']);$i=$i+2)  {
	$Pa = $gridpointJSON['periods'][$i];
	$Pb = $gridpointJSON['periods'][$i+1];
?>
<table border="0" width="622" style="margin:0px auto 0px auto;">
 <tr>
  <td class="graybox" style="width: 311px;">
    <div class="graytitles"><?php echo $Pa['name']; ?></div>
  </td>
  <td>&nbsp;</td>
  <td class="graybox" style="width: 311px;">
    <div class="graytitles"><?php echo $Pb['name']; ?></div>
  </td>
 </tr>
 <tr>
    <?php if($Pa['isDaytime']) {
			// Daytime:
			$color = '#800';
			$loworhigh = 'HIGH';
			$tdStyle = $dayStyle;
		} else {
			// nighttime
			$tdStyle = $nightStyle;
			$loworhigh = 'LOW';
			$color = '#008';
		}?>
  <td style="<?php echo $tdStyle; ?>">
    <div style="text-align: center; font-size:110%; padding: 6px 0px 16px 0px;"><i>"<?php echo $Pa['shortForecast']; ?>"</i></div>
    <div style="vertical-align: top; padding: 0px 8px 0px 8px; text-align: justify">
    <?php
		  if(substr($Pa['icon'],0,4) !== 'http') {$Pa['icon'] = convert_icon($Pa['icon']); }
		  if(substr($Pb['icon'],0,4) !== 'http') {$Pb['icon'] = convert_icon($Pb['icon']); }
		?>
    <img style="float: left; border-style: none; padding-right: 8px" src="<?php echo $Pa['icon']; ?>" height="86" width="86"
    alt="<?php echo $Pa['shortForecast']; ?>" title="<?php echo $Pa['detailedForecast']; ?>"/>
    <div>
      <span style="color:<?php echo $color;?>;"><?php echo $loworhigh; ?> </span><span style="color:<?php echo $color;?>; font-size:1.5em;">
       <b><?php echo $Pa['temperature'];?>&deg;</b>
      </span>
    </div>
    <span style="font-size:.9em"><?php echo $Pa['detailedForecast']; ?></span>
    </div>
    </td>
  <td>&nbsp;</td>
    <?php if($Pb['isDaytime']) {
			// Daytime:
			$color = '#800';
			$loworhigh = 'HIGH';
			$tdStyle = $dayStyle;
		} else {
			// nighttime
			$tdStyle = $nightStyle;
			$loworhigh = 'LOW';
			$color = '#008';
		}?>
  <td style="<?php echo $tdStyle; ?>">
    <div style="text-align: center; font-size:110%; padding: 6px 0px 16px 0px;"><i>"<?php echo $Pb['shortForecast']; ?>"</i></div>
    <div style="vertical-align: top; padding: 0px 8px 0px 8px; text-align: justify">
    <img style="float: left; border-style: none; padding-right: 8px" src="<?php echo $Pb['icon']; ?>" height="86" width="86"
    alt="<?php echo $Pb['shortForecast']; ?>" title="<?php echo $Pb['detailedForecast']; ?>"/>
    <div>
      <span style="color:<?php echo $color;?>;"><?php echo $loworhigh; ?> </span><span style="color:<?php echo $color;?>; font-size:1.5em;">
       <b><?php echo $Pb['temperature'];?>&deg;</b>
      </span>
    </div>
    <span style="font-size:.9em"><?php echo $Pb['detailedForecast']; ?></span>
    </div>
    </td>
 </tr>
</table>
<?php
} // end of loop to print

?>
<script type="text/javascript">
// <![CDATA[
<?php
	print '// Leaflet/OpenStreetMap+other tile providers MAP production code
';
	// Generate map options
	$mOpts = array();
	$mList = '';  
	$mFirstMap = '';
	$mFirstMapName = '';
	$mSelMap = '';
	$mSelMapName = '';
	$swxAttrib = ' | Script by <a href="https://saratoga-weather.org/">Saratoga-weather.org</a>';
	$mScheme = $_SERVER['SERVER_PORT']==443?'https':'http';
	foreach ($mapTileProviders as $n => $M ) {
		$name = $M['name'];
		$vname = 'M'.strtolower($name);
		if(empty($mFirstMap)) {$mFirstMap = $vname; $mFirstMapName = $name;}  // default map is first in list
		if(strpos($n,'Mapbox') !== false and 
		   strpos($mapboxAPIkey,'-API-key-') !== false) { 
			 $mList .= "\n".'// skipping Mapbox - '.$name.' since $mapboxAPIkey is not set'."\n\n"; 
			 continue;
		}
		if($mScheme == 'https' and parse_url($M['URL'],PHP_URL_SCHEME) == 'http') {
			$mList .= "\n".'// skipping '.$name.' due to http only map tile link while our page is https'."\n\n";
			continue;
		}
		if($mapProvider == $n) {$mSelMap = $vname; $mSelMapName = $name;}
		$mList .= 'var '.$vname.' = L.tileLayer(\''.$M['URL'].'\', {
			maxZoom: '.$M['maxzoom'].',
			attribution: \''.$M['attrib'].$swxAttrib.'\',
			mapname: "'.$name.'"
			});
';
		$mOpts[$name] = $vname;
		
	}
	print "// Map tile providers:\n";
  print $mList;
	print "// end of map tile providers\n\n";
	print "var baseLayers = {\n";
  $mtemp = '';
	foreach ($mOpts as $n => $v) {
		$mtemp .= '  "'.$n.'": '.$v.",\n";
	}
	$mtemp = substr($mtemp,0,strlen($mtemp)-2)."\n";
	print $mtemp;
	print "};	\n";
	if(empty($mSelMap)) {$mSelMap = $mFirstMap; $mSelMapName = $mFirstMapName;}
	if(isset($reqMap) and isset($mOpts[$reqMap])) {
		$mSelMap = $mOpts[$reqMap];
		$mSelMapName = $reqMap;
	}
	// end Generate map tile options
?>
var map = L.map('map_canvas', {
		center: new L.latLng([<?php echo $centerlat;?>,<?php echo $centerlong;?>]), 
		zoom: <?php echo $zoom; ?>,
		layers: [<?php echo $mSelMap; ?>],
		doubleClickZoom: false,
		scrollWheelZoom: false
		});

var selMap = '<?php echo $mSelMapName; ?>';
	 // console.log('initial selMap='+selMap);	

  L.control.scale().addTo(map);
  L.control.layers(baseLayers).addTo(map);

// draw the gridpoint forecast area as a polygon
  var polyfa = [
<?php
  foreach ($poly as $i => $coords) {
		list($longP,$latP) = explode(' ',$coords);
		print "  [$latP,$longP],\n";
	}
?>
  ];
	
 var mapolyfa = new L.polygon(polyfa,{
  opacity: 1.0,
  color: "#009900",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "#7FF378",
  fillOpacity: 0.20,
	title: "Forecast Area"
 }).addTo(map);

  mapolyfa.bindTooltip("Forecast Area for <?php echo "$distanceFrom$cityname"; ?>", 
   { sticky: true,
     direction: "auto"
   });
	 
	var markerImageGreen  = new L.icon({ 
		iconUrl: "./ajax-images/mma_20_green.png",
		iconSize: [12, 20],
		iconAnchor: [6, 20]
    });
	var marker = new L.marker(new L.LatLng(<?php echo $centerlat;?>,<?php echo $centerlong;?>),{
		clickable: true,
		draggable: false,
		icon: markerImageGreen,
		title: "Selected location at <?php echo $centerlat;?>,<?php echo $centerlong;?>"+
		       "\n(/gridpoints/<?php echo $gridPointMeta; ?>/forecast bounding box shown)"+
					 "\nadjustment of <?php echo $adj1; ?>,<?php echo $adj2; ?> gridpoint used"
	});
  map.addLayer(marker);

<?php if(!empty($alertsJS)) { print $alertsJS; } ?>

// display mouse lat/long in page as mouse moves	 
 function mouseMove (e) {
   document.getElementById('latspan').innerHTML = e.latlng.lat.toFixed(4)
   document.getElementById('lngspan').innerHTML = e.latlng.lng.toFixed(4)
 }
 map.on('mousemove',mouseMove);

// do 'submit' with mouse lat/long+current zoom as args  
 function mouseDoubleClicked (e) {
   document.getElementById('latlongclicked').value = e.latlng.lat.toFixed(4) + ',' + e.latlng.lng.toFixed(4);
   document.getElementById('currentzoom').value = map.getZoom();
   document.getElementById('currentmap').value = selMap;
   document.getElementById('theSubmitButton').click();
	 // console.log('submit selMap='+selMap)	
 }
 map.on('dblclick',mouseDoubleClicked);
 
 function mapbasechg (e) {
   document.getElementById('currentmap').value = e.name;
	 selMap = e.name;
	 //console.log('mapchange selMap='+e.name)	
 }
 
 map.on('baselayerchange',mapbasechg);

// ]]>
</script>
<?php } // end of got data to print ?>
<p style="text-align:center;"><small>Script by <a href="https://saratoga-weather.org/">Saratoga-weather.org</a>. 
Data provided by <a href="https://www.weather.gov/">NOAA/NWS</a></small> </p>
</div>
</div>
<?php if(!$includeMode) { ?>
</body>
</html>
<?php } // end !$includeMode ?>
<?php
// ------------------------------------------------------------------------------------------
// FUNCTIONS

function WXmap_fetchUrlWithoutHanging($inurl)
{

  // get contents from one URL and return as string

  global $Status, $needCookie /*, $URLcache */;
  $useFopen = false;
  $overall_start = time();
  if (!$useFopen) {

    // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed

    $numberOfSeconds = 6;
		$url = $inurl;
    // Thanks to Curly from ricksturf.com for the cURL fetch functions

    $data = '';
    $domain = parse_url($url, PHP_URL_HOST);
    $theURL = str_replace('nocache', '?' . $overall_start, $url); // add cache-buster to URL if needed
    $Status.= "<!-- curl fetching '$theURL' -->\n";
    $ch = curl_init(); // initialize a cURL session
    curl_setopt($ch, CURLOPT_URL, $theURL); // connect to provided URL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't verify peer certificate
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (wxForecastMap.php (JSON) - saratoga-weather.org)');
//    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, // request LD-JSON format
    array(
      "Accept: application/geo+json",
			"Cache-control: no-cache",
			"Pragma: akamai-x-cache-on, akamai-x-get-request-id"
    ));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds); //  connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds); //  data timeout
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the data transfer
    curl_setopt($ch, CURLOPT_NOBODY, false); // set nobody
    curl_setopt($ch, CURLOPT_HEADER, true); // include header information

      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
      curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time

    if (isset($needCookie[$domain])) {
      curl_setopt($ch, $needCookie[$domain]); // set the cookie for this request
      curl_setopt($ch, CURLOPT_COOKIESESSION, true); // and ignore prior cookies
      $Status.= "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
    }

    $data = curl_exec($ch); // execute session
    if (curl_error($ch) <> '') { // IF there is an error
      $Status.= "<!-- curl Error: " . curl_error($ch) . " -->\n"; //  display error notice
    }

    $cinfo = curl_getinfo($ch); // get info on curl exec.
    /*
    curl info sample
    Array
    (
    [url] => http://saratoga-weather.net/clientraw.txt
    [content_type] => text/plain
    [http_code] => 200
    [header_size] => 266
    [request_size] => 141
    [filetime] => -1
    [ssl_verify_result] => 0
    [redirect_count] => 0
    [total_time] => 0.125
    [namelookup_time] => 0.016
    [connect_time] => 0.063
    [pretransfer_time] => 0.063
    [size_upload] => 0
    [size_download] => 758
    [speed_download] => 6064
    [speed_upload] => 0
    [download_content_length] => 758
    [upload_content_length] => -1
    [starttransfer_time] => 0.125
    [redirect_time] => 0
    [redirect_url] =>
    [primary_ip] => 74.208.149.102
    [certinfo] => Array
    (
    )
    [primary_port] => 80
    [local_ip] => 192.168.1.104
    [local_port] => 54156
    )
    */
		if($url !== $cinfo['url'] and $cinfo['http_code'] == 200 and
		   strpos($url,'/points/') > 0 and strpos($cinfo['url'],'/gridpoints/') > 0) {
			# only cache point forecast->gridpoint forecast successful redirects
			$Status .= "<!-- note: fetched '".$cinfo['url']."' after redirect was followed. -->\n";
			//$URLcache[$inurl] = $cinfo['url'];
			//$Status .= "<!-- $inurl added to URLcache -->\n";
		}

    $Status.= "<!-- HTTP stats: " . " RC=" . $cinfo['http_code'];
		if (isset($cinfo['primary_ip'])) {
			$Status .= " dest=" . $cinfo['primary_ip'];
		}
    if (isset($cinfo['primary_port'])) {
      $Status .= " port=" . $cinfo['primary_port'];
    }

    if (isset($cinfo['local_ip'])) {
      $Status.= " (from sce=" . $cinfo['local_ip'] . ")";
    }

    $Status.= "\n      Times:" . 
		" dns=" . sprintf("%01.3f", round($cinfo['namelookup_time'], 3)) . 
		" conn=" . sprintf("%01.3f", round($cinfo['connect_time'], 3)) . 
		" pxfer=" . sprintf("%01.3f", round($cinfo['pretransfer_time'], 3));
    if ($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
      $Status.= " get=" . sprintf("%01.3f", round($cinfo['total_time'] - $cinfo['pretransfer_time'], 3));
    }

    $Status.= " total=" . sprintf("%01.3f", round($cinfo['total_time'], 3)) . " secs -->\n";

    // $Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";

    curl_close($ch); // close the cURL session

    // $Status .= "<!-- raw data\n".$data."\n -->\n";
    $stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
    $content = (string)array_pop($stuff); // last one is the content
    $headers = (string)array_pop($stuff); // next-to-last-one is the headers

    if ($cinfo['http_code'] <> '200') {
      $Status.= "<!-- headers returned:\n" . $headers . "\n -->\n";
    }

    return $data; // return headers+contents
  }
  else {

    //   print "<!-- using file_get_contents function -->\n";

    $STRopts = array(
      'http' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (NWS-forecast-map.php - saratoga-weather.org)\r\n" . 
					"Accept: application/geo+json\r\n",
					"Cache-control: no-cache\r\n",
					"Pragma: akamai-x-cache-on, akamai-x-get-request-id\r\n"
      ) ,
      'ssl' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
				'verify_peer' => false,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (NWS-forecast-map.php - saratoga-weather.org)\r\n" . 
					"Accept: application/geo+json\r\n",
					"Cache-control: no-cache\r\n",
					"Pragma: akamai-x-cache-on, akamai-x-get-request-id\r\n"
      )
    );
    $STRcontext = stream_context_create($STRopts);
    $T_start = WXmap_fetch_microtime();
    $xml = file_get_contents($inurl, false, $STRcontext);
    $T_close = WXmap_fetch_microtime();
    $headerarray = get_headers($url, 0);
    $theaders = join("\r\n", $headerarray);
    $xml = $theaders . "\r\n\r\n" . $xml;
    $ms_total = sprintf("%01.3f", round($T_close - $T_start, 3));
    $Status.= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
    $Status.= "<-- get_headers returns\n" . $theaders . "\n -->\n";

    //   print " file() stats: total=$ms_total secs.\n";

    $overall_end = time();
    $overall_elapsed = $overall_end - $overall_start;
    $Status.= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n";

    //   print "fetch function elapsed= $overall_elapsed secs.\n";

    return ($xml);
  }
} // end WXmap_fetchUrlWithoutHanging

// ------------------------------------------------------------------

function WXmap_fetch_microtime()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

// ------------------------------------------------------------------------------------------

function WXmap_get_alerts($countyURL,$warnZone,$countyZone,$fireZone,$lat,$long,$locName) {
	# https://forecast.weather.gov/showsigwx.php?warnzone=MNZ083&warncounty=MNC013&firewxzone=MNZ083&lat=44.1661&lon=-94.0054
	# get/decode the alerts (if any) and return 
	# strings $alertsList for display and $alertsJS for polygons on the map
	$alertURL = str_replace('zones/county','alerts/active/zone',$countyURL);
	global $Status,$doDebug;
	
	$Status .= "<!-- fetching County alerts -->\n";
	$linkURL = "https://forecast.weather.gov/showsigwx.php?warnzone={$warnZone}&warncounty={$countyZone}&firewxzone={$fireZone}&lat={$lat}&lon={$long}&local_place1=".urlencode($locName);
  $alertHTML = WXmap_fetchUrlWithoutHanging($alertURL);
  $stuff = explode("\r\n\r\n",$alertHTML); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	//$Status .= "<!-- m=".print_r($m,true)." -->\n";
	//$Status .= "<!-- html=".print_r($html,true)." -->\n";
	if(!isset($m[1])) {
		$Status .= "<!-- failed to fetch $alertURL to process -->\n";
	  return(array('Error: alert data not available',''));
	}
		
	$lastRC = (string)$m[1];

  if($lastRC !== '200') { // no data to process
		$Status .= "<!-- no alerts to process -->\n";
	  return(array('',''));
	}

	$AJSON = json_decode($content,true);
	if($doDebug) {$Status .= "<!-- alert JSON\n".var_export($AJSON,true)." -->\n"; }
	
	if(!isset($AJSON['features'][0])) {
		$Status .= "<!-- no alerts found at $alertURL -->\n";
	  return(array('',''));
	}
	
	$A = "";
	$JS = '';
  $colors = array('#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC',
                  '#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC',
                  '#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC'
									);
		foreach($AJSON['features'] as $i => $rJ) {
		$J = $rJ['properties'];
		if(isset($J['expires'])) {
			$exp = strtotime($J['expires']);
			$Status .= "<!-- expires '".$J['expires']. "' ";
			if(time() > $exp) {
				$Status .= " EXPIRED. -->\n";
				continue;
			} else {
				$Status .= " still active. -->\n";
			}
		}
		// generate the text and link
		if(isset($J['headline']) and isset($J['description'])) {
			$desc = $J['description'];
			$headline = preg_replace('| by .*$|i','',$J['headline']);
			$product = $J['id'];
#			$url = NWS_DETAIL_PRODUCT_URL.$product;
			$url = $linkURL;
			$A .= '<b><a href="'.$url.'" title="'.$desc.'">'.$headline."</a></b><br/>\n";
			$Status .= "<!-- alert $i='".$J['headline']."' found expires='".$J['expires']."' -->\n";
		}
		// process map defs
		if(isset($rJ['geometry']['coordinates'][0])) {
			if(true or preg_match('|POLYGON\(\(([^\)]+)\)\)|i',$J['geometry'],$m)) {
				$poly = array();
				foreach ($rJ['geometry']['coordinates'][0] as $ii => $coords) {
					$poly[] = $coords[0].' '.$coords[1];
				}
				
				$Status .= "<!-- alert poly cs=$i \n".var_export($poly,true)." -->\n";
				$cs = $i;
        $cc = count($colors);
        if(!isset($colors[$cs])) {$tr = range(0,$cs); shuffle($tr); $cs = $tr[0];}
        $rc = isset($colors[$cs])?$colors[$cs]:$colors[1];
				$id = $i+1;
$JS .= '					
  var poly'.$id.' = [
';
				foreach ($poly as $i => $coords) {
					list($longP,$latP) = explode(' ',$coords);
					$JS .= "  [$latP,$longP],\n";
				}
				$tooltip = '<b>'.str_replace(' issued ',"</b><br>issued ",$J['headline']);
				$tooltip = str_replace(' until ','<br/>&nbsp;&nbsp;&nbsp;until ',$tooltip);
				$tooltip = str_replace(' by ','<br/> by ',$tooltip);
$JS .= '
  ];
	
 var mapoly'.$id.' = new L.polygon(poly'.$id.',{
  opacity: 1.0,
  color: "'.$rc.'",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "'.$rc.'",
  fillOpacity: 0.20,
	title: "'.$J['event'].'"
 }).addTo(map);

  mapoly'.$id.'.bindTooltip("'.$tooltip.'", 
   { sticky: true,
     direction: "auto"
   });
';				
									  
	  } else {
				$Status.= "<!-- no coordinates found -->\n";
	  }// end polygon generation	
	} // end JS generation
		
		
	} // end process alerts
	
	return(array($A,$JS));
}

// ------------------------------------------------------------------------------------------

function convert_icon($icon)
{

  // input: /icons/land/day/rain_showers,20/sct,20?size=medium
  //        /icons/land/night/sct?size=medium
	//
  // output: {iconDir}{icon}{$iconType} or
  //         DualImage.php?i={lefticon}&j={righticon}&ip={leftpop}&jp={rightpop}

  global $Status, $NWSICONLIST;
	$iconDir = 'https://forecast.weather.gov/newimages/medium/';
	$dualImageDir = 'https://forecast.weather.gov/';
	$iconType = '.png';
	$iconHeight = '86';
	$iconWidth  = '86';
	
  $newicon = $icon; // for testing
  $uparts = parse_url($icon);
  $iparts = array_slice(explode('/', $uparts['path']) , 3); //get day|night/icon[/icon]

  // $Status .= "<!-- iparts \n".print_r($iparts,true)." -->\n";

  $daynight = ($iparts[0] == 'day') ? '' : 'night/';
  list($icon1, $pop1) = explode(',', $iparts[1] . ',');
  $doDual = false;
  if (isset($iparts[2])) {
    $doDual = true;
    list($icon2, $pop2) = explode(',', $iparts[2] . ',');
  }
  else {
    $icon2 = '';
    $pop2 = '';
  }

  // convert new API icon names to old image names

  if (isset($NWSICONLIST["{$daynight}{$icon1}"])) {
    list($nicon1, $rest) = explode('|', $NWSICONLIST["{$daynight}{$icon1}"]);
    $icon1 = $nicon1;
  }
  else {
    $Status.= "<!-- icon1='$icon1' not found - na used instead -->\n";
    $icon1 = 'na';
  }

  if ($icon2 <> '') {
    if (isset($NWSICONLIST["{$daynight}{$icon2}"])) {
      list($nicon2, $rest) = explode('|', $NWSICONLIST["{$daynight}{$icon2}"]);
      $icon2 = $nicon2;
    }
    else {
      $Status.= "<!-- icon2='$icon2' not found - na used instead -->\n";
      $icon2 = 'na';
    }
  }

  //  $Status .= "<!-- doDual='$doDual' DualImageAvailable='$DualImageAvailable' icon1=$icon1 icon2=$icon2 -->\n";

  if ($pop2 !== '') { // generate the DualImage.php script calling sequence for image
    $newicon = "{$dualImageDir}DualImage.php?";
    $newicon.= "i=$icon1";
    if ($pop1 <> '') {
      $newicon.= "&ip=$pop1";
    }

    $newicon.= "&j=$icon2";
    if ($pop2 <> '') {
      $newicon.= "&jp=$pop2";
    }

    $Status.= "<!-- dual image '$newicon' used-->\n";
  }
  elseif ($pop1 !== '') { // use the image as-is
    $newicon = "{$iconDir}{$icon1}{$pop1}{$iconType}";
  }
  else {
    $newicon = "{$iconDir}{$icon1}{$iconType}";
  }

  return ($newicon);
} // end convert_to_local_icon

// ------------------------------------------------------------------------------------------

function get_zone($zoneURL) {
	# extract Zone from end of URL
	$p = explode('/',$zoneURL);
	return(array_pop($p));
}
// ------------------------------------------------------------------------------------------

function load_lookups()
{

  // static lookup arrays

  global $fcstPeriods, $NWSICONLIST;
  $fcstPeriods = array( // for filling in the '<period> Through <period>' zone forecasts.
    'Monday',
    'Monday Night',
    'Tuesday',
    'Tuesday Night',
    'Wednesday',
    'Wednesday Night',
    'Thursday',
    'Thursday Night',
    'Friday',
    'Friday Night',
    'Saturday',
    'Saturday Night',
    'Sunday',
    'Sunday Night',
    'Monday',
    'Monday Night',
    'Tuesday',
    'Tuesday Night',
    'Wednesday',
    'Wednesday Night',
    'Thursday',
    'Thursday Night',
    'Friday',
    'Friday Night',
    'Saturday',
    'Saturday Night',
    'Sunday',
    'Sunday Night'
  );
  $NWSICONLIST = array(

    //  index is new icon name
    //  imagename is original image name (for which we have icons available)
    //  PoP flag controls if PoP is written on output or not.
    //  ScePosition controls if image is pulled from Left, Middle or Right of source image
    //  Text Name - optional, just a reminder of what it means
    //  imgname | PoP?=[YN] | ScePosition=[LMR] | Text Name (optional)

    'bkn' => 'bkn|N|L|Broken Clouds',
    'night/bkn' => 'nbkn|N|L|Night Broken Clouds',
    'blizzard' => 'blizzard|Y|L|Blizzard',
    'night/blizzard' => 'nblizzard|Y|L|Night Blizzard',
    'cold' => 'cold|Y|L|Cold',
    'night/cold' => 'cold|Y|L|Cold',

    //  'cloudy' =>  'cloudy|Y|L|Overcast (old cloudy)',

    'dust' => 'du|N|22|Dust',
    'night/dust' => 'ndu|N|22|Night Dust',

    //  'fc' =>  'fc|N|L|Funnel Cloud',
    //  'nsvrtsra' =>  'nsvrtsra|N|L|Funnel Cloud (old)',

    'few' => 'few|Y|L|Few Clouds',
    'night/few' => 'nfew|Y|L|Night Few Clouds',

    //  'fg' =>  'fg|N|R|Fog',
    //  'br' =>  'br|Y|R|Fog / mist old',
    //  'fu' =>  'fu|N|L|Smoke',

    'fog' => 'fg|N|R|Fog',

    'night/fog' => 'nfg|N|R|Night Fog',
    'fzra' => 'fzra|Y|L|Freezing rain',
    'night/fzra' => 'nfzra|Y|L|Night Freezing Rain',

    //  'fzrara' =>  'fzrara|Y|L|Rain/Freezing Rain (old)',

    'snow_fzra' => 'fzra_sn|Y|L|Freezing Rain/Snow',
    'night/snow_fzra' => 'nfzra_sn|Y|L|Night Freezing Rain/Snow',

    //  'mix' =>  'mix|Y|L|Freezing Rain/Snow',
    //  'hi_bkn' =>  'hi_bkn|Y|L|Broken Clouds (old)',
    //  'hi_few' =>  'hi_few|Y|L|Few Clouds (old)',
    //  'hi_sct' =>  'hi_sct|N|L|Scattered Clouds (old)',
    //  'hi_skc' =>  'hi_skc|N|L|Clear Sky (old)',
    //  'hi_nbkn' =>  'hi_nbkn|Y|L|Night Broken Clouds (old)',
    //  'hi_nfew' =>  'hi_nfew|Y|L|Night Few Clouds (old)',
    //  'hi_nsct' =>  'hi_nsct|N|L|Night Scattered Clouds (old)',
    //  'hi_nskc' =>  'hi_nskc|N|L|Night Clear Sky (old)',
    //  'hi_nshwrs' =>  'hi_nshwrs|Y|R|Night Showers',
    //  'hi_ntsra' =>  'hi_ntsra|Y|L|Night Thunderstorm',
    //  'hi_shwrs' =>  'hi_shwrs|Y|R|Showers',
    //  'hi_tsra' =>  'hi_tsra|Y|L|Thunderstorm',

    'hur_warn' => 'hur_warn|N|L|Hurrican Warning',
    'night/hur_warn' => 'hur_warn|N|L|Hurrican Warning',
    "hurr_warn" => "hurr|N|L|Hurricane warning", // New NWS list
    "night/hurr_warn" => "hurr|N|L|Night Hurricane warning", // New NWS list
    'hur_watch' => 'hur_watch|N|L|Hurricane Watch',
    'night/hur_watch' => 'hur_watch|N|L|Hurricane Watch',
    "hurr_watch" => "hurr-noh|N|L|Hurricane watch", // New NWS list
    "night/hurr_watch" => "hurr-noh|N|L|Night Hurricane watch", // New NWS list
    'hurricane' =>  'hurr-noh|Y|L|Hurricane',
    'night/hurricane' =>  'hurr-noh|Y|L|Hurricane',
    //  'hurr' =>  'hurr|N|L|Hurrican Warning old',
    //  'hurr-noh' =>  'hurr-noh|N|L|Hurricane Watch old',

    'hazy' => 'hz|N|L|Haze',
    'night/hazy' => 'hz|N|L|Haze',
    "haze" => "hz|N|L|Haze", // New NWS list
    "night/haze" => "hz|N|L|Night Haze", // New NWS list

    //  'hazy' =>  'hazy|N|L|Haze old',

    'hot' => 'hot|N|R|Hot',
    'night/hot' => 'hot|N|R|Hot',
    'sleet' => 'ip|Y|L|Ice Pellets',
    'night/sleet' => 'nip|Y|L|Night Ice Pellets',

    //  'minus_ra' =>  'minus_ra|Y|L|Stopped Raining',
    //  'ra1' =>  'ra1|N|L|Stopped Raining (old)',
    //  'mist' =>  'mist|N|R|Mist (fog) (old)',
    //  'ncloudy' =>  'ncloudy|Y|L|Overcast night(old ncloudy)',
    //  'ndu' =>  'ndu|N|M|Night Dust',
    //  'nfc' =>  'nfc|N|L|Night Funnel Cloud',
    //  'nbr' =>  'nbr|Y|R|Night Fog/mist (old)',
    //  'nfu' =>  'nfu|N|L|Night Smoke',
    //  'nmix' =>  'nmix|Y|30|Night Freezing Rain/Snow (old)',
    //  'nrasn' =>  'nrasn|Y|M|Night Snow (old)',
    //  'pcloudyn' =>  'pcloudyn|Y|L|Night Partly Cloudy (old)',
    //  'nscttsra' =>  'nscttsra|Y|M|Night Scattered Thunderstorm',
    //  'nsn_ip' =>  'nsn_ip|Y|L|Night Snow/Ice Pellets (old)',
    //  'nwind' =>  'nwind|N|5|Night Windy/Clear (old)',

    'ovc' => 'ovc|N|L|Overcast',
    'night/ovc' => 'novc|N|L|Night Overcast',
    'rain' => 'ra|Y|30|Rain',
    'night/rain' => 'nra|Y|30|Night Rain',
    'rain_showers' => 'shra|Y|12|Rain Showers',
    'night/rain_showers' => 'nshra|Y|12|Night Rain Showers',
    "rain_showers_hi" => "hi_shwrs|Y|45|Rain showers (low cloud cover)", // New NWS list
    "night/rain_showers_hi" => "hi_nshwrs|Y|45|Night Rain showers (low cloud cover)", // New NWS list
    'rain_sleet' => 'raip|Y|M|Rain/Ice Pellets',
    'night/rain_sleet' => 'nraip|Y|M|Night Rain/Ice Pellets',
    'rain_fzra' => 'ra_fzra|Y|30|Rain/Freezing Rain',
    'night/rain_fzra' => 'nra_fzra|Y|30|Night Freezing Rain',
    'rain_snow' => 'ra_sn|Y|22|Rain/Snow',
    'night/rain_snow' => 'nra_sn|Y|22|Night Rain/Snow',

    //  'rasn' =>  'rasn|Y|M|Rain/Snow (old)',

    'sct' => 'sct|N|L|name',
    'night/sct' => 'nsct|N|L|Night Scattered Clouds',

    //  'pcloudy' =>  'pcloudy|Y|L|Partly Cloudy (old)',
    //  'scttsra' =>  'scttsra|Y|M|name',
    //  'shra2' =>  'shra2|N|10|Rain Showers (old)',

    'skc' => 'skc|N|L|Clear',
    'night/skc' => 'nskc|N|L|Night Clear',
    'snow' => 'sn|Y|L|Snow',
    'night/snow' => 'nsn|Y|L|Night Snow',
    'snow_sleet' => 'sn_ip|Y|L|Snow/Ice Pellets',
    'night/snow_sleet' => 'nsn_ip|Y|L|Night Snow/Ice Pellets',
    'smoke' => 'fu|N|L|Smoke',
    'night/smoke' => 'nfu|N|L|Smoke', // NEW - Nov-2016

    //  'sn_ip' =>  'sn_ip|Y|L|Snow/Ice Pellets (old)',
    //  'tcu' =>  'tcu|N|L|Towering Cumulus (old)',

    'tornado' => 'tor|N|L|Tornado',
    'night/tornado' => 'ntor|N|L|Night Tornado',
    'tsra' => 'tsra|Y|10|Thunderstorm',
    'night/tsra' => 'ntsra|Y|10|Night Thunderstorm',
    "tsra_sct" => "scttsra|Y|20|Thunderstorm (medium cloud cover)", // New NWS list
    "night/tsra_sct" => "nscttsra|Y|20|Night Thunderstorm (medium cloud cover)", // New NWS list
    "tsra_hi" => "hi_tsra|Y|L|Thunderstorm (low cloud cover)", // New NWS list
    "night/tsra_hi" => "hi_ntsra|Y|L|Night Thunderstorm (low cloud cover)", // New NWS list

    //  'tstormn' =>  'tstormn|N|L|Thunderstorm night (old)',
    //  'ts_nowarn' =>  'ts_nowarn|N|L|Tropical Storm',

    "ts_hurr_warn" => "ts_hur_flags|N|L|Tropical storm with hurricane warning in effect", // New NWS list
    "night/ts_hurr_warn" => "ts_hur_flags|N|L|Night Tropical storm with hurricane warning in effect", // New NWS list
		'tropical_storm' => 'tropstorm-noh|Y|L|Tropical Storm Warning',
		'night/tropical_storm' => 'tropstorm-noh|Y|L|Tropical Storm Warning',
    'ts_warn' => 'tropstorm|Y|L|Tropical Storm Warning',
    'night/ts_warn' => 'tropstorm|Y|L|Tropical Storm Warning',

    //  'tropstorm-noh' =>  'tropstorm-noh|N|L|Tropical Storm old',
    //  'tropstorm' =>  'tropstorm|N|L|Tropical Storm Warning old',

    'ts_watch' => 'tropstorm-noh|Y|L|Tropical Storm Watch',
    'night/ts_watch' => 'tropstorm-noh|Y|L|Tropical Storm Watch',

    //  'ts_hur_flags' =>  'ts_hur_flags|Y|L|Hurrican Warning old',
    //  'ts_no_flag' =>  'ts_no_flag|Y|L|Tropical Storm old',

    'wind_bkn' => 'wind_bkn|N|7|Windy/Broken Clouds',
    'night/wind_bkn' => 'nwind_bkn|N|7|Night Windy/Broken Clouds',
    'wind_few' => 'wind_few|N|7|Windy/Few Clouds',
    'night/wind_few' => 'nwind_few|N|7|Night Windy/Few Clouds',
    'wind_ovc' => 'wind_ovc|N|7|Windy/Overcast',
    'night/wind_ovc' => 'nwind_ovc|N|7|Night Windy/Overcast',
    'wind_sct' => 'wind_sct|N|7|Windy/Scattered Clouds',
    'night/wind_sct' => 'nwind_sct|N|7|Night Windy/Scattered Clouds',
    'wind_skc' => 'wind_skc|N|7|Windy/Clear',
    'night/wind_skc' => 'nwind_skc|N|7|Night Windy/Clear',

    //  'wind' =>  'wind|N|L|Windy/Clear (old)',

    'na' => 'na|N|L|Not Available',
  );
} // end load_lookups function

