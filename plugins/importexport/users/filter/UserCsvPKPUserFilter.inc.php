<?php

/**
 * @file plugins/importexport/users/filter/UserCsvPKPUserFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCsvPKPUserFilter
 * @ingroup plugins_importexport_users
 *
 * @brief Base class that converts a User CSV document to a set of users
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class UserCsvPKPUserFilter extends NativeImportExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('User CSV 2 user import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from Filter
	//

	/**
	 * @see Filter::process()
	 * @param $document DOMDocument|string
	 * @return array Array of imported documents
	 */
	function &process(&$document) {
		$importedObjects = null;
		if (is_string($document)) {
			$lines = str_getcsv($document, "\n"); //parse the rows
			$importedObjects = array();
			array_shift($lines);
			foreach ($lines as $line) {
				$fields = str_getcsv($line, ";"); //parse the items in rows
				$object = $this->parseUser($fields);
				if ($object) {
					$importedObjects[] = $object;
				}
			}
		}
		return $importedObjects;
	}

	//
	// Implement template methods from PersistableFilter
	//

	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.users.filter.UserCsvPKPUserFilter';
	}

	/**
	 * Handle an array of user properties
	 * @param $fields array
	 * @return array Array of User objects
	 */
	function parseUser($fields) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$sendConfirmationEmail = $context->getData('sendConfirmationEmail') === '1';

		$line = join(";", $fields);

		$defaultLocale = AppLocale::getLocale();

		// Create the data object
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->newDataObject();

		$interests = array();
		$roles = array();
		$username = '';

		// Handle user properties in array
		for ($i = 0; $i <= sizeof($fields); $i++) {
			switch($i) {
				case 0:
					if (empty($fields[$i]) or mb_strlen($fields[$i], 'UTF-8') > 255) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Username', 'line' => $line)));
						return null;
					}
					$username = $fields[$i];
					break;
				case 1:
					if (empty($fields[$i]) or mb_strlen($fields[$i], 'UTF-8') > 255) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'FirstName', 'line' => $line)));
						return null;
					}
					$user->setGivenName($fields[$i], $defaultLocale);
					break;
				case 2:
					if (empty($fields[$i]) or mb_strlen($fields[$i], 'UTF-8') > 255) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'LastName', 'line' => $line)));
						return null;
					}
					$user->setFamilyName($fields[$i], $defaultLocale);
					break;
				case 3:
					if (mb_strlen($fields[$i], 'UTF-8') > 65535) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Affiliation', 'line' => $line)));
						return null;
					}
					$user->setAffiliation($fields[$i], 'fr_CA');
					break;
				case 4:
					if (empty($fields[$i]) or mb_strlen($fields[$i], 'UTF-8') > 255 or !filter_var($fields[$i], FILTER_VALIDATE_EMAIL)) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Email', 'line' => $line)));
						return null;
					}
					$user->setEmail($fields[$i]);
					break;
				case 5:
					if (mb_strlen($fields[$i], 'UTF-8') > 65535) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Interests', 'line' => $line)));
						return null;
					}
					$interests = explode(';', $fields[$i]);
					break;
				case 6:
					if (mb_strlen($fields[$i], 'UTF-8') > 255) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'MailingAddress', 'line' => $line)));
						return null;
					}
					$user->setMailingAddress($fields[$i]);
					break;
				case 8:
					if (strlen($fields[$i]) > 2) {
						$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Country', 'line' => $line)));
						return null;
					}
					$user->setCountry($fields[$i]);
					break;
				case 9:
				case 10:
				case 11:
				case 12:
				case 13:
					if (!empty($fields[$i])) {
						if (in_array($fields[$i], array('author', 'reviewer', 'reader', 'manager', 'assistant', 'subeditor', 'subscriptionmanager'))) {
							$roles[] = $fields[$i];
						}
						else {
							$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.dataValidation', array('field' => 'Role', 'line' => $line)));
							return null;
						}
					}
					break;
			}
		}

		$password = Validation::generatePassword();
		$user->setUsername($username);
		$user->setMustChangePassword(true);
		$user->setPassword(Validation::encryptCredentials($user->getUserName(), $password));

		$userByUsername = $userDao->getByUsername($user->getUsername(), false);
		$userByEmail = $userDao->getUserByEmail($user->getEmail(), false);

		// Username already exists in OJS
		// TODO: Maybe give it another try by adding a number at the end of username...
		if ($userByUsername) {
			$this->addWarning($context,'userImportWarnings', __('plugins.importexport.users.userImportWarning.username', array('username' => $user->getUsername())));
			$user = null;
			$sendConfirmationEmail = false;
		}
		// Email already exists in OJS
		elseif ($userByEmail) {
			$this->addWarning($context, 'userImportWarnings', __('plugins.importexport.users.userImportWarning.email', array('email' => $user->getEmail())));
			$user = null;
			$sendConfirmationEmail = false;
		}
		// All clear, username and email do not already exist in OJS
		else {
			$userDao->insertObject($user);

			// Insert reviewing interests, now that there is a userId.
			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();
			$interestManager->setInterestsForUser($user, $interests);

			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			// Extract user groups from the CSV and assign the user to those (existing) groups.
			// Note:  It is possible for a user to exist with no user group assignments so there is
			// no fatalError() as is the case with PKPAuthor import.
			foreach (array_unique($roles) as $role) {
				$userGroup = null;
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
					case 'manager':
						$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_MANAGER);
						break;
					case 'assistant':
						$userGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_ASSISTANT);
						break;
					case 'subeditor':
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

		if ($sendConfirmationEmail) {
			$this->sendEmail($context, $user, $password);
		}

		return $user;
	}
}

?>
