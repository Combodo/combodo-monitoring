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
    /** @var string $sValue */
    private $sValue;
    /** @var string[] $aLabels */
    private $aLabels;

    /**
     * MonitoringMetric constructor.
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

    public function AddAllLabels($aNewLabels){
        foreach ($aNewLabels as $sLabelName => $sLabelValue){
            $this->aLabels[$sLabelName] = $sLabelValue;
        }
    }

}