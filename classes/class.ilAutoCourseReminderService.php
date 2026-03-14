<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Utilities/classes/class.ilUtil.php';

class ilAutoCourseReminderService
{
    private ilAutoCourseReminderRepository $repository;
    private ilAutoCourseReminderMailAdapter $mailAdapter;

    public function __construct(
        private readonly ilAutoCourseReminderPlugin $plugin,
        private readonly ilLogger $logger
    ) {
        $this->repository = $plugin->getRepository();
        $this->mailAdapter = new ilAutoCourseReminderMailAdapter($logger);
    }

    public function run(ilCronJobResult $result): ilCronJobResult
    {
        require_once ACR_ILIAS_ROOT . '/components/ILIAS/ILIASObject/classes/class.ilObject.php';
        require_once ACR_ILIAS_ROOT . '/components/ILIAS/Tracking/classes/class.ilLPStatusWrapper.php';
        require_once ACR_ILIAS_ROOT . '/components/ILIAS/User/classes/class.ilObjUser.php';
        require_once ACR_ILIAS_ROOT . '/components/ILIAS/Course/classes/class.ilCourseParticipants.php';

        $rules = $this->repository->getRules();
        $sent = 0;
        $checked = 0;

        if ($this->repository->getSetting('token_secret', '') === '') {
            $this->repository->setSetting('token_secret', bin2hex(random_bytes(24)));
        }

        foreach ($rules as $rule) {
            if (empty($rule['active'])) {
                continue;
            }

            $courseRefId = (int) $rule['course_ref_id'];
            if ($courseRefId <= 0) {
                continue;
            }

            $courseObjId = (int) ilObject::_lookupObjectId($courseRefId);
            if ($courseObjId <= 0) {
                $this->logger->warning('[AutoCourseReminder] Cours introuvable pour la règle ' . $rule['rule_key']);
                continue;
            }

            $participants = ilCourseParticipants::_getInstanceByObjId($courseObjId);
            $members = array_map('intval', (array) $participants->getMembers());
            $inProgressUsers = array_map('intval', (array) ilLPStatusWrapper::_getInProgress($courseObjId));
            $completedUsers = array_map('intval', (array) ilLPStatusWrapper::_getCompleted($courseObjId));
            $stepCompletedUsers = [];

            if ($rule['rule_type'] === 'step' && (int) $rule['step_ref_id'] > 0) {
                $stepObjId = (int) ilObject::_lookupObjectId((int) $rule['step_ref_id']);
                if ($stepObjId > 0) {
                    $stepCompletedUsers = array_map('intval', (array) ilLPStatusWrapper::_getCompleted($stepObjId));
                }
            }

            foreach ($members as $userId) {
                ++$checked;

                if (!in_array($userId, $inProgressUsers, true)) {
                    continue;
                }
                if (in_array($userId, $completedUsers, true)) {
                    continue;
                }
                if ($this->repository->isOptedOut($userId, $courseRefId)) {
                    continue;
                }

                $activity = $this->repository->getActivity($userId, $courseRefId);
                if ($activity === null) {
                    continue;
                }

                if (!$this->ruleAppliesToUser($rule, $userId, $activity, $stepCompletedUsers)) {
                    continue;
                }

                $dispatch = $this->repository->getDispatch($userId, (string) $rule['rule_key']);
                $sentCount = (int) ($dispatch['sent_count'] ?? 0);
                if ($sentCount >= (int) $rule['max_reminders']) {
                    continue;
                }
                if (!$this->canSendAgain($rule, $dispatch)) {
                    continue;
                }

                $courseTitle = (string) ilObject::_lookupTitle($courseObjId);
                $user = new ilObjUser($userId);
                $mailData = $this->buildMessage($rule, $user, $courseRefId, $courseTitle, $activity);

                if ($this->mailAdapter->sendToUser(
                    $userId,
                    $mailData['subject'],
                    $mailData['body'],
                    $mailData['from_address'],
                    $mailData['from_name']
                )) {
                    ++$sent;
                    $this->repository->saveDispatch($userId, (string) $rule['rule_key'], $sentCount + 1, (string) $mailData['reason']);
                }
            }
        }

        $result->setStatus(ilCronJobResult::STATUS_OK);
        $result->setMessage(sprintf('AutoCourseReminder: %d vérifications, %d email(s) envoyés.', $checked, $sent));

        return $result;
    }

