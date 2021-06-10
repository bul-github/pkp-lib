<?php

/**
 * @file classes/task/SubmissionReminder.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionReminder
 * @ingroup tasks
 *
 * @brief Class to perform automated reminders for authors.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

const AUTHOR_REVISIONS_REMIND_AUTO = 'AUTHOR_REVISIONS_REMIND_AUTO';

class SubmissionReminder extends ScheduledTask {

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	function getName() {
		return __('admin.scheduledTask.revisionsReminder');
	}

	/**
	 * Send the automatic revisions reminder to the author.
	 * @param $pendingRevision EditDecision
	 * @param $submission Submission
	 * @param $context Context
 	 * @param $editDecisionDao EditDecisionDAO
	 * @return bool
	 */
	function sendReminder($pendingRevision, $submission, $context, $editDecisionDao) {
		$submissionId = $pendingRevision->getSubmissionId();
		$stageId = $pendingRevision->getStageId();

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $stageId);

		$userService = Services::get('user');

		$editors = array();
		foreach ($editorsStageAssignments as $stageAssignment) {
			$editors[] = $userService->get((int) $stageAssignment->getUserId());
		}

		if (!isset($editors) || empty($editors)) {
			return false;
		}

		$application = PKPApplication::get();
		$request = $application->getRequest();
		$dispatcher = $application->getDispatcher();
		$urlArgs = array('submissionId' => $submissionId);
		$submissionUrl = $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $urlArgs);

		$contactEmail = $context->getContactEmail();
		$contactName = $context->getContactName();
		$editorialContactSignature = $contactName . "\n" . $context->getLocalizedName();
		$primaryLocale = $context->getPrimaryLocale();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, AUTHOR_REVISIONS_REMIND_AUTO, $primaryLocale, $context, false);

		foreach ($editors as $editor) {
			$email->addRecipient($editor->getEmail(), $editor->getFullName());
		}

		$email->setReplyTo(null);
		$email->addCc($contactEmail, $contactName);
		$email->setFrom($contactEmail, $contactName);

		$params = array(
			'submissionUrl' => $submissionUrl,
			'editorialContactSignature' => $editorialContactSignature
		);

		$email->assignParams($params);
		$email->send();

		$pendingRevision->setDateReminded(Core::getCurrentDate());
		$editDecisionDao->updateObject($pendingRevision);

		return true;
	}

	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	function executeActions() {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$contextDao = Application::getContextDAO();
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');

		// Only retrieves the current pending revisions.
		$pendingRevisions = $editDecisionDao->getPendingRevisions();

		$revisionsReminderDays = 0;
		$submission = null;
		$context = null;

		foreach ($pendingRevisions as $pendingRevision) {
			// Avoid reminding the same revisions request many times.
			if ($pendingRevision->getDateReminded() !== null) {
				continue;
			}

			$submissionId = $pendingRevision->getSubmissionId();
			if ($submission == null || $submission->getId() != $submissionId) {
				unset($submission);
				$submission = $submissionDao->getById($submissionId);

				// Avoid review assignments without submission in database.
				if (!$submission) {
					continue;
				}
			}

			if ($submission->getStatus() != STATUS_QUEUED) {
				continue;
			}

			$contextId = $submission->getContextId();
			if ($context == null || $context->getId() != $contextId) {
				unset($context);
				$context = $contextDao->getById($contextId);

				$revisionsReminderDays = $context->getSetting('numDaysBeforeRevisionsReminder');
			}

			if ($revisionsReminderDays >= 1) {
				$dateDecided = strtotime($pendingRevision->getDateDecided());
				$checkDate = $dateDecided + (60 * 60 * 24 * $revisionsReminderDays);

				if (time() > $checkDate) {
					$this->sendReminder($pendingRevision, $submission, $context, $editDecisionDao);
				}
			}
		}

		return true;
	}
}

?>
