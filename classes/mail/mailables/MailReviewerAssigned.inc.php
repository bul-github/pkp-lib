<?php

/**
 * @file classes/mail/mailables/MailReviewerAssigned.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailReviewerAssigned
 * @ingroup mail_mailables
 *
 * @brief An email send to a reviewer when they are assigned
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class MailReviewerAssigned extends Mailable
{
    use Recipient;
    use Sender;

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());
    }
}
