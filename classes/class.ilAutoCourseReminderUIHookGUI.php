<?php

declare(strict_types=1);

class ilAutoCourseReminderUIHookGUI extends ilUIHookPluginGUI
{
    private static bool $requestServicesHandled = false;

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        /** @var ilAutoCourseReminderPlugin $plugin */
        $plugin = $this->getPluginObject();

        if (!self::$requestServicesHandled) {
            self::$requestServicesHandled = true;
            $service = $plugin->getService();
            $service->handleOptOutRequest();
            $service->trackCurrentRequest();
        }

        $courseSettingsGUI = new ilAutoCourseReminderCourseSettingsGUI($plugin);
        return $courseSettingsGUI->getHookResponse($a_comp, $a_part, $a_par);
    }

    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []): void
    {
        if ($a_part !== 'sub_tabs' || !isset($a_par['tabs'])) {
            return;
        }

        /** @var ilAutoCourseReminderPlugin $plugin */
        $plugin = $this->getPluginObject();
        $courseSettingsGUI = new ilAutoCourseReminderCourseSettingsGUI($plugin);
        $courseSettingsGUI->modifySubTabs($a_par['tabs']);
    }
}
