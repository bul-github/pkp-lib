<?php

/**
 * @file plugins/importexport/native/filter/NativeImportExportFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts between Native XML documents and DataObjects
 */

import('lib.pkp.classes.filter.PersistableFilter');

class NativeImportExportFilter extends PersistableFilter {
	/** @var NativeImportExportDeployment */
	var $_deployment;

	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		parent::__construct($filterGroup);
	}


	//
	// Deployment management
	//
	/**
	 * Set the import/export deployment
	 * @param $deployment NativeImportExportDeployment
	 */
	function setDeployment($deployment) {
		$this->_deployment = $deployment;
	}

	/**
	 * Get the import/export deployment
	 * @return NativeImportExportDeployment
	 */
	function getDeployment() {
		return $this->_deployment;
	}

	function addWarning($context, $category, $message) {
		$userImportWarnings = $context->getData($category);
		if (is_null($userImportWarnings)) {
			$userImportWarnings = array();
		}
		array_push($userImportWarnings,  $message);
		$context->setData($category, $userImportWarnings);
	}

	function sendEmail($context, $user, $password) {
		// Send email with new password
		import('lib.pkp.classes.mail.MailTemplate');
		$mail = new MailTemplate('USER_REGISTER');
		$mail->setReplyTo($context->getSetting('contactEmail'), $context->getSetting('contactName'));
		$mail->setFrom($context->getSetting('contactEmail'), $context->getSetting('contactName'));
		$mail->assignParams(array(
			'userFullName' => $user->getFullName(),
			'username' => $user->getUsername(),
			'password' => $password,
			'contextName' => $context->getLocalizedName()
		));
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->send();
		unset($mail);
	}
}


