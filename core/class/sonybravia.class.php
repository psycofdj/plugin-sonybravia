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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class sonybravia extends eqLogic {

    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'sonybravia_update';
        $return['progress_file'] = jeedom::getTmpFolder('sonybravia') . '/dependance';
        if(strpos(exec('python3 --version'), 'Python 3') !== false){
            $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function dependancy_install() {
        if (file_exists(jeedom::getTmpFolder('sonybravia') . '/dependance')) {
            return;
        }
        self::dependancy_force();
        log::remove(__CLASS__ . '_update');
        return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    public static function dependancy_force() {
        log::add('sonybravia', 'info', 'Dependancy manual install');
        return array('script' => dirname(__FILE__) . '/../../resources/install_dependancy.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    public static function deamon_info() {
        $return = array();
        $return['log'] = 'sonybravia';
        $return['launchable'] = 'ok';
        $return['state'] = 'ok';
        $retour = true;
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            $retour &= sonybravia::tv_deamon_info($eqLogic->getLogicalId());
            if ($eqLogic->getConfiguration('psk') == ""){
                $return['launchable'] = 'nok';
            }
        }
        if (!$retour) {
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function deamon_stop() {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            $pidmac = str_replace(":", "", $eqLogic->getLogicalId());
            self::tv_deamon_stop($pidmac);
        }
    }

    public static function get_pidfile($mac) {
        return sprintf("%s/sonybravia_%s.pid",
                       jeedom::getTmpFolder('sonybravia'),
                       str_replace(":", "", $mac));
    }

    public static function tv_deamon_stop($mac) {
        log::add('sonybravia', 'info', 'Arrêt démon sonybravia : ' . $mac);
        $pid_file = self::get_pidfile($mac);
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        system::kill('sonybravia.py');
        sleep(1);
    }

    public static function tv_deamon_info($mac){
        $return = false;
        $pid_file = self::get_pidfile($mac);
        if (file_exists($pid_file)) {
            if (posix_getsid(trim(file_get_contents($pid_file)))) {
                $return = true;
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        return $return;
    }

    public static function tv_deamon_start($_ip, $_mac, $_psk, $_name) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            self::deamon_stop();
        }

        if ($deamon_info['launchable'] != 'ok') {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }

        $callback_base = network::getNetworkAccess('internal', 'proto:localhost:port:comp');
        $callback_path = "/plugins/sonybravia/core/php/jeesonybravia.php";
        $callback_url = sprintf("%s/%s", $callback_base, $callback_path);
        $pid_file = self::get_pidfile($_mac);
        $args = join(" ", array(
            "daemon",
            "--tv-ip="       . $_ip,
            "--tv-mac="      . $_mac,
            "--tv-pin="      . $_psk,
            "--jeedom-url="  . $callback_url,
            "--jeedom-key="  . jeedom::getApiKey('sonybravia'),
            "--jeedom-name=" . $_name,
            "--pid-file="    . $pid_file,
            "--log-level="   . log::convertLogLevel(log::getLogLevel('sonybravia'))
        ));
        $sonybravia_path = realpath(dirname(__FILE__) . '/../../resources');
        $cmd = sprintf("%s python3 %s/sonybravia.py %s", system::getCmdSudo(), $sonybravia_path, $args);

        log::add('sonybravia', 'info', 'Lancement démon sonybravia : ' . $cmd);
        $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1 &');
        $i = 0;
        while ($i < 30) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 30) {
            log::add('sonybravia', 'error', __('Impossible de lancer le démon sonybravia, vérifiez la log',__FILE__), 'unableStartDeamon');
            return false;
        }
        message::removeAll('sonybravia', 'unableStartDeamon');
        return true;
    }


    public static function tv_pairing($_ip, $_mac, $_psk, $_name) {
        if (($_ip == "") || ($_mac == "") || ($_name == "")) {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }

        $subcmd = ($_psk == "") ? "pair" : "confirm";
        $args = array(
            "--tv-ip="       . $_ip,
            "--tv-mac="      . $_mac,
            "--jeedom-name=" . $_name,
            "--log-level="   . log::convertLogLevel(log::getLogLevel('sonybravia'))
        );

        if ($_psk == "") {
            array_unshift($args, "pair");
        } else {
            array_unshift($args, "confirm");
            array_push($args, "--tv-pin=" . $_psk);
        }

        $sonybravia_path = realpath(dirname(__FILE__) . '/../../resources');
        $cmd = sprintf("%s python3 %s/sonybravia.py %s", system::getCmdSudo(), $sonybravia_path, join(" ", $args));

        log::add('sonybravia', 'info', 'Lancement association sonybravia : ' . $cmd);
        $status = 0;
        system($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1', $status);

        switch ($status) {
        case 1:
            log::add('sonybravia', 'error', __('Impossible de se connecter à la TV, vérifiez la configuration', __FILE__));
            break;
        case 2:
            log::add('sonybravia', 'error', __('Impossible d\'allumer automatiquement la TV, vérifiez la configuration', __FILE__));
            break;
        case 3:
            log::add('sonybravia', 'error', __('Impossible de s\'authentifier, vérifiez la configuration', __FILE__));
            break;
        case 4:
            log::add('sonybravia', 'error', __('Le periphérique est déjà appairé', __FILE__));
            break;
        default:
            break;
        }
        return $status;
    }


    public static function deamon_start() {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic)
        {
            self::tv_deamon_start($eqLogic->getConfiguration('ipadress'),
                                  $eqLogic->getLogicalId(),
                                  $eqLogic->getConfiguration('psk'),
                                  $eqLogic->getName());
            sleep(1);
        }
        return true;
    }
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

    public static function event() {
        $cmd = sonybraviaCmd::byId(init('id'));
        if (!is_object($cmd) || $cmd->getEqType() != 'sonybravia') {
            throw new \Exception(__('Commande ID virtuel inconnu, ou la commande n\'est pas de type virtuel : ', __FILE__) . init('id'));
        }
        $cmd->event(init('value'));
    }

    public static function deadCmd() {
        $return = array();
        foreach (eqLogic::byType('sonybravia') as $sonybravia){
            foreach ($sonybravia->getCmd() as $cmd) {
                preg_match_all("/#([0-9]*)#/", $cmd->getConfiguration('infoName',''), $matches);
                foreach ($matches[1] as $cmd_id) {
                    if (!cmd::byId(str_replace('#','',$cmd_id))){
                        $return[]= array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(),'help' => 'Nom Information','who'=>'#' . $cmd_id . '#');
                    }
                }
                preg_match_all("/#([0-9]*)#/", $cmd->getConfiguration('calcul',''), $matches);
                foreach ($matches[1] as $cmd_id) {
                    if (!cmd::byId(str_replace('#','',$cmd_id))){
                        $return[]= array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(),'help' => 'Calcul','who'=>'#' . $cmd_id . '#');
                    }
                }
            }
        }
        return $return;
    }


    public function postSave() {
        /*$refresh = $this->getCmd(null, 'refresh');
          if (!is_object($refresh)) {
          $refresh = new sonybraviaCmd();
          $refresh->setLogicalId('refresh');
          $refresh->setIsVisible(1);
          $refresh->setName(__('Rafraichir', __FILE__));
          }
          $refresh->setType('action');
          $refresh->setSubType('other');
          $refresh->setEqLogic_id($this->getId());
          $refresh->save();*/
    }

    public function copyFromEqLogic($_eqLogic_id) {
        $eqLogic = eqLogic::byId($_eqLogic_id);
        if (!is_object($eqLogic)) {
            throw new Exception(__('Impossible de trouver l\'équipement : ', __FILE__) . $_eqLogic_id);
        }
        if ($eqLogic->getEqType_name() == 'sonybravia') {
            throw new Exception(__('Vous ne pouvez importer la configuration d\'un équipement virtuel', __FILE__));
        }
        foreach ($eqLogic->getCategory() as $key => $value) {
            $this->setCategory($key, $value);
        }
        foreach ($eqLogic->getCmd() as $cmd_def) {
            $cmd_name = $cmd_def->getName();
            if ($cmd_name == __('Rafraichir')) {
                $cmd_name .= '_1';
            }
            $cmd = (new sonybraviaCmd())
                 ->setName($cmd_name)
                 ->setEqLogic_id($this->getId())
                 ->setIsVisible($cmd_def->getIsVisible())
                 ->setType($cmd_def->getType())
                 ->setUnite($cmd_def->getUnite())
                 ->setOrder($cmd_def->getOrder())
                 ->setDisplay('icon', $cmd_def->getDisplay('icon'))
                 ->setDisplay('invertBinary', $cmd_def->getDisplay('invertBinary'))
                 ->setConfiguration('listValue', $cmd_def->getConfiguration('listValue',''));
            foreach ($cmd_def->getTemplate() as $key => $value) {
                $cmd->setTemplate($key, $value);
            }
            $cmd->setSubType($cmd_def->getSubType());
            if ($cmd->getType() == 'info') {
                $cmd->setConfiguration('calcul', '#' . $cmd_def->getId() . '#')
                                                     ->setValue($cmd_def->getId());
            } else {
                $cmd->setValue($cmd_def->getValue())
                    ->setConfiguration('infoName', '#' . $cmd_def->getId() . '#');
            }
            try {
                $cmd->save();
            } catch (\Exception $e) {

            }
        }
        $this->save();
    }

    /*     * **********************Getteur Setteur*************************** */
}

