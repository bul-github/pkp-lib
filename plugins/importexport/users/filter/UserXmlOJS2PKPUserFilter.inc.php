<?php

/**
 * @file plugins/importexport/users/filter/UserXmlOJS2PKPUserFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserXmlOJS2PKPUserFilter
 * @ingroup plugins_importexport_users
 *
 * @brief Base class that converts a User OJS 2 XML document to a set of users
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

const OJS_DATE_FORMAT = 'Y-m-d H:i:s';
const SQL_SERVER_BIGINT_MAX = 9223372036854775807;

class UserXmlOJS2PKPUserFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('User XML OJS 2 user import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//

	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'PKPUsers';
	}

	//
	// Implement template methods from PersistableFilter
	//

	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.users.filter.UserXmlOJS2PKPUserFilter';
	}

	/**
	 * Handle a users element
	 * @param $node DOMElement
	 * @return User
	 */
	function parseUser($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$sendConfirmationEmail = $context->getData('sendConfirmationEmail') === '1';

		$defaultLocale = AppLocale::getLocale();

		// Create the data object
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->newDataObject();

		$password =  Validation::generatePassword();

		// Handle metadata in subelements
		for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$data = $n->textContent;
				switch($n->tagName) {
					case 'username':
						if (empty($data) or mb_strlen($data, 'UTF-8') > 32) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Username', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setUsername($data);
						break;
					case 'first_name':
						if (empty($data) or mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'FirstName', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setGivenName($data, $defaultLocale);
						break;
					case 'last_name':
						if (empty($data) or mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'LastName', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setFamilyName($data, $defaultLocale);
						break;
					case 'affiliation':
						if (mb_strlen($data, 'UTF-8') > 65535) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Affiliation', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setAffiliation($data, $n->getAttribute('locale'));
						break;
					case 'country':
						if (mb_strlen($data, 'UTF-8') > 90) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Country', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setCountry($data);
						break;
					case 'email':
						if (empty($data) or mb_strlen($data, 'UTF-8') > 255 or !filter_var($data, FILTER_VALIDATE_EMAIL)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Email', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setEmail($data);
						break;
					case 'url':
						if (mb_strlen($data, 'UTF-8') > 2047) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Url', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setUrl($data);
						break;
					case 'orcid':
						if (mb_strlen($data, 'UTF-8') > 65535) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'Orcid', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setOrcid($data);
						break;
					case 'phone':
						if (mb_strlen($data, 'UTF-8') > 32) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'phone', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setPhone($data);
						break;
					case 'billing_address':
						if (mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'billing_address', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setBillingAddress($data);
						break;
					case 'mailing_address':
						if (mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'mailing_address', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setMailingAddress($data);
						break;
					case 'biography':
						if (mb_strlen($data, 'UTF-8') > 65535) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'biography', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setBiography($data, $n->getAttribute('locale'));
						break;
					case 'gossip':
						if (mb_strlen($data, 'UTF-8') > 65535) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'gossip', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setGossip($data);
						break;
					case 'signature':
						if (mb_strlen($data, 'UTF-8') > 65535) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'signature', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setSignature($data, $n->getAttribute('locale'));
						break;
					case 'date_registered':
						DateTime::createFromFormat(OJS_DATE_FORMAT, $data);
						$errors = DateTime::getLastErrors();
						if (!empty($errors)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'date_registered', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setDateRegistered($data);
						break;
					case 'date_last_login':
						DateTime::createFromFormat(OJS_DATE_FORMAT, $data);
						$errors = DateTime::getLastErrors();
						if (!empty($errors)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'date_last_login', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setDateLastLogin($data);
						break;
					case 'date_last_email':
						DateTime::createFromFormat(OJS_DATE_FORMAT, $data);
						$errors = DateTime::getLastErrors();
						if (!empty($errors)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'date_last_email', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setDateLastEmail($data);
						break;
					case 'date_validated':
						DateTime::createFromFormat(OJS_DATE_FORMAT, $data);
						$errors = DateTime::getLastErrors();
						if (!empty($errors)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'date_validated', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setDateValidated($data);
						break;
					case 'inline_help':
						$data == 'true' ? $user->setInlineHelp(true) : $user->setInlineHelp(false);
						break;
					case 'auth_id':
						if ((ctype_digit($data) and $data > SQL_SERVER_BIGINT_MAX) or !ctype_digit($data)) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'auth_id', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setAuthId($data);
						break;
					case 'auth_string':
						if (mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'auth_string', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setAuthString($data);
						break;
					case 'disabled_reason':
						if (mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'disabled_reason', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setDisabledReason($data);
						break;
					case 'locales':
						if (mb_strlen($data, 'UTF-8') > 255) {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation', array('field' => 'locales', 'line' => $node->getLineNo())));
							return null;
						}
						$user->setLocales(preg_split('/:/', $data));
						break;
					case 'password':
						if ($n->getAttribute('encrypted') == 'md5') {
							$user->setMustChangePassword(true);
						}
						if ($n->getAttribute('is_disabled') == 'true') {
							$user->setIsDisabled(true);
						}
						$user->setPassword(Validation::encryptCredentials($user->getUserName(), $password));
						break;
				}
			}
		}

		// The existence of these user's fields is mandatory
		$nonNullUserAttributes = array();
		$nonNullUserAttributes['email'] = $user->getEmail();
		$nonNullUserAttributes['username'] = $user->getUsername();
		$nonNullUserAttributes['firstname'] = $user->getGivenName($defaultLocale);
		$nonNullUserAttributes['lastname'] = $user->getFamilyName($defaultLocale);

		foreach ($nonNullUserAttributes as $attribute => $value) {
			if (empty($value)) {
				$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.xmlDataValidation.missing', array('field' => $attribute, 'line' => $node->getLineNo())));
				return null;
			}
		}

		$userByUsername = $userDao->getByUsername($user->getUsername(), false);
		$userByEmail = $userDao->getUserByEmail($user->getEmail(), false);

		// Username already exists in OJS
		if ($userByUsername) {
			$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.username', array('username' => $user->getUsername())));
			$user = null;
			$sendConfirmationEmail = false;
		}
		// Email already exists in OJS
		elseif ($userByEmail) {
			$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.username', array('email' => $user->getEmail())));
			$user = null;
			$sendConfirmationEmail = false;
		}
		// All clear, username and email do not already exist in OJS
		else {
			$userDao->insertObject($user);

			// Insert reviewing interests, now that there is a userId.
			$interestNodeList = $node->getElementsByTagName('interests');

			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();
			$interests = array();

			for ($i = 0; $i < $interestNodeList->length; $i++) {
				array_push($interests, $interestNodeList->item($i)->textContent);
			}
			$interestManager->setInterestsForUser($user, $interests);

			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			// Extract user groups from the User XML and assign the user to those (existing) groups.
			// Note:  It is possible for a user to exist with no user group assignments so there is
			// no fatalError() as is the case with PKPAuthor import.
			$roleNodeList = $node->getElementsByTagName('role');
			if ($roleNodeList->length > 0) {
				for ($i = 0; $i < $roleNodeList->length; $i++) {
					$userGroup = null;
					$role = $roleNodeList->item($i)->getAttribute('type');
					switch (strtolower($role)) {
						case 'reviewer':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_REVIEWER);
							break;
						case 'author':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_AUTHOR);
							break;
						case 'reader':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_READER);
							break;
						case 'editor':
						case 'manager':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_MANAGER);
							break;
						case 'copyeditor':
						case 'layouteditor':
						case 'proofreader':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_ASSISTANT);
							break;
						case 'sectioneditor':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_SUB_EDITOR);
							break;
						case 'subscriptionmanager':
							$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_SUBSCRIPTION_MANAGER);
							break;
					}
					if ($userGroup) {
						$userGroupDao->assignUserToGroup($user->getId(), $userGroup->getId());
					}
				}
			}
		}

		if ($sendConfirmationEmail) {
			$this->sendEmail($context, $user, $password);
		}

		return $user;
	}

	/**
	 * Handle a singular element import.
	 * @param $node DOMElement
	 * @return array|null imported users
	 */
	function handleElement($node) {
		$users = array();
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$users = $this->handleChildElement($n);
			}
		}
		return $users;
	}

	/**
	 * Handle an element whose parent is the users element.
	 * @param $n DOMElement
	 * @return User|null imported user
	 */
	function handleChildElement($n) {
		$user = null;
		switch ($n->tagName) {
			case 'user':
				$user = $this->parseUser($n);
				break;
			default:
				fatalError('Unknown element ' . $n->tagName);
		}
		return $user;
	}
}

?>
