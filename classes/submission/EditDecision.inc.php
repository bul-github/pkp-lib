<?php

/**
 * @file classes/submission/EditDecision.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditDecision
 * @ingroup submission
 * @see EditDecisionDAO
 *
 * @brief Basic class describing an edit decision.
 */

class EditDecision extends DataObject {

	/**
	 * Get ID of submission.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Set ID of submission.
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		$this->setData('submissionId', $submissionId);
	}

	/**
	 * Get ID of review round.
	 * @return int
	 */
	function getReviewRoundId() {
		return $this->getData('reviewRoundId');
	}

	/**
	 * Set ID of review round.
	 * @param $reviewRoundId int
	 */
	function setReviewRoundId($reviewRoundId) {
		$this->setData('reviewRoundId', $reviewRoundId);
	}

	/**
	 * Get ID of stage.
	 * @return int
	 */
	function getStageId() {
		return $this->getData('stageId');
	}

	/**
	 * Set ID of stage.
	 * @param $stageId int
	 */
	function setStageId($stageId) {
		$this->setData('stageId', $stageId);
	}

	/**
	 * Get round.
	 * @return int
	 */
	function getRound() {
		return $this->getData('round');
	}

	/**
	 * Set round.
	 * @param $round int
	 */
	function setRound($round) {
		$this->setData('round', $round);
	}

	/**
	 * Get ID of editor.
	 * @return int
	 */
	function getEditorId() {
		return $this->getData('editorId');
	}

	/**
	 * Set ID of editor.
	 * @param $editorId int
	 */
	function setEditorId($editorId) {
		$this->setData('editorId', $editorId);
	}

	/**
	 * Get decision.
	 * @return int
	 */
	function getDecision() {
		return $this->getData('decision');
	}

	/**
	 * Set decision.
	 * @param $decision int
	 */
	function setDecision($decision) {
		$this->setData('decision', $decision);
	}

	/**
	 * Get the decision date.
	 * @return string
	 */
	function getDateDecided() {
		return $this->getData('dateDecided');
	}

	/**
	 * Set the decision date.
	 * @param $dateDecided string
	 */
	function setDateDecided($dateDecided) {
		$this->setData('dateDecided', $dateDecided);
	}

	/**
	 * Get the reminded date.
	 * @return string
	 */
	function getDateReminded() {
		return $this->getData('dateReminded');
	}

	/**
	 * Set the reminded date.
	 * @param $dateReminded string
	 */
	function setDateReminded($dateReminded) {
		$this->setData('dateReminded', $dateReminded);
	}
}

?>
