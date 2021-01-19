<?php

namespace Combodo\iTop\Integrity\Monitoring\Controller;

class CombodoMonitoringMetric {
    /** @var string $sName */
    private $sName;
    /** @var string $sDescription */
    private $sDescription;
    /** @var string $sValue */
    private $sValue;
    /** @var string[] $aLabels */
    private $aLabels;

    /**
     * CombodoMonitoringMetric constructor.
     * @param string $sName
     * @param string $sDescription
     * @param string $sValue
     * @param string[] $aLabels
     */
    public function __construct(string $sName, string $sDescription, string $sValue, $aLabels=[]) {
        $this->sName = $sName;
        $this->sDescription = $sDescription;
        $this->sValue = $sValue;
        $this->aLabels = $aLabels;
    }

    /**
     * @return string
     */
    public function GetName() {
        return $this->sName;
    }

    /**
     * @return string
     */
    public function GetDescription() {
        return $this->sDescription;
    }

    /**
     * @param string $sDescription
     */
    public function SetDescription(string $sDescription): void {
        $this->sDescription = $sDescription;
    }

    /**
     * @return string
     */
    public function GetValue() {
        return $this->sValue;
    }

    /**
     * @return string[]
     */
    public function GetLabels() {
        return $this->aLabels;
    }

    public function AddLabel($sKey, $sValue){
        $this->aLabels[$sKey] = $sValue;
    }

}