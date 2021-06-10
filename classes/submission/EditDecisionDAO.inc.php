<?php

/**
 * @file classes/submission/EditDecisionDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditDecisionDAO
 * @ingroup submission
 *
 * @brief Operations for retrieving and modifying editor decisions.
 */

// Bring in editor decision constants
// FIXME: These should be standardized into lib-pkp.
import('classes.workflow.EditorDecisionActionsManager');
import('lib.pkp.classes.submission.EditDecision');

class EditDecisionDAO extends DAO {

	/**
	 * Update the editor decision table.
	 * @param $submissionId int
	 * @param $editorDecision array
	 * @param $stageId int Optional STAGE_ID_...
	 * @param $reviewRound ReviewRound (optional)
	 */
	function updateEditorDecision($submissionId, $editorDecision, $stageId = null, $reviewRound = null) {
		if ($editorDecision['editDecisionId'] == null) {
			$this->update(
				sprintf(
					'INSERT INTO edit_decisions
					(submission_id, review_round_id, stage_id, round, editor_id, decision, date_decided)
					VALUES (?, ?, ?, ?, ?, ?, %s)',
					$this->datetimeToDB($editorDecision['dateDecided'])
				),
				[
					(int) $submissionId,
					is_a($reviewRound, 'ReviewRound') ? (int) $reviewRound->getId() : 0,
					is_a($reviewRound, 'ReviewRound') ? $reviewRound->getStageId() : (int) $stageId,
					is_a($reviewRound, 'ReviewRound') ? (int) $reviewRound->getRound() : REVIEW_ROUND_NONE,
					(int) $editorDecision['editorId'],
					$editorDecision['decision']
				]
			);
		}
	}

	/**
	 * Delete editing decisions by submission ID.
	 * @param $submissionId int
	 */
	function deleteDecisionsBySubmissionId($submissionId) {
		return $this->update(
			'DELETE FROM edit_decisions WHERE submission_id = ?',
			[(int) $submissionId]
		);
	}

	/**
	 * Get the editor decisions for a review round of a submission.
	 * @param $submissionId int Submission ID
	 * @param $stageId int Optional STAGE_ID_...
	 * @param $round int Optional review round number
	 * @param $editorId int Optional editor ID
	 * @return array List of information on the editor decisions:
	 * 	editDecisionId, reviewRoundId, stageId, round, editorId, decision, dateDecided
	 */
	function getEditorDecisions($submissionId, $stageId = null, $round = null, $editorId = null) {
		$params = [(int) $submissionId];
		if ($stageId) $params[] = (int) $stageId;
		if ($round) $params[] = (int) $round;
		if ($editorId) $params[] = (int) $editorId;

		$result = $this->retrieve(
			'SELECT	edit_decision_id, editor_id, decision,
				date_decided, review_round_id, stage_id, round
			FROM	edit_decisions
			WHERE	submission_id = ?
				' . ($stageId?' AND stage_id = ?':'') . '
				' . ($round?' AND round = ?':'') . '
				' . ($editorId?' AND editor_id = ?':'') . '
				ORDER BY date_decided ASC',
			$params
		);

		$decisions = [];
		foreach ($result as $row) {
			$decisions[] = [
				'editDecisionId' => $row->edit_decision_id,
				'reviewRoundId' => $row->review_round_id,
				'stageId' => $row->stage_id,
				'round' => $row->round,
				'editorId' => $row->editor_id,
				'decision' => $row->decision,
				'dateDecided' => $this->datetimeFromDB($row->date_decided)
			];
		}
		return $decisions;
	}

	/**
	 * Transfer the decisions for an editor.
	 * @param $oldUserId int
	 * @param $newUserId int
	 */
	function transferEditorDecisions($oldUserId, $newUserId) {
		$this->update(
			'UPDATE edit_decisions SET editor_id = ? WHERE editor_id = ?',
			[(int) $newUserId, (int) $oldUserId]
		);
	}

