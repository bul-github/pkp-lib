<?php

/**
 * @file controllers/review/linkAction/ReviewRoundModalLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundModalLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to show a modal with the information about a review round.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ReviewRoundModalLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionId int the ID of the submission to present link for
	 * @param $reviewRoundId int the ID of the review round
	 * @param $reviewRoundNumber int the round number to show information about
	 */
	function __construct($request, $submissionId, $reviewRoundId, $reviewRoundNumber) {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);

		$submissionTitle = $submission->getLocalizedTitle();
		$title = __('reviewer.submission.reviewRound.info.modal.title', array('reviewRoundNumber' => $reviewRoundNumber, 'submissionTitle' => $submissionTitle));

		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$ajaxModal = new AjaxModal(
			$dispatcher->url(
				$request,
				ROUTE_COMPONENT,
				null,
				'review.ReviewRoundModalHandler',
				'viewRoundInfo',
				null,
				array(
					'submissionId' => $submissionId,
					'reviewRoundId' => $reviewRoundId,
					'reviewRoundNumber' => $reviewRoundNumber
				)
			),
			$title,
			'modal_information'
		);

		// Configure the link action.
		parent::__construct(
			'reviewRoundInfo', $ajaxModal
		);
	}
}

?>
