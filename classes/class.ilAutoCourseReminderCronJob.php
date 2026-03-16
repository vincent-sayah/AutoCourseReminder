<?php

declare(strict_types=1);

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Cron/classes/class.ilCronJob.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Cron/classes/class.ilCronJobResult.php';

use ILIAS\Cron\Schedule\CronJobScheduleType;

class ilAutoCourseReminderCronJob extends ilCronJob
{
    public function __construct(
        private readonly ilAutoCourseReminderPlugin $plugin,
        private readonly ilLogger $logger
    ) {
    }

    public function getId(): string
    {
        return 'autocoursereminder_job';
    }

    public function getTitle(): string
    {
        return 'AutoCourseReminder';
    }

    public function getDescription(): string
    {
        return 'Envoie des rappels automatiques basés sur l’inactivité ou la non-complétion d’une étape dans un cours.';
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function getValidScheduleTypes(): array
    {
        return [
            CronJobScheduleType::SCHEDULE_TYPE_DAILY,
            CronJobScheduleType::SCHEDULE_TYPE_IN_DAYS,
            CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS,
        ];
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_DAILY;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function isManuallyExecutable(): bool
    {
        return true;
    }

    public function run(): ilCronJobResult
    {
        $this->logger->info('[AutoCourseReminder] Début du job cron');
        $result = new ilCronJobResult();
        $result = $this->plugin->getService()->run($result);
        $this->logger->info('[AutoCourseReminder] ' . $result->getMessage());

        return $result;
    }
}