class sonybraviaCmd extends cmd {
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */

    public function dontRemoveCmd() {
        if ($this->getLogicalId() == 'refresh') {
            return true;
        }
        return false;
    }

    public function preSave() {
        if ($this->getConfiguration('sonybraviaAction') == 1) {
            $actionInfo = sonybraviaCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
            if (is_object($actionInfo)) {
                $this->setId($actionInfo->getId());
            }
        }
    }

    public function postSave() {
        if ($this->getType() == 'info' && $this->getConfiguration('sonybraviaAction', 0) == '0' && $this->getConfiguration('calcul') != '') {
            $this->event($this->execute());
        }
    }

    public function execute($_options = null) {
        switch ($this->getType())
        {
        case 'info':
            if ($this->getConfiguration('sonybraviaAction', 0) == '0') {
                try {
                    $result = jeedom::evaluateExpression($this->getConfiguration('calcul'));
                    if ($this->getSubType() == 'numeric') {
                        if (is_numeric($result)) {
                            $result = number_format($result, 2);
                        } else {
                            $result = str_replace('"', '', $result);
                        }
                        if (strpos($result, '.') !== false) {
                            $result = str_replace(',', '', $result);
                        } else {
                            $result = str_replace(',', '.', $result);
                        }
                    }
                    return $result;
                } catch (\Exception $e) {
                    log::add('sonybravia', 'info', $e->getMessage());
                    return jeedom::evaluateExpression($this->getConfiguration('calcul'));
                }
            }
            break;
        case 'action':
            try {
                $sonybravia = $this->getEqLogic();
                $args = array(
                    "cmd",
                    "--tv-ip="       . $sonybravia->getConfiguration('ipadress'),
                    "--tv-mac="      . $sonybravia->getLogicalId(),
                    "--tv-pin="      . $sonybravia->getConfiguration('psk'),
                    "--jeedom-name=" . $sonybravia->getName(),
                    "--log-level="   . log::convertLogLevel(log::getLogLevel('sonybravia')),
                    "--command="     . $this->getLogicalId(),
                );
                $sonybravia_path = realpath(dirname(__FILE__) . '/../../resources');
                if($this->getConfiguration('param') !== ""){
                    array_push($args, "--param='" . $this->getConfiguration('param') . "'");
                }
                $cmd = sprintf("%s python3 %s/sonybravia.py %s", system::getCmdSudo(), $sonybravia_path, join(" ", $args));
                log::add('sonybravia', 'info', 'Lancement commande sonybravia : ' . $cmd);
                $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1 &');
            } catch (Exception $e) {
                log::add('sonybravia', 'info', $e->getMessage());
            }
            break;
        }
    }
}

