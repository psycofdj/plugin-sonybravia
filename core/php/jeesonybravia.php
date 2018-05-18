<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
header('Content-type: application/json');
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";



if (!jeedom::apiAccess(init('apikey'), 'sonybravia')) {
 http_response_code(403);
 echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (sonybravia)', __FILE__);
 die();
}

$eqlogic = sonybravia::byLogicalId(init('mac'), 'sonybravia');
if (!is_object($eqlogic)) {
    http_response_code(404);
	die();
}

$array_recu = "";
foreach ($_POST as $key => $value) {
	$array_recu = $array_recu . $key . '=' . $value . ' / ';
	$cmd = $eqlogic->getCmd('info',$key);
	if (is_object($cmd)) {
		$cmd->event($value);
	}
}
log::add('sonybravia', 'debug', 'Reception de : ' . $array_recu);

