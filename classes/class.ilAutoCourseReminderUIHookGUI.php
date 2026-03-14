<?php

declare(strict_types=1);

class ilAutoCourseReminderUIHookGUI extends ilUIHookPluginGUI
{
    private static bool $handled = false;

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        if (!self::$handled) {
            self::$handled = true;

            /** @var ilAutoCourseReminderPlugin $plugin */
            $plugin = $this->getPluginObject();
            $service = $plugin->getService();
            $service->handleOptOutRequest();
            $service->trackCurrentRequest();
        }

        return [
            'mode' => self::KEEP,
            'html' => ''
        ];
    }
}
