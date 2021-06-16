<?php

/**
 * @file controllers/review/linkAction/ResetReviewDecisionLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ResetReviewDecisionLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to reset the reviewer's decision (declined).
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ResetReviewDecisionLinkAction extends LinkAction {

	/**
	 * Constructor
	 *
	 * @param $request Request
	 * @param $reviewAssignment ReviewAssignment The review assignment to show information about.
	 * @param $submission Submission The reviewed submission.
	 * @param $user User The user.
	 */
	function __construct($request, $reviewAssignment, $submission, $user) {
		$url = $request->getRouter()->url(
			$request,
			null,
			'grid.users.reviewer.ReviewerGridHandler',
			'resetReviewDecision',
			null,
			array(
				'stageId' => $submission->getStageId(),
				'reviewAssignmentId' => $reviewAssignment->getId(),
				'submissionId' => $submission->getId()
			)
		);

		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		$ajaxModal = new RemoteActionConfirmationModal(
			$request->getSession(),
			__('editor.review.resetDecision'),
			sprintf('%s:%s', __('editor.review'), $submission->getLocalizedTitle()),
			$url,
			'modal_information'
		);

		parent::__construct( 'resetReviewDecision', $ajaxModal, __('editor.review.resetReviewDecision'));
	}
}

?>
