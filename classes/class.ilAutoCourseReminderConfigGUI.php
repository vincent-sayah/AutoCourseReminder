<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Component/classes/class.ilPluginConfigGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilPropertyFormGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilTextInputGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilFormSectionHeaderGUI.php';

/**
 * @ilCtrl_IsCalledBy ilAutoCourseReminderConfigGUI: ilObjComponentSettingsGUI
 */
class ilAutoCourseReminderConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd): void
    {
        match ($cmd) {
            'save' => $this->save(),
            default => $this->configure(),
        };
    }

    public function configure(): void
    {
        $this->tpl()->setContent($this->buildForm()->getHTML());
    }

    public function save(): void
    {
        $form = $this->buildForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->tpl()->setContent($form->getHTML());
            return;
        }

        $plugin = $this->plugin();
        $repo = $plugin->getRepository();
        $repo->setSetting('base_url', trim((string) $form->getInput('base_url')));
        $repo->setSetting('client_id', trim((string) $form->getInput('client_id')));
        $repo->setSetting('mail_from', trim((string) $form->getInput('mail_from')));
        $repo->setSetting('mail_from_name', trim((string) $form->getInput('mail_from_name')));

        if ($repo->getSetting('token_secret', '') === '') {
            $repo->setSetting('token_secret', bin2hex(random_bytes(24)));
        }

        $this->tpl()->setOnScreenMessage('success', $plugin->txt('config_saved'), true);
        $this->ctrl()->redirect($this, 'configure');
    }

    private function buildForm(): ilPropertyFormGUI
    {
        $plugin = $this->plugin();
        $repo = $plugin->getRepository();

        $form = new ilPropertyFormGUI();
        $form->setTitle($plugin->txt('config_title'));
        $form->setFormAction($this->ctrl()->getFormAction($this));
        $form->addCommandButton('save', $plugin->txt('save'));

        $technical = new ilFormSectionHeaderGUI();
        $technical->setTitle($plugin->txt('technical_settings'));
        $form->addItem($technical);

        $baseUrl = new ilTextInputGUI($plugin->txt('base_url'), 'base_url');
        $baseUrl->setRequired(false);
        $baseUrl->setInfo($plugin->txt('base_url_info'));
        $baseUrl->setValue((string) $repo->getSetting('base_url', defined('ILIAS_HTTP_PATH') ? (string) ILIAS_HTTP_PATH : ''));
        $form->addItem($baseUrl);

        $clientId = new ilTextInputGUI($plugin->txt('client_id'), 'client_id');
        $clientId->setRequired(false);
        $clientId->setValue((string) $repo->getSetting('client_id', defined('CLIENT_ID') ? (string) CLIENT_ID : ''));
        $form->addItem($clientId);

        $mailFrom = new ilTextInputGUI($plugin->txt('mail_from'), 'mail_from');
        $mailFrom->setRequired(true);
        $mailFrom->setValue((string) $repo->getSetting('mail_from', 'noreply@example.org'));
        $form->addItem($mailFrom);

        $mailFromName = new ilTextInputGUI($plugin->txt('mail_from_name'), 'mail_from_name');
        $mailFromName->setRequired(true);
        $mailFromName->setValue((string) $repo->getSetting('mail_from_name', 'ILIAS'));
        $form->addItem($mailFromName);

        $course = new ilFormSectionHeaderGUI();
        $course->setTitle($plugin->txt('course_level_configuration'));
        $form->addItem($course);

        $hint = new ilTextInputGUI($plugin->txt('course_level_configuration_hint'), 'course_level_configuration_hint');
        $hint->setValue($plugin->txt('course_level_configuration_hint_value'));
        $hint->setDisabled(true);
        $form->addItem($hint);

        return $form;
    }

    private function ctrl(): ilCtrl
    {
        global $DIC;

        return $DIC->ctrl();
    }

    private function tpl()
    {
        global $tpl;

        return $tpl;
    }

    private function plugin(): ilAutoCourseReminderPlugin
    {
        /** @var ilAutoCourseReminderPlugin $plugin */
        $plugin = $this->getPluginObject();
        return $plugin;
    }
}
