<?php

/**
 * @file classes/user/PrivateNote.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrivateNote
 * @ingroup user
 * @see PrivateNotesDAO
 *
 * @brief Basic class describing user private note existing in the system.
 */

class PrivateNote extends DataObject {
	/**
	 * Get private note ID.
	 * @return int
	 */
	function getId() {
		return $this->getData('id');
	}

	/**
	 * Set private note ID.
	 * @param $id int
	 */
	function setId($id) {
		$this->setData('id', $id);
	}

	/**
	 * Get private note context ID.
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set private note context ID.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get private note user ID.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set private note user ID.
	 * @param $userId int
	 */
	function setUserId($userId) {
		$this->setData('userId', $userId);
	}


	/**
	 * Get private note value.
	 * @return string
	 */
	function getNote() {
		return $this->getData('note');
	}

	/**
	 * Set private note value.
	 * @param $note string
	 */
	function setNote($note) {
		$this->setData('note', $note);
	}
}

