<?php

namespace Combodo\iTop\Integrity\Monitoring\Controller;

class CombodoMonitoringMetric {
    /** @var string $sName */
    private $sName;
    /** @var string $sDescription */
    private $sDescription;
    /** @var string $sValue */
    private $sValue;

    /**
     * CombodoMonitoringMetric constructor.
     * @param string $sName
     * @param string $sDescription
     * @param string $sValue
     */
    public function __construct(string $sName, string $sDescription, string $sValue) {
        $this->sName = $sName;
        $this->sDescription = $sDescription;
        $this->sValue = $sValue;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->sName;
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return $this->sDescription;
    }

    /**
     * @return string
     */
    public function getValue(): string {
        return $this->sValue;
    }


}