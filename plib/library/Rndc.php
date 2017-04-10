<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class Modules_SlaveDnsManager_Rndc
{
    private function _call(Modules_SlaveDnsManager_Slave $slave, $arguments, $verbose = false)
    {
        $arguments = join(' ', [
            "-b \"{$slave->getMasterIp()}\"",
            "-s \"{$slave->getIp()}\"",
            "-p \"{$slave->getPort()}\"",
            "-y \"{$slave->getRndcKeyId()}\"",
            "-c \"{$slave->getConfigPath()}\"",
            $arguments,
        ]);

        if (pm_ProductInfo::isWindows()) {
            $command = '"' . PRODUCT_ROOT . '\dns\bin\rndc.exe"';
        } else {
            $command = '/usr/sbin/rndc';
        }
        exec("{$command} {$arguments} 2>&1", $out, $code);
        $output = implode("\n", $out);

        if ($verbose) {
            if ($code != 0) {
                throw new pm_Exception("$command $arguments\n$output\n\nError code: $code");
            }
            return $output;
        }

        if ($code != 0) {
            // Cannot send output header due to possible API-RPC calls
            error_log("Error code $code: $output");
        }

        return $code == 0;
    }

    public function addZone($domain, Modules_SlaveDnsManager_Slave $slave = null)
    {
        $slaves = null === $slave ? Modules_SlaveDnsManager_Slave::getList() : [$slave];
        foreach ($slaves as $slave) {
            $this->_call($slave, "addzone \"{$domain}\" \"{$slave->getRndcClass()}\" \"{$slave->getRndcView()}\"" .
                " \"{ type slave; file \\\"{$domain}\\\"; masters { {$slave->getMasterIp()}; }; };\"");
        }
    }

    public function updateZone($domain, Modules_SlaveDnsManager_Slave $slave = null)
    {
        $slaves = null === $slave ? Modules_SlaveDnsManager_Slave::getList() : [$slave];
        foreach ($slaves as $slave) {
            $result = $this->_call($slave, "refresh \"{$domain}\" \"{$slave->getRndcClass()}\" \"{$slave->getRndcView()}\"");
            if (false === $result) {
                $this->addZone($domain, $slave);
            }
        }
    }

    public function deleteZone($domain, Modules_SlaveDnsManager_Slave $slave = null)
    {
        $slaves = null === $slave ? Modules_SlaveDnsManager_Slave::getList() : [$slave];
        foreach ($slaves as $slave) {
            $this->_call($slave, "delzone \"{$domain}\" \"{$slave->getRndcClass()}\" \"{$slave->getRndcView()}\"");
        }
    }

    public function checkStatus(Modules_SlaveDnsManager_Slave $slave)
    {
        return $this->_call($slave, "status", true);
    }
}
