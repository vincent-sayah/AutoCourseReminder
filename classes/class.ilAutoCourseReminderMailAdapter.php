<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

class ilAutoCourseReminderMailAdapter
{
    public function __construct(private readonly ?ilLogger $logger = null)
    {
    }

    public function sendToUser(
        int $recipientUserId,
        string $subject,
        string $body,
        string $fromAddress,
        string $fromName = 'ILIAS'
    ): bool {
        require_once ACR_ILIAS_ROOT . '/components/ILIAS/User/classes/class.ilObjUser.php';
        require_once __DIR__ . '/class.ilAutoCourseReminderInternalMailNotification.php';

        try {
            $recipient = new ilObjUser($recipientUserId);
            $login = trim((string) $recipient->getLogin());
            if ($login === '') {
                $this->log('warning', '[AutoCourseReminder] Envoi annulé : login vide pour user_id=' . $recipientUserId);
                return false;
            }

            $notification = new ilAutoCourseReminderInternalMailNotification();
            $notification->sendReminder($recipientUserId, $subject, $body);

            $this->log('info', '[AutoCourseReminder] Notification ILIAS créée pour user_id=' . $recipientUserId . ' login=' . $login . ' sujet="' . $subject . '"');
            return true;
        } catch (Throwable $e) {
            $this->log('error', '[AutoCourseReminder] Echec création notification ILIAS pour user_id=' . $recipientUserId . ' : ' . $e->getMessage());
            return false;
        }
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        switch ($level) {
            case 'error':
                $this->logger->error($message);
                return;
            case 'warning':
                $this->logger->warning($message);
                return;
            default:
                $this->logger->info($message);
        }
    }
}
