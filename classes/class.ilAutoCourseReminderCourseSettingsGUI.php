<?php

declare(strict_types=1);

if (!defined('ACR_ILIAS_ROOT')) {
    define('ACR_ILIAS_ROOT', dirname(__DIR__, 9));
}

require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilPropertyFormGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilTextInputGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilTextAreaInputGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilCheckboxInputGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Form/classes/class.ilFormSectionHeaderGUI.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/Utilities/classes/class.ilUtil.php';
require_once ACR_ILIAS_ROOT . '/components/ILIAS/ILIASObject/classes/class.ilObject.php';

class ilAutoCourseReminderCourseSettingsGUI
{
    private const TOKEN_OPEN = '__ACR_DBL_OPEN__';
    private const TOKEN_CLOSE = '__ACR_DBL_CLOSE__';

    private static bool $initialized = false;
    private static ?int $preparedCourseRefId = null;
    private static ?ilPropertyFormGUI $preparedForm = null;
    private static string $messageHtml = '';

    public function __construct(private readonly ilAutoCourseReminderPlugin $plugin)
    {
    }

    public function getHookResponse(string $a_comp, string $a_part, array $a_par = []): array
    {
        $courseRefId = $this->getCurrentCourseRefId();
        if ($courseRefId <= 0 || !$this->isReminderSettingsRequest()) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => ''
            ];
        }

        if (!$this->access()->checkAccess('write', '', $courseRefId)) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => ''
            ];
        }

        if (!self::$initialized) {
            self::$initialized = true;
            $this->initializeRequest($courseRefId);
        }

        if ($a_part !== 'template_show' || !isset($a_par['html']) || !is_string($a_par['html'])) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => ''
            ];
        }

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => $this->replaceCenterColumnContent(
                $a_par['html'],
                $this->renderSettingsHtml()
            )
        ];
    }

    public function modifySubTabs($tabs): void
    {
        $courseRefId = $this->getCurrentCourseRefId();
        if ($courseRefId <= 0 || !$this->access()->checkAccess('write', '', $courseRefId)) {
            return;
        }

        if (!$this->isSettingsAreaRequest()) {
            return;
        }

        $tabs->addSubTab('acrm_course_settings', $this->plugin->txt('course_settings_tab'), $this->buildSettingsUrl());
        if ($this->isReminderSettingsRequest()) {
            $tabs->setSubTabActive('acrm_course_settings');
        }
    }

    private function initializeRequest(int $courseRefId): void
    {
        self::$preparedCourseRefId = $courseRefId;
        self::$messageHtml = '';
        self::$preparedForm = null;

        if ((int) ($_GET['acr_saved'] ?? 0) === 1) {
            self::$messageHtml = $this->buildMessageHtml('success', $this->plugin->txt('course_settings_saved'));
        }

        if ($this->isSaveRequest()) {
            $this->prepareSave($courseRefId);
            return;
        }

        self::$preparedForm = $this->buildForm($courseRefId);
    }

    private function prepareSave(int $courseRefId): void
    {
        $form = $this->buildForm($courseRefId);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            self::$messageHtml = $this->buildMessageHtml('failure', $this->lng()->txt('err_check_input'));
            self::$preparedForm = $form;
            return;
        }

        $delayDays = $this->parsePositiveInt((string) $form->getInput('delay_days'));
        $repeatDays = $this->parsePositiveInt((string) $form->getInput('repeat_every_days'));
        $maxReminders = $this->parsePositiveInt((string) $form->getInput('max_reminders'));

        $hasError = false;
        if ($delayDays === null) {
            $form->getItemByPostVar('delay_days')->setAlert($this->plugin->txt('positive_integer_required'));
            $hasError = true;
        }
        if ($repeatDays === null) {
            $form->getItemByPostVar('repeat_every_days')->setAlert($this->plugin->txt('positive_integer_required'));
            $hasError = true;
        }
        if ($maxReminders === null) {
            $form->getItemByPostVar('max_reminders')->setAlert($this->plugin->txt('positive_integer_required'));
            $hasError = true;
        }

        if ($hasError) {
            $form->setValuesByPost();
            self::$messageHtml = $this->buildMessageHtml('failure', $this->lng()->txt('err_check_input'));
            self::$preparedForm = $form;
            return;
        }

        $this->plugin->getRepository()->saveCourseRule($courseRefId, [
            'active' => (bool) $form->getInput('active'),
            'delay_days' => $delayDays,
            'repeat_every_days' => $repeatDays,
            'max_reminders' => $maxReminders,
            'allow_opt_out' => (bool) $form->getInput('allow_opt_out'),
            'subject' => trim((string) $form->getInput('subject')),
            'body' => trim((string) $form->getInput('body')),
        ]);

        ilUtil::redirect($this->buildSettingsUrl(['acr_saved' => '1']));
    }

    private function renderSettingsHtml(): string
    {
        $courseRefId = self::$preparedCourseRefId ?? $this->getCurrentCourseRefId();
        $form = self::$preparedForm instanceof ilPropertyFormGUI
            ? self::$preparedForm
            : $this->buildForm($courseRefId);

        return $this->finalizeTemplateMarkersForHtml(
            self::$messageHtml
            . $form->getHTML()
            . $this->buildTemplateHelpHtml()
        );
    }

    private function buildMessageHtml(string $type, string $message): string
    {
        if ($message === '') {
            return '';
        }

        $class = $type === 'failure' ? 'alert alert-danger' : 'alert alert-success';

        return '<div class="' . $class . '" role="alert" style="margin-bottom:15px;">'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }

    private function replaceCenterColumnContent(string $pageHtml, string $contentHtml): string
    {
        if ($pageHtml === '' || !class_exists('DOMDocument')) {
            return $pageHtml;
        }

        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $pageHtml;
        }

        $xpath = new DOMXPath($dom);
        $centerColumn = $xpath->query('//*[@id="il_center_col"]')->item(0);
        if (!$centerColumn instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $pageHtml;
        }

        while ($centerColumn->firstChild !== null) {
            $centerColumn->removeChild($centerColumn->firstChild);
        }

        $fragmentDocument = new DOMDocument('1.0', 'UTF-8');
        $fragmentLoaded = $fragmentDocument->loadHTML('<?xml encoding="utf-8" ?><div id="acr_wrapper">' . $contentHtml . '</div>');
        if ($fragmentLoaded) {
            $wrapper = $fragmentDocument->getElementById('acr_wrapper');
            if ($wrapper instanceof DOMElement) {
                while ($wrapper->firstChild !== null) {
                    $centerColumn->appendChild($dom->importNode($wrapper->firstChild, true));
                    $wrapper->removeChild($wrapper->firstChild);
                }
            }
        }

        $leftColumn = $xpath->query('//*[@id="il_left_col"]')->item(0);
        if ($leftColumn instanceof DOMElement) {
            while ($leftColumn->firstChild !== null) {
                $leftColumn->removeChild($leftColumn->firstChild);
            }
        }

        $rightColumn = $xpath->query('//*[@id="il_right_col"]')->item(0);
        if ($rightColumn instanceof DOMElement) {
            while ($rightColumn->firstChild !== null) {
                $rightColumn->removeChild($rightColumn->firstChild);
            }
        }

        $html = $dom->saveHTML();
        $html = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $html) ?? $pageHtml;

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $this->finalizeTemplateMarkersForHtml($html);
    }

    private function buildForm(int $courseRefId): ilPropertyFormGUI
    {
        $rule = $this->plugin->getRepository()->getCourseRule($courseRefId);
        $courseTitle = (string) ilObject::_lookupTitle((int) ilObject::_lookupObjectId($courseRefId));

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('course_settings_form_title') . ($courseTitle !== '' ? ' - ' . $courseTitle : ''));
        $form->setFormAction($this->buildSettingsUrl());
        $form->addCommandButton('save', $this->plugin->txt('save'));

        $active = new ilCheckboxInputGUI($this->plugin->txt('course_rule_active'), 'active');
        $active->setChecked((bool) $rule['active']);
        $active->setInfo($this->plugin->txt('course_rule_active_info'));
        $form->addItem($active);

        $delayDays = new ilTextInputGUI($this->plugin->txt('delay_days'), 'delay_days');
        $delayDays->setRequired(true);
        $delayDays->setValue((string) $rule['delay_days']);
        $delayDays->setInfo($this->plugin->txt('delay_days_info'));
        $form->addItem($delayDays);

        $repeatDays = new ilTextInputGUI($this->plugin->txt('repeat_every_days'), 'repeat_every_days');
        $repeatDays->setRequired(true);
        $repeatDays->setValue((string) $rule['repeat_every_days']);
        $repeatDays->setInfo($this->plugin->txt('repeat_every_days_info'));
        $form->addItem($repeatDays);

        $maxReminders = new ilTextInputGUI($this->plugin->txt('max_reminders'), 'max_reminders');
        $maxReminders->setRequired(true);
        $maxReminders->setValue((string) $rule['max_reminders']);
        $maxReminders->setInfo($this->plugin->txt('max_reminders_info'));
        $form->addItem($maxReminders);

        $allowOptOut = new ilCheckboxInputGUI($this->plugin->txt('allow_opt_out'), 'allow_opt_out');
        $allowOptOut->setChecked((bool) $rule['allow_opt_out']);
        $allowOptOut->setInfo($this->plugin->txt('allow_opt_out_info'));
        $form->addItem($allowOptOut);

        $templates = new ilFormSectionHeaderGUI();
        $templates->setTitle($this->plugin->txt('message_templates'));
        $form->addItem($templates);

        $subject = new ilTextInputGUI($this->plugin->txt('subject'), 'subject');
        $subject->setRequired(true);
        $subject->setValue($this->getDisplaySubject((string) $rule['subject']));
        $subject->setInfo($this->plugin->txt('subject_info'));
        $form->addItem($subject);

        $body = new ilTextAreaInputGUI($this->plugin->txt('body'), 'body');
        $body->setRequired(true);
        $body->setRows(16);
        $body->setCols(120);
        $body->setValue($this->getDisplayBody((string) $rule['body']));
        $body->setInfo($this->plugin->txt('body_info'));
        $form->addItem($body);

        return $form;
    }

    private function getDisplaySubject(string $value): string
    {
        $postedValue = $_POST['subject'] ?? null;
        if (is_string($postedValue) && $postedValue !== '') {
            return $this->tokenizeTemplateMarkers($postedValue);
        }

        return $this->tokenizeTemplateMarkers($value);
    }

    private function getDisplayBody(string $value): string
    {
        $postedValue = $_POST['body'] ?? null;
        if (is_string($postedValue) && $postedValue !== '') {
            return $this->tokenizeTemplateMarkers($postedValue);
        }

        return $this->tokenizeTemplateMarkers($value);
    }

    private function buildTemplateHelpHtml(): string
    {
        return '<div class="alert alert-info" style="margin-top:15px;">'
            . '<strong>' . htmlspecialchars($this->plugin->txt('template_variables_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
            . '<p style="margin-top:8px; margin-bottom:8px;">' . htmlspecialchars($this->plugin->txt('template_variables_intro'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<ul style="margin-bottom:8px;">'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{FIRSTNAME}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_firstname'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{LASTNAME}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_lastname'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{LOGIN}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{COURSE_TITLE}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_course_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{COURSE_URL}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_course_url'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{INACTIVITY_DAYS}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_inactivity_days'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{MAIL_FROM_NAME}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_mail_from_name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '<li><code>' . $this->tokenizeTemplateMarkers('{{OPTOUT_BLOCK}}') . '</code> : ' . htmlspecialchars($this->plugin->txt('template_var_optout_block'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
            . '</ul>'
            . '<p style="margin-bottom:4px;"><strong>' . htmlspecialchars($this->plugin->txt('template_subject_example'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> <code>' . $this->tokenizeTemplateMarkers('[ILIAS] Rappel d&#39;activité - {{COURSE_TITLE}}') . '</code></p>'
            . '<p style="margin-bottom:4px;"><strong>' . htmlspecialchars($this->plugin->txt('template_body_example'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>'
            . '<pre style="white-space:pre-wrap;">'
            . $this->tokenizeTemplateMarkers("Bonjour {{FIRSTNAME}},

Aucune activité n&#39;a été détectée dans le cours \"{{COURSE_TITLE}}\" depuis {{INACTIVITY_DAYS}} jour(s).

Reprendre le cours : {{COURSE_URL}}
{{OPTOUT_BLOCK}}

Cordialement,
{{MAIL_FROM_NAME}}")
            . '</pre>'
            . '</div>';
    }

    private function tokenizeTemplateMarkers(string $value): string
    {
        return preg_replace('/\{\{([A-Z_]+)\}\}/', self::TOKEN_OPEN . '$1' . self::TOKEN_CLOSE, $value) ?? $value;
    }

    private function finalizeTemplateMarkersForHtml(string $html): string
    {
        $html = str_replace(
            [self::TOKEN_OPEN, self::TOKEN_CLOSE],
            ['&#123;&#123;', '&#125;&#125;'],
            $html
        );

        $encoded = preg_replace_callback(
            '/\{\{([A-Z_]+)\}\}/',
            static fn(array $matches): string => '&#123;&#123;' . $matches[1] . '&#125;&#125;',
            $html
        );

        return is_string($encoded) ? $encoded : $html;
    }

    private function parsePositiveInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^[1-9][0-9]*$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function isSaveRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function isReminderSettingsRequest(): bool
    {
        return (int) ($_GET['acr_course_settings'] ?? 0) === 1;
    }

    private function isSettingsAreaRequest(): bool
    {
        if ($this->isReminderSettingsRequest()) {
            return true;
        }

        $cmd = strtolower((string) ($_GET['cmd'] ?? ''));

        return in_array($cmd, ['edit', 'update', 'editinfo', 'updateinfo'], true);
    }

    private function getCurrentCourseRefId(): int
    {
        $refId = (int) ($_GET['ref_id'] ?? 0);
        if ($refId <= 0) {
            return 0;
        }

        $type = (string) ilObject::_lookupType($refId, true);
        return $type === 'crs' ? $refId : 0;
    }

    private function buildSettingsUrl(array $extra = []): string
    {
        $params = [];
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($queryString !== '') {
            parse_str($queryString, $params);
        }

        unset($params['acr_saved']);
        $params['acr_course_settings'] = '1';
        foreach ($extra as $key => $value) {
            $params[$key] = (string) $value;
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? 'ilias.php');
        return $script . ($query !== '' ? '?' . $query : '');
    }

    private function lng(): ilLanguage
    {
        global $DIC;

        return $DIC->language();
    }

    private function access()
    {
        global $DIC;

        return $DIC->access();
    }
}
