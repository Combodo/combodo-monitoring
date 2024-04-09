<?php
/*
 * Copyright (C) 2013-2021 Combodo SARL
 * This file is part of iTop.
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 */

namespace Combodo\iTop\Monitoring\Model;

class MonitoringMetric {
    /** @var string $sName */
    private $sName;
    /** @var string $sDescription */
    private $sDescription;
    /** @var int $sValue */
    private $sValue;
    /** @var string[] $aLabels */
    private $aLabels;

    /**
     * MonitoringMetric constructor.
     * @param string $sName
     * @param string $sDescription
     * @param int $sValue
     * @param string[] $aLabels
     */
    public function __construct(string $sName, string $sDescription, int $sValue, $aLabels=[]) {
        $this->sName = $sName;
        $this->sDescription = $sDescription;
        $this->sValue = $sValue;
        $this->aLabels = $aLabels;
    }

    public function GetName() : string {
        return $this->sName;
    }

    public function GetDescription() : string {
        return $this->sDescription;
    }

    public function SetDescription(string $sDescription): void {
        $this->sDescription = $sDescription;
    }

    public function GetValue() : int {
        return $this->sValue;
    }

	public function setValue(int $sValue): void {
		$this->sValue = $sValue;
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

    public function AddAllLabels($aNewLabels){
        foreach ($aNewLabels as $sLabelName => $sLabelValue){
            $this->aLabels[$sLabelName] = $sLabelValue;
        }
    }

    public function __toString() {
        return sprintf("name:%s\nvalue:%s\ndescription:%s\nlabels:%s\n",
            $this->sName,
            $this->sValue,
            $this->sDescription,
            var_export($this->aLabels, true)
        );
    }
}