	/**
	 * Find any still valid pending revisions decision for the passed
	 * submission id. A valid decision is one that is not overriden by any
	 * other decision.
	 * @param $submissionId int
	 * @param $expectedStageId int
	 * @param $revisionDecision int SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS or SUBMISSION_EDITOR_DECISION_RESUBMIT
	 * @return mixed array or null
	 */
	function findValidPendingRevisionsDecision($submissionId, $expectedStageId, $revisionDecision = SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS) {
		$postReviewDecisions = array(SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION);
		$revisionDecisions = array(SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS, SUBMISSION_EDITOR_DECISION_RESUBMIT);
		if (!in_array($revisionDecision, $revisionDecisions)) return null;

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$editorDecisions = $editDecisionDao->getEditorDecisions($submissionId);
		$workingDecisions = array_reverse($editorDecisions);
		$pendingRevisionDecision = null;

		foreach ($workingDecisions as $decision) {
			if (in_array($decision['decision'], $postReviewDecisions)) {
				// Decisions at later stages do not override the pending revisions one.
				continue;
			} elseif ($decision['decision'] == $revisionDecision) {
				if ($decision['stageId'] == $expectedStageId) {
					$pendingRevisionDecision = $decision;
					// Only the last pending revisions decision is relevant.
					break;
				} else {
					// Both internal and external pending revisions decisions are
					// valid at the same time. Continue to search.
					continue;
				}

			} else {
				break;
			}
		}

		return $pendingRevisionDecision;
	}

	/**
	 * Find any file upload that's a revision and can be considered as
	 * a pending revisions decision response.
	 * @param $decision array
	 * @param $submissionId int
	 * @return boolean
	 */
	function responseExists($decision, $submissionId) {
		$stageId = $decision['stageId'];
		$round = $decision['round'];
		$sentRevisions = false;

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		$reviewRound = $reviewRoundDao->getReviewRound($submissionId, $stageId, $round);

		import('lib.pkp.classes.submission.SubmissionFile'); // Bring the file constants.
		$submissionFilesIterator = Services::get('submissionFile')->getMany([
			'reviewRoundIds' => [$reviewRound->getId()],
			'fileStages' => [SUBMISSION_FILE_REVIEW_REVISION],
		]);

		foreach ($submissionFilesIterator as $submissionFile) {
			if ($submissionFile->getData('updatedAt') > $decision['dateDecided']) {
				$sentRevisions = true;
				break;
			}
		}

		return $sentRevisions;
	}

	/**
	 * Get all pending revisions for all journals.
	 * @return array
	 */
	function getPendingRevisions() {
		// Stage 3 => evaluation process.
		// Decision 2 => asking for revisions.
		$params = array(3, 2);
		$result = $this->retrieve(
			'SELECT ed.*
			FROM edit_decisions ed
			INNER JOIN (
				SELECT edj.submission_id, MAX(edj.date_decided) as max_date
				FROM edit_decisions edj
				GROUP BY edj.submission_id
			) AS edj ON ed.submission_id = edj.submission_id AND ed.date_decided = edj.max_date
			WHERE ed.stage_id = ? and ed.decision = ?
			ORDER BY ed.submission_id',
			$params
		);
		$pendingRevisions = array();
		foreach ($result as $row) {
			$pendingRevisions[] = $this->_fromRow((array) $row);
		}
		return $pendingRevisions;
	}
	/**
	 * Internal function to return an edit decision object from a row.
	 * @param $row array
	 * @return EditDecision
	 */
	function _fromRow($row) {
		$editDecision = $this->newDataObject();
		$editDecision->setId($row['edit_decision_id']);
		$editDecision->setSubmissionId($row['submission_id']);
		$editDecision->setReviewRoundId($row['review_round_id']);
		$editDecision->setStageId($row['stage_id']);
		$editDecision->setRound($row['round']);
		$editDecision->setEditorId($row['editor_id']);
		$editDecision->setDecision($row['decision']);
		$editDecision->setDateDecided($this->datetimeFromDB($row['date_decided']));
		$editDecision->setDateReminded($this->datetimeFromDB($row['date_reminded']));
		return $editDecision;
	}

	/**
	 * Return a new edit decision data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new EditDecision();
	}

	/**
	 * Update an existing edit decision.
	 * @param $editDecision EditDecision
	 */
	function updateObject($editDecision)
	{
		$this->update(
			sprintf('UPDATE edit_decisions
				SET	submission_id = ?,
					review_round_id = ?,
					stage_id = ?,
					round = ?,
					editor_id = ?,
					decision = ?,
					date_decided = %s,
					date_reminded = %s
				WHERE edit_decision_id = ?',
				$this->datetimeToDB($editDecision->getDateDecided()),
				$this->datetimeToDB($editDecision->getDateReminded())
			),
			array(
				$editDecision->getSubmissionId(),
				$editDecision->getReviewRoundId(),
				$editDecision->getStageId(),
				$editDecision->getRound(),
				$editDecision->getEditorId(),
				$editDecision->getDecision(),
				(int)$editDecision->getId()
			)
		);
	}
}


