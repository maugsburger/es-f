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
* @package Plugins
* @subpackage NextAuction
* @desc German language definitions
*/

defined('_ESF_OK') || die('No direct call allowed.');

# -----------------------------------------------------------------------------
#
# Don't "htmlspecialchar" your translation,
# just type '<text>' and NOT '&lt;text&gt;'!
#
# line format (php array):
# 'english text' => 'translated text',
#
# -----------------------------------------------------------------------------
Translation::Define('NEXTAUCTION', array(
# -----------------------------------------------------------------------------

# $1: plugin name
'PluginName'                => 'Plugin %1$s: Nächstendende Auktion',

# %1$s: item anchor; %2$s: auction name; %3$s: remaining time; %4$s: category
'Message'                   => 'html:Nächste Auktion: <a href="#%1$s">%2$s</a> '
                              .'(%4$s) &nbsp; <tt>[</tt>%3$s<tt>]</tt>',

# -----------------------------------------------------------------------------
));