<?php
/**
 * Copyright (c) 2006-2009 Knut Kohl <knutkohl@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * GPL: http://www.gnu.org/licenses/gpl.txt
 *
 * @package Module-Analyse
 * @subpackage Core
 * @desc Generates the analyse graph
 */

// -----------------------------------------------------------------------------
// Some configuration

$MinMaxWidth = array( 4, 8 );

$XYGrace     = array( 3, 8 ); # x/y grace in percent

// -----------------------------------------------------------------------------
error_reporting(0);
error_reporting(E_ALL);

$time = time();

$jppath = '../../local/module/analyse/jpgraph';

/**
 * @ignore
 */
require $jppath.'/jpgraph.php';

$data = FALSE;

if (!empty($_GET['data'])) {
  # 1. via ?data=...
  $data = $_GET['data'];
} elseif (!empty($_SERVER['QUERY_STRING'])) {
  # 2. via query ?...
  $data = $_SERVER['QUERY_STRING'];
}

if (!$data) {
  # error handling via jpgraph ...
  $e = new JpGraphErrObjectImg();
  $e->Raise('No data!'."\n\n".'There are 2 ways to provide data:'."\n\n"
           .'1. as ...?<data>'."\n"
           .'2. or as ...?data=<data>');
}

$data = base64_decode($data);

if (function_exists('gzuncompress')) {
  // parameter CAN but not must be compressed
  ob_start();
  // pick warnings of de-compression errors
  eval('$_data = gzuncompress($data);');
  if (!ob_get_clean()) $data = $_data;
}

list (
  $xsize, $ysize,
  $bid,  $legend,
  $mid,  $mlegend,
  $hmid, $hlegend,
  $data
) = unserialize($data);

$ends = $data['endts'];
$datax = array_map('fTS', $data['endts']);
$datay = $data['bid'];
$databids = $data['bids'];

$MinMaxBids = $x = $y = array(1E+10,0);

$XY = $linebids = $linemid = $linehmid = array();

foreach ($databids as $id => $bids) {
  $XY[md5($datax[$id].$datay[$id])] = array($bids,$ends[$id]);
  if ($bids < $MinMaxBids[0]) $MinMaxBids[0] = $bids;
  if ($bids > $MinMaxBids[1]) $MinMaxBids[1] = $bids;
  // self calculation of x grace
  if ($datax[$id] < $x[0]) $x[0] = $datax[$id];
  if ($datax[$id] > $x[1]) $x[1] = $datax[$id];
  // self calculation of y grace
  if ($datay[$id] < $y[0]) $y[0] = $datay[$id];
  if ($datay[$id] > $y[1]) $y[1] = $datay[$id];
  $linebids[] = $bid;
  $linemid[]  = $mid;
  $linehmid[] = $hmid;
}

$MinMaxScale = (count($datax) == 1 OR ($MinMaxBids[1]-$MinMaxBids[0]) == 0) ? 1
             : ($MinMaxWidth[1]-$MinMaxWidth[0]) / ($MinMaxBids[1]-$MinMaxBids[0]);

include $jppath.'/jpgraph_scatter.php';
include $jppath.'/jpgraph_line.php';
include $jppath.'/jpgraph_date.php';

// move at least bid into graph...
if ($y[0] > $bid) $y[0] = $bid;
if ($y[1] < $bid) $y[1] = $bid;

// self calculation of graces
$Grace[0] = $y[0] - ($y[1]-$y[0])*$XYGrace[1]/100;
$Grace[0] = floor($Grace[0]);
// no negative prices ;-)
if ($Grace[0] < 0) $Grace[0] = '0';
$Grace[1] = $y[1] + ($y[1]-$y[0])*$XYGrace[1]/100;

$Grace[2] = $x[0] - ($x[1]-$x[0])*$XYGrace[0]/100;
if ($Grace[2] < 0) $Grace[2] = '1';
$Grace[3] = $x[1] + ($x[1]-$x[0])*$XYGrace[0]/100;

// Setup a basic graph
$graph = new Graph($xsize, $ysize, 'auto');

$graph->SetScale('datlin', $Grace[0], $Grace[1], $Grace[2], $Grace[3]);

if ($xsize < 500) {
  $graph->SetMargin(55, 15, 25, 40);
  $graph->xaxis->SetFont(FF_FONT0);
  $graph->yaxis->SetFont(FF_FONT0);
} else {
  $graph->SetMargin(65, 15, 25, 50);
}

