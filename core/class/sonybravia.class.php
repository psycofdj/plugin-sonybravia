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

    /*
    * Fonction jeedom permettant de connaitre l'état des dépendances
    */
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

    /*
    * Fonction Jeedom permettant de lancer l'installation des dépendances
    */
    public static function dependancy_install() {
        if (file_exists(jeedom::getTmpFolder('sonybravia') . '/dependance')) {
            return;
        }
        self::dependancyForce();
        log::remove(__CLASS__ . '_update');
        return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    public static function dependancyForce() {
        log::add('sonybravia', 'info', 'Dependancy manual install');
        return array('script' => dirname(__FILE__) . '/../../resources/install_dependancy.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    /*
    * Fonction Jeedom permettant de connaitre l'état du deamon
    */
    public static function deamon_info() {
        $return = array();
        $etatDeamon = true;
        $return['log'] = 'sonybravia';
        $return['launchable'] = 'ok';
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            if (!(sonybravia::tvDeamonInfo($eqLogic->getLogicalId()))){
                $etatDeamon = false;
            }
            if ($eqLogic->getConfiguration('psk') === "1234"){
                $return['launchable'] = 'nok';
            }
        }
        $return['state'] = ($etatDeamon) ? 'ok' : 'nok';
        return $return;
    }

    /*
    * Fonction Jeedom d'arrêt du deamon
    */
    public static function deamon_stop() {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            self::tvDeamonStop(str_replace(":", "", $eqLogic->getLogicalId()));
        }
    }

    public static function tvDeamonStop($macAddress) {
        log::add('sonybravia', 'info', 'Arrêt démon sonybravia : ' . $macAddress);
        $pidFile =  jeedom::getTmpFolder('sonybravia') .'/sonybravia_'.$macAddress.'.pid';
        if (file_exists($pidFile)) {
            system::kill(intval(trim(file_get_contents($pidFile))));
        }
        system::kill('sonybravia.py');
        sleep(1);
    }

    public static function tvDeamonInfo($macAddress){
        $pidFile = jeedom::getTmpFolder('sonybravia') .'/sonybravia_' . str_replace(":", "", $macAddress) . '.pid';
        if (file_exists($pidFile)) {
            if (posix_getsid(trim(file_get_contents($pidFile)))) {
                return true;
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pidFile . ' 2>&1 > /dev/null');
            }
        }
        return false;
    }

    public static function tvDeamonPin($ipAddress, $macAddress, $psk, $cookie = false){
        $deamonInfo = self::deamon_info();
        if ($deamonInfo['state'] === 'ok') {
            self::deamon_stop();
        }
        if ($deamonInfo['launchable'] !== 'ok') {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $sonybraviaPath = realpath(dirname(__FILE__) . '/../../resources');
        if ($cookie === true){
            $cmd = 'sudo /usr/bin/python3 ' . $sonybraviaPath . '/sonybravia_cookie.py';
            $cmd .= ' --tvip ' . $ipAddress;
            $cmd .= ' --mac ' . $macAddress;
            $cmd .= ' --psk ' . $psk;
            $cmd .= ' --jeedomadress ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/sonybravia/core/php/jeesonybravia.php';
            $cmd .= ' --apikey ' . jeedom::getApiKey('sonybravia');
            log::add('sonybravia', 'info', 'Récupération du pin : ' . $cmd);
            $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1 &');
            message::removeAll('sonybravia', 'unableStartDeamon');
            return true;
        }
        log::add('sonybravia', 'error', __('Veuillez sélectionner le mode pin'), 'unableStartDeamon');
        return false;
    }

    public static function tvDeamonStart($iAddressp, $macAddress, $psk, $cookie = false){
        $i = 0;
        $deamonInfo = self::deamon_info();
        if ($deamonInfo['state'] === 'ok') {
            self::deamon_stop();
        }
        if ($deamonInfo['launchable'] !== 'ok') {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $sonybraviaPath = realpath(dirname(__FILE__) . '/../../resources');

        $cmd = ($cookie === true) ? 'sudo /usr/bin/python3 ' . $sonybraviaPath . '/sonybravia_cookie.py' : 'sudo /usr/bin/python3 ' . $sonybraviaPath . '/sonybravia.py';
        $cmd .= ' --tvip ' . $iAddressp;
        $cmd .= ' --mac ' . $macAddress;
        $cmd .= ' --psk ' . $psk;
        $cmd .= ' --jeedomadress ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/sonybravia/core/php/jeesonybravia.php';
        $cmd .= ' --apikey ' . jeedom::getApiKey('sonybravia');
        log::add('sonybravia', 'info', 'Lancement démon sonybravia : ' . $cmd);
        $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1 &');
        while ($i <= 30) {
            $deamonInfo = self::deamon_info();
            if ($deamonInfo['state'] === 'ok') {
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

    /*
    * Fonction Jeedom permettant de démarer le deamon
    */
    public static function deamon_start() {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            self::tvDeamonStart($eqLogic->getConfiguration('ipadress'), $eqLogic->getLogicalId(),$eqLogic->getConfiguration('psk'),$eqLogic->getConfiguration('pin'));
            sleep(1);
        }
        return true;
    }

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
                foreach ($matches[1] as $cmdId) {
                    if (!cmd::byId(str_replace('#','',$cmdId))){
                            $return[]= array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(),'help' => 'Nom Information','who'=>'#' . $cmdId . '#');
                        }
                }
                preg_match_all("/#([0-9]*)#/", $cmd->getConfiguration('calcul',''), $matches);
                foreach ($matches[1] as $cmdId) {
                    if (!cmd::byId(str_replace('#','',$cmdId))){
                            $return[]= array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(),'help' => 'Calcul','who'=>'#' . $cmdId . '#');
                        }
                }
            }
        }
        return $return;
    }

    public function postSave() {
    }

    public function copyFromEqLogic($eqLogicId) {
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new \Exception(__('Impossible de trouver l\'équipement : ', __FILE__) . $eqLogicId);
        }
        if ($eqLogic->getEqType_name() == 'sonybravia') {
            throw new \Exception(__('Vous ne pouvez importer la configuration d\'un équipement virtuel', __FILE__));
        }
        foreach ($eqLogic->getCategory() as $key => $value) {
            $this->setCategory($key, $value);
        }
        foreach ($eqLogic->getCmd() as $cmdDef) {
            $cmdName = $cmdDef->getName();
            if ($cmdName == __('Rafraichir')) {
                $cmdName .= '_1';
            }
            $cmd = (new sonybraviaCmd())
                ->setName($cmdName)
                ->setEqLogic_id($this->getId())
                ->setIsVisible($cmdDef->getIsVisible())
                ->setType($cmdDef->getType())
                ->setUnite($cmdDef->getUnite())
                ->setOrder($cmdDef->getOrder())
                ->setDisplay('icon', $cmdDef->getDisplay('icon'))
                ->setDisplay('invertBinary', $cmdDef->getDisplay('invertBinary'))
                ->setConfiguration('listValue', $cmdDef->getConfiguration('listValue',''));
            foreach ($cmdDef->getTemplate() as $key => $value) {
                $cmd->setTemplate($key, $value);
            }
            $cmd->setSubType($cmdDef->getSubType());
            if ($cmd->getType() == 'info') {
                $cmd->setConfiguration('calcul', '#' . $cmdDef->getId() . '#')
                    ->setValue($cmdDef->getId());
            } else {
                $cmd->setValue($cmdDef->getValue())
                    ->setConfiguration('infoName', '#' . $cmdDef->getId() . '#');
            }
            try {
                $cmd->save();
            } catch (\Exception $e) {

            }
        }
        $this->save();
    }
}

class sonybraviaCmd extends cmd {
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

    public function execute($options = null) {
        switch ($this->getType()) {
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
                                    $sonyBravia = $this->getEqLogic();
                                    $sonyBraviaPath = realpath(dirname(__FILE__) . '/../../resources');
                                    $cmd = 'sudo /usr/bin/python3 ' . $sonyBraviaPath . '/sonybravia_send.py';
                                    $cmd .= ' --tvip ' . $sonyBravia->getConfiguration('ipadress');
                                    $cmd .= ' --mac ' . $sonyBravia->getLogicalId();
                                    $cmd .= ' --psk ' . $sonyBravia->getConfiguration('psk');
                                    $cmd .= ' --command ' . $this->getLogicalId();
                                    if($this->getConfiguration('param') !== ""){
                                        $cmd .= " --commandparam '" . $this->getConfiguration('param') . "'";
                                    }
                                    $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia') . ' 2>&1 &');
                                } catch (\Exception $e) {
                                    log::add('sonybravia', 'info', $e->getMessage());
                }
                break;
        }
    }
}
