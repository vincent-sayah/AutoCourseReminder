<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Mail/classes/class.ilMail.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Mail/classes/class.ilMailNotification.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/User/classes/class.ilObjUser.php';

class ilAutoCourseReminderInternalMailNotification extends ilMailNotification
{
    private int $recipientUserId = 0;
    private string $mailSubject = '';
    private string $mailBody = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function sendReminder(int $recipientUserId, string $subject, string $body): void
    {
        $this->recipientUserId = $recipientUserId;
        $this->mailSubject = $subject;
        $this->mailBody = $body;

        $this->initLanguage($recipientUserId);
        $this->getLanguage()->loadLanguageModule('mail');
        $this->initMail();
        $this->setSubject($subject);
        $this->setBody($body);
        $this->appendBody("\n\n");
        $this->getMail()->appendInstallationSignature(true);
        $this->sendMail([$recipientUserId]);
    }
}
