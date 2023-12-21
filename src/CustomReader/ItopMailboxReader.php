<?php
/*
 * Copyright (C) 2022 Combodo SARL
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

namespace Combodo\iTop\Monitoring\CustomReader;

use Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use DBObjectSearch;
use DBObjectSet;
use Exception;
use MetaModel;

class ItopMailboxReader implements CustomReaderInterface
{
	private $aMetricConf;
	private $sMetricName;

	public function __construct($sMetricName, $aMetricConf)
	{
		$this->aMetricConf = $aMetricConf;
		$this->sMetricName = 'itop_mailbox_failed_connection_count';
	}

	/**
	 * {@inheritDoc}
	 */
	public function GetMetrics(): ?array
	{
		$aMetrics = [];
		if (MetaModel::IsValidClass('MailInboxBase'))
		{
			$oSearch = new DBObjectSearch('MailInboxBase');
			$oSearch->AddCondition('active', 'yes');
			$oSet = new DBObjectSet($oSearch);
			$result = 0;
			while($oInbox = $oSet->Fetch())
			{
				try
				{
					//N°5177 - Failure to connect to a mailbox crashes the monitoring
					//when mailbox is not reachable there is annoying print_r in IMAPEmailSource that breaks monitoring output format (prometheus usually)
					ob_start();
					// When OVH is crashed, the opening/reading of the IMAP mailboxes can be very slow AND
					// if the monitoring page does not reply within a few seconds, the monitoring considers the whole target as DOWN
					// So let put some "relatively" short timeouts here
					imap_timeout(IMAP_OPENTIMEOUT, 1);
					imap_timeout(IMAP_READTIMEOUT, 1);
					$oInbox->GetEmailSource(); // Will try to connect to the mailbox and throw an error in case of failure
				}
				catch(Exception $e)
				{
					\IssueLog::Warning("Mailbox connection issue", null, [ 'exception' => $e ]);
					$result++;
				} finally {
					ob_end_clean();
				}
			}
			$aMetrics[] = new MonitoringMetric(
				$this->sMetricName,
				'Failed connections to a mailbox',
				$result,
				[],
			);
		}

		return $aMetrics;
	}
}