    public function trackCurrentRequest(): void
    {
        global $DIC;

        $userId = (int) $DIC->user()->getId();
        if ($userId <= 0 || $userId === ANONYMOUS_USER_ID) {
            return;
        }

        $refId = (int) ($_GET['ref_id'] ?? 0);
        if ($refId <= 0) {
            return;
        }

        $courseRefId = $this->resolveCourseRefIdFromRefId($refId);
        if ($courseRefId <= 0) {
            return;
        }

        $this->repository->touchCourseActivity($userId, $courseRefId);
    }

    public function handleOptOutRequest(): void
    {
        $action = (string) ($_GET['acr_action'] ?? '');
        if ($action !== 'disable') {
            return;
        }

        $userId = (int) ($_GET['acr_u'] ?? 0);
        $courseRefId = (int) ($_GET['acr_c'] ?? 0);
        $token = (string) ($_GET['acr_t'] ?? '');

        if ($userId <= 0 || $courseRefId <= 0 || $token === '') {
            return;
        }

        if (!$this->isValidOptOutToken($userId, $courseRefId, $token)) {
            $this->logger->warning('[AutoCourseReminder] Lien de désactivation invalide pour user_id=' . $userId . ', course_ref_id=' . $courseRefId);
            ilUtil::redirect($this->buildCourseGotoUrl($courseRefId));
        }

        $this->repository->saveOptOut($userId, $courseRefId);
        $this->logger->info('[AutoCourseReminder] Désactivation enregistrée pour user_id=' . $userId . ', course_ref_id=' . $courseRefId);
        ilUtil::redirect($this->buildCourseGotoUrl($courseRefId));
    }

    private function resolveCourseRefIdFromRefId(int $refId): int
    {
        global $DIC;

        require_once ACR_ILIAS_ROOT . '/components/ILIAS/ILIASObject/classes/class.ilObject.php';

        $type = (string) ilObject::_lookupType($refId, true);
        if ($type === 'crs') {
            return $refId;
        }

        $path = (array) $DIC->repositoryTree()->getPathId($refId);
        foreach (array_reverse($path) as $pathRefId) {
            if ((string) ilObject::_lookupType((int) $pathRefId, true) === 'crs') {
                return (int) $pathRefId;
            }
        }

        return 0;
    }

    private function ruleAppliesToUser(array $rule, int $userId, array $activity, array $stepCompletedUsers): bool
    {
        $delayDays = (int) $rule['delay_days'];
        $nowTs = time();
        $firstSeenTs = strtotime((string) $activity['first_seen']);
        $lastSeenTs = strtotime((string) $activity['last_seen']);
        if ($firstSeenTs === false || $lastSeenTs === false) {
            return false;
        }

        if ($rule['rule_type'] === 'step') {
            if ((int) $rule['step_ref_id'] <= 0) {
                return false;
            }
            if (in_array($userId, $stepCompletedUsers, true)) {
                return false;
            }
            return ($nowTs - $firstSeenTs) >= ($delayDays * 86400);
        }

        return ($nowTs - $lastSeenTs) >= ($delayDays * 86400);
    }

    private function canSendAgain(array $rule, ?array $dispatch): bool
    {
        if ($dispatch === null) {
            return true;
        }

        $lastSent = strtotime((string) ($dispatch['last_sent'] ?? ''));
        if ($lastSent === false) {
            return true;
        }

        return (time() - $lastSent) >= (((int) $rule['repeat_every_days']) * 86400);
    }

