<?php

/**
 * @file classes/user/form/UserFormHelper.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserFormHelper
 * @ingroup user_form
 *
 * @brief Helper functions for shared user form concerns.
 */

class UserFormHelper {
	/**
	 * Constructor
	 */
	function __construct() {
	}

	/**
	 * Assign role selection content to the template manager.
	 * @param $templateMgr PKPTemplateManager
	 * @param $request PKPRequest
	 */
	function assignRoleContent($templateMgr, $request) {
		// Need the count in order to determine whether to display
		// extras-on-demand for role selection in other contexts.
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll(true)->toArray();
		$contextsWithUserRegistration = array();
		foreach ($contexts as $context) {
			if (!$context->getData('disableUserReg')) {
				$contextsWithUserRegistration[] = $context;
			}
		}
		$templateMgr->assign(array(
			'contexts' => $contexts,
			'showOtherContexts' => !$request->getContext() || count($contextsWithUserRegistration)>1,
		));

		// Expose potential self-registration user groups to template
		$authorUserGroups = $reviewerUserGroups = $readerUserGroups = array();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		foreach ($contexts as $context) {
			if ($context->getData('disableUserReg')) continue;
			$reviewerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_REVIEWER)->toArray();
			$authorUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR)->toArray();
			$readerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_READER)->toArray();
		}
		$templateMgr->assign(array(
			'reviewerUserGroups' => $reviewerUserGroups,
			'authorUserGroups' => $authorUserGroups,
			'readerUserGroups' => $readerUserGroups,
		));
	}

	/**
	 * Save role elements of an executed user form.
	 * @param $form Form The form from which to fetch elements
	 * @param $user User The current user
	 * @param $fromRegisterForm Boolean If the method has been called from the Register Form
	 */
	function saveRoleContent($form, $user, $fromRegisterForm = false) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll(true);
		while ($context = $contexts->next()) {
			if ($context->getData('disableUserReg')) continue;

			foreach (array(
				array(
					'roleId' => ROLE_ID_REVIEWER,
					'formElement' => 'reviewerGroup'
				),
				array(
					'roleId' => ROLE_ID_AUTHOR,
					'formElement' => 'authorGroup'
				),
				array(
					'roleId' => ROLE_ID_READER,
					'formElement' => 'readerGroup'
				),
			) as $groupData) {
				$groupFormData = (array) $form->getData($groupData['formElement']);
				$userGroups = $userGroupDao->getByRoleId($context->getId(), $groupData['roleId']);
				while ($userGroup = $userGroups->next()) {
					if (!$userGroup->getPermitSelfRegistration()) continue;

					$groupId = $userGroup->getId();
					$inGroup = $userGroupDao->userInGroup($user->getId(), $groupId);
					if (!$inGroup && array_key_exists($groupId, $groupFormData)) {
						$userGroupDao->assignUserToGroup($user->getId(), $groupId, $context->getId());
						// Send an email to journals principal contact if user is asking for the Reviewer role.
						if ($groupData['formElement'] == 'reviewerGroup') {
							$contactName = $context->getData('contactName');
							$contactEmail = $context->getData('contactEmail');
							import('lib.pkp.classes.mail.MailTemplate');
							$mail = new MailTemplate('ASK_FOR_REVIEWER_ROLE');
							$mail->setReplyTo(null);
							$mail->addRecipient($contactEmail, $contactName);
							$mail->assignParams(array(
								'contextName' => $context->getLocalizedName(),
								'contactName' => $contactName,
								'userFullName' => $user->getFullName(),
								'userEmail' => $user->getEmail(),
							));
							$mail->send();
						}
					} elseif ($inGroup && !array_key_exists($groupId, $groupFormData) && !$fromRegisterForm) {
						$userGroupDao->removeUserFromGroup($user->getId(), $groupId, $context->getId());
					}
				}
			}
		}
	}
}


