<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Cron/interfaces/interface.ilCronJobProvider.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Cron/classes/class.ilCronJob.php';

class ilAutoCourseReminderPlugin extends ilUserInterfaceHookPlugin implements ilCronJobProvider
{
    public const PLUGIN_ID = 'acrm';
    public const TABLE_SETTINGS = 'ui_uihk_acrm_settings';
    public const TABLE_ACTIVITY = 'ui_uihk_acrm_activity';
    public const TABLE_DISPATCH = 'ui_uihk_acrm_dispatch';
    public const TABLE_OPTOUT = 'ui_uihk_acrm_optout';

    protected function init(): void
    {
        parent::init();
        require_once __DIR__ . '/class.ilAutoCourseReminderRepository.php';
        require_once __DIR__ . '/class.ilAutoCourseReminderService.php';
        require_once __DIR__ . '/class.ilAutoCourseReminderCronJob.php';
        require_once __DIR__ . '/class.ilAutoCourseReminderMailAdapter.php';
    }

    public function getPluginName(): string
    {
        return 'AutoCourseReminder';
    }

    public function getCronJobInstances(): array
    {
        global $DIC;

        return [
            new ilAutoCourseReminderCronJob($this, $DIC->logger()->root())
        ];
    }

    public function getCronJobInstance(string $jobId): ilCronJob
    {
        foreach ($this->getCronJobInstances() as $job) {
            if ($job->getId() === $jobId) {
                return $job;
            }
        }

        throw new OutOfBoundsException(sprintf('Cron job "%s" introuvable', $jobId));
    }

    public function getRepository(): ilAutoCourseReminderRepository
    {
        return new ilAutoCourseReminderRepository($this->db, $this);
    }

    public function getService(): ilAutoCourseReminderService
    {
        global $DIC;

        return new ilAutoCourseReminderService($this, $DIC->logger()->root());
    }
}
