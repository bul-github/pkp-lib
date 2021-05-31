<?php

/**
 * @file classes/user/StatusDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatusDAO
 * @ingroup user
 * @see Status
 *
 * @brief Operations for retrieving user statuses.
 */

import('lib.pkp.classes.user.Status');

class StatusDAO extends DAO {

	/**
	 * Retrieve all statuses.
	 * @return DAOResultFactory
	 */
	function getAll() {
		$result = $this->retrieve(
		'SELECT * FROM statuses ORDER BY seq'
		);
		return new DAOResultFactory($result, $this, '_returnStatusFromRow');
	}

	/**
	 * Retrieve all statuses for select options.
	 * @return array
	 */
	function getAllForSelectOptions() {
		$statuses = array();
		foreach ($this->getAll()->toArray() as $status) {
			$statuses[$status->getId()] = $status->getLocaleKey();
		}
		return $statuses;
	}

	/**
	 * Internal function to return a Status object from a row.
	 * @param $row array
	 * @return Status
	 */
	function _returnStatusFromRow($row) {
		$status = $this->newDataObject();
		$status->setId($row['status_id']);
		$status->setLocaleKey($row['locale_key']);
		$status->setSequence($row['seq']);
		return $status;
	}

	/**
	 * Construct a new Status object.
	 * @return Status
	 */
	function newDataObject() {
		return new Status();
	}
}

?>
