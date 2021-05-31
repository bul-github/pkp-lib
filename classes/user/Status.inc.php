<?php
/**
 * @defgroup user User
 * Implements data objects and DAOs concerned with managing user accounts.
 */

/**
 * @file classes/user/Status.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Status
 * @ingroup user
 * @see StatusDAO
 *
 * @brief Basic class describing user statuses existing in the system.
 */

import('lib.pkp.classes.identity.Identity');

class Status extends DataObject {
	/**
	 * Get status ID.
	 * @return int
	 */
	function getId() {
		return $this->getData('id');
	}

	/**
	 * Set status ID.
	 * @param $id int
	 */
	function setId($id) {
		$this->setData('id', $id);
	}

	/**
	 * Get locale key.
	 * @return string
	 */
	function getLocaleKey() {
		return $this->getData('localeKey');
	}

	/**
	 * Set locale key.
	 * @param $localeKey string
	 */
	function setLocaleKey($localeKey) {
		$this->setData('localeKey', $localeKey);
	}

	/**
	 * Get sequence.
	 * @return int
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence.
	 * @param $sequence int
	 */
	function setSequence($sequence) {
		$this->setData('sequence', $sequence);
	}
}