// Setup graph colors
$graph->SetFrame(FALSE);
$graph->SetMarginColor('white');
// X-axis
$graph->xaxis->SetPos('min');
$graph->xaxis->scale->SetDateFormat('H:i');
$graph->xaxis->SetLabelAngle(90);
$graph->xaxis->HideTicks(TRUE, FALSE);

// Y-axis
$graph->yaxis->SetLabelFormat('%0.2f'); 
$graph->yaxis->HideTicks(TRUE, FALSE);

// Grid
$graph->xgrid->Show();
$graph->xgrid->SetColor('gray@0.8');
$graph->ygrid->SetColor('gray@0.8');

// Legend
$graph->legend->SetColor('black', 'white');
$graph->legend->SetFillColor('white');
$graph->legend->SetShadow(FALSE);
$graph->legend->SetPos(0.005, 0.01);
$graph->legend->SetLayout(LEGEND_HOR);

$flegend = '%1$.2f : %2$s';
// Lines
if ($bid > 0) {
  // Create bid line as lin. plot
  $lp1 = new LinePlot($linebids, $datax);
  $lp1->SetColor('red');
  $lp1->SetLegend(sprintf($flegend, $bid, $legend));
  $graph->Add($lp1);
}

if ($mid > 0) {
  // Arithmetical average
  $lp2 = new LinePlot($linemid, $datax);
  $lp2->SetColor('green');
  $lp2->SetLegend(sprintf($flegend, $mid, $mlegend));
  $graph->Add($lp2);
}

if ($hmid > 0) {
  // Harmonic average
  $lp3 = new LinePlot($linehmid, $datax);
  $lp3->SetColor('blue');
  $lp3->SetLegend(sprintf($flegend, $hmid, $hlegend));
  $graph->Add($lp3);
}

// Create the scatter plot
$sp1 = new ScatterPlot($datay, $datax);
$sp1->mark->SetType(MARK_FILLEDCIRCLE);

// Specify the callback
$sp1->mark->SetCallbackYX('fCallbackYX');

//$sp1->SetLegend('Auctions');

// Plot to the graph
$graph->Add($sp1);

// Send to browser
$graph->Stroke();

/**
 * Reformat time stamp
 *
 * @param integer $ts Time stamp
 * @return integer
 */
function fTS ( $ts ) {
  $h = explode(':',date('H:i:s', $ts));
  $h = ($h[0]-1)*60*60 + $h[1]*60 + $h[2];
  // move to the end of display
  if ($h < 0) $h += 86400;
  return $h;
}

/**
 * Callback function for graph point formating
 *
 * @param integer $Y
 * @param integer $X
 * @return array
 */
function fCallbackYX ( $Y, $X ) {
  # return array(width,border_color,fill_color,NULL,NULL)
  global $XY, $MinMaxScale, $MinMaxWidth, $MinMaxBids, $time;
  $point = md5($X.$Y);
  $bids = $XY[$point][0];
  $size = ($MinMaxBids[1]-$bids) * $MinMaxScale;
  $color = $XY[$point][1] < $time
         ? getGradientColor('AAFFAA','FF6666',$MinMaxBids[1]-$MinMaxBids[0],$bids-$MinMaxBids[0])
         : 'yellow';
  $width = $MinMaxWidth[0] + $size;
  return array( $width, NULL, $color, NULL, NULL );
}

/**
 * Calculate graph point color
 *
 * @param string $s Start color
 * @param string $e End color
 * @param integer $max Point count
 * @param integer $id Point ID
 * @return array
 */
function getGradientColor ( $s, $e, $max, $id ) {
  if ($id < 0    ) $id = 0;
  if ($id > $max ) $id = $max;

  if (!is_array($s)) {
    $s = str_replace('#','',$s);
    $s = array( hexdec(substr($s,0,2)), hexdec(substr($s,2,2)), hexdec(substr($s,4,2)) );
  }
  if (!is_array($e)) {
    $e = str_replace('#','',$e);
    $e = array( hexdec(substr($e,0,2)), hexdec(substr($e,2,2)), hexdec(substr($e,4,2)) );
  }
  return (!$max)
       ? array( $s[0], $s[1], $s[2] )
       : array(
           round( max(0, $s[0] - ( (($e[0]-$s[0])/-$max) * $id )) ),
           round( max(0, $s[1] - ( (($e[1]-$s[1])/-$max) * $id )) ),
           round( max(0, $s[2] - ( (($e[2]-$s[2])/-$max) * $id )) )
         );
}