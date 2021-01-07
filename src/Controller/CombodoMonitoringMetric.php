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
    public function getName() {
        return $this->sName;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->sDescription;
    }

    /**
     * @param string $sDescription
     */
    public function setDescription(string $sDescription): void {
        $this->sDescription = $sDescription;
    }

    /**
     * @return string
     */
    public function getValue() {
        return $this->sValue;
    }

    /**
     * @return string[]
     */
    public function getLabels() {
        return $this->aLabels;
    }

    public function addLabel($sKey, $sValue){
        $this->aLabels[$sKey] = $sValue;
    }
}