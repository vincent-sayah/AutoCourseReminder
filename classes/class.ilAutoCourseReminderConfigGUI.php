<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Component/classes/class.ilPluginConfigGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilPropertyFormGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilTextInputGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilTextAreaInputGUI.php';

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

        $rulesJson = trim((string) $form->getInput('rules_json'));
        json_decode($rulesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->tpl()->setOnScreenMessage('failure', 'JSON invalide : ' . json_last_error_msg(), false);
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
        $repo->setSetting('rules_json', $rulesJson);

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

        $rules = new ilTextAreaInputGUI($plugin->txt('rules_json'), 'rules_json');
        $rules->setRequired(true);
        $rules->setRows(24);
        $rules->setCols(120);
        $rules->setInfo($plugin->txt('rules_json_info'));
        $rules->setValue((string) $repo->getSetting('rules_json', $this->getDefaultRulesJson()));
        $form->addItem($rules);

        return $form;
    }

    private function getDefaultRulesJson(): string
    {
        return json_encode([
            [
                'rule_key' => 'course_123_inactivity',
                'course_ref_id' => 123,
                'rule_type' => 'inactivity',
                'delay_days' => 5,
                'repeat_every_days' => 5,
                'max_reminders' => 3,
                'allow_opt_out' => true,
                'active' => true,
                'subject' => '[ILIAS] Rappel d\'activité - {{COURSE_TITLE}}',
                'body' => "Bonjour {{FIRSTNAME}},\n\nAucune activité n'a été détectée dans le cours \"{{COURSE_TITLE}}\" depuis {{INACTIVITY_DAYS}} jour(s).\n\nReprendre : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}",
            ],
            [
                'rule_key' => 'course_123_step_456',
                'course_ref_id' => 123,
                'rule_type' => 'step',
                'step_ref_id' => 456,
                'delay_days' => 7,
                'repeat_every_days' => 4,
                'max_reminders' => 2,
                'allow_opt_out' => true,
                'active' => false,
                'subject' => '[ILIAS] Étape à compléter - {{COURSE_TITLE}}',
                'body' => "Bonjour {{FIRSTNAME}},\n\nUne étape du cours \"{{COURSE_TITLE}}\" n'est pas encore complétée.\n\nReprendre : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}",
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }


    private function ctrl(): ilCtrl
    {
        global $DIC;

        return $DIC->ctrl();
    }

    private function tpl()
    {
        global $DIC;

        return $DIC['tpl'];
    }

    private function plugin(): ilAutoCourseReminderPlugin
    {
        /** @var ilAutoCourseReminderPlugin $plugin */
        $plugin = $this->getPluginObject();
        return $plugin;
    }
}
