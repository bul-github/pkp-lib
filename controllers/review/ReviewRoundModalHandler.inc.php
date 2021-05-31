<?php

/**
 * @defgroup controllers_review Review Handlers
 */

/**
 * @file controllers/review/ReviewRoundModalHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundModalHandler
 * @ingroup controllers_review
 *
 * @brief Reviewer review round info handler.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class ReviewRoundModalHandler extends Handler {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_REVIEWER),
			array('viewRoundInfo', 'closeModal')
		);
	}

	//
	// Implement template methods from PKPHandler.
	//

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		$this->addPolicy(new RoleBasedHandlerOperationPolicy($request, array(ROLE_ID_REVIEWER), array('viewRoundInfo', 'close')));
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Public operations
	//

	/**
	 * Display the review round info modal.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewRoundInfo($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$reviewRoundNumber = $args['reviewRoundNumber'];

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($args['submissionId']);

		$currentUserId = $request->getUser()->getId();

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment = $reviewAssignmentDao->getReviewAssignment($args['reviewRoundId'], $currentUserId);

		$submissionReviewAssignments = $reviewAssignmentDao->getByUserId($currentUserId);
		$declinedReviewAssignments = array();
		foreach ($submissionReviewAssignments as $submissionReviewAssignment) {
			if ($submissionReviewAssignment->getDeclined() and $submission->getId() == $submissionReviewAssignment->getSubmissionId()) {
				$declinedReviewAssignments[] = $submissionReviewAssignment;
			}
		}

		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$reviewComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submission->getId(), $currentUserId, $reviewAssignment->getId());

		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$emailLogs = $submissionEmailLogDao->getBySenderId($submission->getId(), SUBMISSION_EMAIL_REVIEW_DECLINE, $currentUserId)->toArray();
		$i = 0;
		$declineEmail = null;
		foreach ($declinedReviewAssignments as $declinedReviewAssignment) {
			if (isset($emailLogs[$i]) && $reviewRoundNumber == $declinedReviewAssignment->getRound()) {
				$declineEmail = $emailLogs[$i];
			}
			$i++;
		}

		$displayFilesGrid = true;
		$lastReviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $currentUserId);
		if($lastReviewAssignment->getDeclined() == 1) {
			$displayFilesGrid = false;
		}

		$templateMgr->assign(array(
			'submission' => $submission,
			'reviewAssignment' => $reviewAssignment,
			'reviewRoundNumber' => $reviewRoundNumber,
			'reviewRoundId' => $args['reviewRoundId'],
			'reviewComments' => $reviewComments,
			'declineEmail' => $declineEmail,
			'displayFilesGrid' => $displayFilesGrid
		));

		return $templateMgr->fetchJson('controllers/modals/reviewRound/reviewRound.tpl');
	}
}

?>
