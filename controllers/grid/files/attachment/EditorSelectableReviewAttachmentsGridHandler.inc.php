<?php
/**
 * @file controllers/grid/files/attachment/EditorSelectableReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorSelectableReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachments
 *
 * @brief Selectable review attachment grid requests (editor's perspective).
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class EditorSelectableReviewAttachmentsGridHandler extends SelectableFileListGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		// Pass in null stageId to be set in initialize from request var.
		parent::__construct(
			// This grid lists all review round files, but creates attachments
			new ReviewGridDataProvider(SUBMISSION_FILE_REVIEW_EDITOR_ATTACHMENT, false, true),
			null,
			FILE_GRID_ADD|FILE_GRID_DELETE|FILE_GRID_VIEW_NOTES|FILE_GRID_EDIT
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow')
		);

		// Set the grid title.
		$this->setTitle('grid.reviewAttachments.send.title');
	}

	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		// Nothing should be selected by default to avoid potential manipulation errors.
		return false;
	}

	/**
	 * @copydoc SelectableFileListGridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedAttachments';
	}
}