    private function buildMessage(array $rule, ilObjUser $user, int $courseRefId, string $courseTitle, array $activity): array
    {
        $settings = $this->repository->getAllSettings();
        $fromAddress = trim((string) ($settings['mail_from'] ?? 'noreply@example.org'));
        $fromName = trim((string) ($settings['mail_from_name'] ?? 'ILIAS'));
        $subject = (string) $rule['subject'];
        $body = trim((string) $rule['body']);

        $lastSeen = (string) $activity['last_seen'];
        $inactivityDays = max(0, (int) floor((time() - strtotime($lastSeen)) / 86400));
        $disableUrl = $this->buildDisableUrl((int) $user->getId(), $courseRefId);

        if ($body === '') {
            if ($rule['rule_type'] === 'step') {
                $body = "Bonjour {{FIRSTNAME}},\n\nVous avez commencé le cours \"{{COURSE_TITLE}}\" mais l'étape ciblée n'est pas encore complétée.\n\nVous pouvez reprendre votre progression ici : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}";
            } else {
                $body = "Bonjour {{FIRSTNAME}},\n\nAucune activité n'a été détectée dans le cours \"{{COURSE_TITLE}}\" depuis {{INACTIVITY_DAYS}} jour(s).\n\nVous pouvez reprendre votre progression ici : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}";
            }
        }

        $optOutBlock = '';
        if (!empty($rule['allow_opt_out'])) {
            $optOutBlock = "\nPour ne plus recevoir ces rappels pour ce cours : " . $disableUrl;
        }

        $replacements = [
            '{{FIRSTNAME}}' => (string) $user->getFirstname(),
            '{{LASTNAME}}' => (string) $user->getLastname(),
            '{{LOGIN}}' => (string) $user->getLogin(),
            '{{COURSE_TITLE}}' => $courseTitle,
            '{{COURSE_URL}}' => $this->buildCourseGotoUrl($courseRefId),
            '{{DISABLE_URL}}' => $disableUrl,
            '{{OPTOUT_BLOCK}}' => $optOutBlock,
            '{{INACTIVITY_DAYS}}' => (string) $inactivityDays,
            '{{MAIL_FROM_NAME}}' => $fromName,
        ];

        return [
            'subject' => strtr($subject, $replacements),
            'body' => strtr($body, $replacements),
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'reason' => (string) $rule['rule_type'],
        ];
    }

    private function buildDisableUrl(int $userId, int $courseRefId): string
    {
        $base = $this->getBaseUrl();
        $token = $this->buildOptOutToken($userId, $courseRefId);

        return $base . '/ilias.php?baseClass=ilDashboardGUI&acr_action=disable&acr_u=' . $userId . '&acr_c=' . $courseRefId . '&acr_t=' . rawurlencode($token);
    }

    private function buildCourseGotoUrl(int $courseRefId): string
    {
        $url = $this->getBaseUrl() . '/goto.php?target=crs_' . $courseRefId;
        $clientId = defined('CLIENT_ID') ? (string) CLIENT_ID : (string) $this->repository->getSetting('client_id', '');
        if ($clientId !== '') {
            $url .= '&client_id=' . rawurlencode($clientId);
        }

        return $url;
    }

    private function getBaseUrl(): string
    {
        $base = defined('ILIAS_HTTP_PATH') ? (string) ILIAS_HTTP_PATH : '';
        if ($base === '') {
            $base = (string) $this->repository->getSetting('base_url', '');
        }

        return rtrim($base, '/');
    }

    private function buildOptOutToken(int $userId, int $courseRefId): string
    {
        $secret = (string) $this->repository->getSetting('token_secret', '');
        if ($secret === '') {
            $secret = 'acrm-default-secret';
        }

        return hash_hmac('sha256', $userId . ':' . $courseRefId, $secret);
    }

    private function isValidOptOutToken(int $userId, int $courseRefId, string $token): bool
    {
        return hash_equals($this->buildOptOutToken($userId, $courseRefId), $token);
    }
}
