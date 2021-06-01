<?php

/**
 * @file classes/user/PrivateNotesDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrivateNotesDAO
 * @ingroup user
 * @see PrivateNote
 *
 * @brief Operations for retrieving and modifying user private notes.
 */

import('lib.pkp.classes.user.PrivateNote');

class PrivateNotesDAO extends DAO {

	/**
	 * Retrieve a user private note value.
	 *
	 * @param $journalId int
	 * @param $userId int
	 *
	 * @return string
	 */
	function getNote($journalId, $userId) {
		$params = array((int) $journalId, (int) $userId);
		$result = $this->retrieve('SELECT * FROM private_notes WHERE context_id = ? AND user_id = ?', $params);
		$factory = new DAOResultFactory($result, $this, '_returnPrivateNoteFromRow');
		$privateNote = $factory->toIterator()->current();
		$note = "";
		if ($privateNote) {
			$note = $privateNote->getNote();
		}
		return $note;
	}

	/**
	 * Internal function to return a PrivateNote object from a row.
	 * @param $row array
	 * @return PrivateNote
	 */
	function _returnPrivateNoteFromRow($row) {
		$privateNote = $this->newDataObject();
		$privateNote->setId($row['private_note_id']);
		$privateNote->setContextId($row['context_id']);
		$privateNote->setUserId($row['user_id']);
		$privateNote->setNote($row['note']);
		return $privateNote;
	}

	/**
	 * Construct a new PrivateNote object.
	 * @return PrivateNote
	 */
	function newDataObject() {
		return new PrivateNote();
	}

	/**
	 * Set a user private note value.
	 *
	 * @param $journalId int
	 * @param $userId int
	 * @param $note string
	 */
	function setNote($journalId, $userId, $note) {
		$params = array($note, (int) $journalId, (int) $userId);

		$oldNote = $this->getNote($journalId, $userId);
		if (isset($oldNote)) {
			$this->update('UPDATE private_notes SET note = ? WHERE context_id = ? AND user_id = ?', $params);
			return;
		}

		$this->update('INSERT INTO private_notes (note, context_id, user_id) VALUES (?, ?, ?)', $params);
	}
}

?>
