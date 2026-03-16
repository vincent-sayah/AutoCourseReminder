<?php

declare(strict_types=1);

class ilAutoCourseReminderRepository
{
    public function __construct(
        private readonly ilDBInterface $db,
        private readonly ilAutoCourseReminderPlugin $plugin
    ) {
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        $set = $this->db->queryF(
            'SELECT setting_value FROM ' . ilAutoCourseReminderPlugin::TABLE_SETTINGS . ' WHERE setting_key = %s',
            ['text'],
            [$key]
        );

        $row = $this->db->fetchAssoc($set);
        if (!$row) {
            return $default;
        }

        return (string) $row['setting_value'];
    }

    public function setSetting(string $key, string $value): void
    {
        $this->db->replace(
            ilAutoCourseReminderPlugin::TABLE_SETTINGS,
            ['setting_key' => ['text', $key]],
            ['setting_value' => ['clob', $value]]
        );
    }

    public function getAllSettings(): array
    {
        $settings = [];
        $set = $this->db->query('SELECT setting_key, setting_value FROM ' . ilAutoCourseReminderPlugin::TABLE_SETTINGS);
        while ($row = $this->db->fetchAssoc($set)) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $settings;
    }

    public function getRules(): array
    {
        return $this->getCourseRules();
    }

    public function getCourseRules(): array
    {
        $rules = [];

        if ($this->db->tableExists(ilAutoCourseReminderPlugin::TABLE_COURSE_RULES)) {
            $set = $this->db->query(
                'SELECT course_ref_id, active, delay_days, repeat_every_days, max_reminders, allow_opt_out, subject, body
                 FROM ' . ilAutoCourseReminderPlugin::TABLE_COURSE_RULES
            );

            while ($row = $this->db->fetchAssoc($set)) {
                $rules[] = $this->normalizeCourseRule($row);
            }
        }

        if ($rules !== []) {
            return $rules;
        }

        return $this->getLegacyRules();
    }

    public function getCourseRule(int $courseRefId): array
    {
        if ($courseRefId <= 0) {
            return $this->getDefaultCourseRule(0);
        }

        if ($this->db->tableExists(ilAutoCourseReminderPlugin::TABLE_COURSE_RULES)) {
            $set = $this->db->queryF(
                'SELECT course_ref_id, active, delay_days, repeat_every_days, max_reminders, allow_opt_out, subject, body
                 FROM ' . ilAutoCourseReminderPlugin::TABLE_COURSE_RULES . '
                 WHERE course_ref_id = %s',
                ['integer'],
                [$courseRefId]
            );

            $row = $this->db->fetchAssoc($set);
            if (is_array($row)) {
                return $this->normalizeCourseRule($row);
            }
        }

        foreach ($this->getLegacyRules() as $rule) {
            if ((int) ($rule['course_ref_id'] ?? 0) === $courseRefId && (string) ($rule['rule_type'] ?? '') === 'inactivity') {
                return $this->normalizeCourseRule($rule);
            }
        }

        return $this->getDefaultCourseRule($courseRefId);
    }

    public function saveCourseRule(int $courseRefId, array $rule): void
    {
        $normalized = $this->normalizeCourseRule(array_merge($rule, ['course_ref_id' => $courseRefId]));

        $this->db->replace(
            ilAutoCourseReminderPlugin::TABLE_COURSE_RULES,
            ['course_ref_id' => ['integer', $courseRefId]],
            [
                'course_ref_id' => ['integer', $courseRefId],
                'active' => ['integer', $normalized['active'] ? 1 : 0],
                'delay_days' => ['integer', (int) $normalized['delay_days']],
                'repeat_every_days' => ['integer', (int) $normalized['repeat_every_days']],
                'max_reminders' => ['integer', (int) $normalized['max_reminders']],
                'allow_opt_out' => ['integer', $normalized['allow_opt_out'] ? 1 : 0],
                'subject' => ['clob', (string) $normalized['subject']],
                'body' => ['clob', (string) $normalized['body']],
            ]
        );
    }

    public function normalizeRule(array $rule): array
    {
        if (((string) ($rule['rule_type'] ?? '')) === 'step') {
            $ruleKey = trim((string) ($rule['rule_key'] ?? ''));
            $courseRefId = (int) ($rule['course_ref_id'] ?? 0);
            if ($ruleKey === '') {
                $ruleKey = 'step_' . $courseRefId . '_' . (int) ($rule['step_ref_id'] ?? 0);
            }

            return [
                'rule_key' => $ruleKey,
                'course_ref_id' => $courseRefId,
                'rule_type' => 'step',
                'step_ref_id' => (int) ($rule['step_ref_id'] ?? 0),
                'delay_days' => max(1, (int) ($rule['delay_days'] ?? 5)),
                'repeat_every_days' => max(1, (int) ($rule['repeat_every_days'] ?? 5)),
                'max_reminders' => max(1, (int) ($rule['max_reminders'] ?? 1)),
                'allow_opt_out' => !empty($rule['allow_opt_out']),
                'active' => array_key_exists('active', $rule) ? (bool) $rule['active'] : true,
                'subject' => trim((string) ($rule['subject'] ?? 'Rappel ILIAS')),
                'body' => (string) ($rule['body'] ?? ''),
            ];
        }

        return $this->normalizeCourseRule($rule);
    }

    public function touchCourseActivity(int $userId, int $courseRefId): void
    {
        if ($userId <= 0 || $courseRefId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->getActivity($userId, $courseRefId);

        $values = [
            'user_id' => ['integer', $userId],
            'course_ref_id' => ['integer', $courseRefId],
            'first_seen' => ['timestamp', $existing['first_seen'] ?? $now],
            'last_seen' => ['timestamp', $now],
        ];

        $this->db->replace(
            ilAutoCourseReminderPlugin::TABLE_ACTIVITY,
            [
                'user_id' => ['integer', $userId],
                'course_ref_id' => ['integer', $courseRefId],
            ],
            $values
        );
    }

    public function getActivity(int $userId, int $courseRefId): ?array
    {
        $set = $this->db->queryF(
            'SELECT user_id, course_ref_id, first_seen, last_seen
             FROM ' . ilAutoCourseReminderPlugin::TABLE_ACTIVITY . '
             WHERE user_id = %s AND course_ref_id = %s',
            ['integer', 'integer'],
            [$userId, $courseRefId]
        );

        $row = $this->db->fetchAssoc($set);

        return $row ?: null;
    }

    public function getDispatch(int $userId, string $ruleKey): ?array
    {
        $set = $this->db->queryF(
            'SELECT user_id, rule_key, sent_count, last_sent, last_reason
             FROM ' . ilAutoCourseReminderPlugin::TABLE_DISPATCH . '
             WHERE user_id = %s AND rule_key = %s',
            ['integer', 'text'],
            [$userId, $ruleKey]
        );

        $row = $this->db->fetchAssoc($set);

        return $row ?: null;
    }

    public function saveDispatch(int $userId, string $ruleKey, int $sentCount, string $reason): void
    {
        $this->db->replace(
            ilAutoCourseReminderPlugin::TABLE_DISPATCH,
            [
                'user_id' => ['integer', $userId],
                'rule_key' => ['text', $ruleKey],
            ],
            [
                'user_id' => ['integer', $userId],
                'rule_key' => ['text', $ruleKey],
                'sent_count' => ['integer', $sentCount],
                'last_sent' => ['timestamp', date('Y-m-d H:i:s')],
                'last_reason' => ['text', $reason],
            ]
        );
    }

    public function isOptedOut(int $userId, int $courseRefId): bool
    {
        $set = $this->db->queryF(
            'SELECT user_id FROM ' . ilAutoCourseReminderPlugin::TABLE_OPTOUT . ' WHERE user_id = %s AND course_ref_id = %s',
            ['integer', 'integer'],
            [$userId, $courseRefId]
        );

        return (bool) $this->db->fetchAssoc($set);
    }

    public function saveOptOut(int $userId, int $courseRefId): void
    {
        $this->db->replace(
            ilAutoCourseReminderPlugin::TABLE_OPTOUT,
            [
                'user_id' => ['integer', $userId],
                'course_ref_id' => ['integer', $courseRefId],
            ],
            [
                'user_id' => ['integer', $userId],
                'course_ref_id' => ['integer', $courseRefId],
                'created_at' => ['timestamp', date('Y-m-d H:i:s')],
            ]
        );
    }

    private function getLegacyRules(): array
    {
        $json = $this->getSetting('rules_json', '[]');
        $rules = json_decode((string) $json, true);
        if (!is_array($rules)) {
            return [];
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $normalized[] = $this->normalizeRule($rule);
        }

        return $normalized;
    }

    private function normalizeCourseRule(array $rule): array
    {
        $courseRefId = (int) ($rule['course_ref_id'] ?? 0);
        $defaults = $this->getDefaultCourseRule($courseRefId);

        $subject = trim((string) ($rule['subject'] ?? $defaults['subject']));
        $body = (string) ($rule['body'] ?? $defaults['body']);

        if ($this->isBrokenDefaultSubject($subject)) {
            $subject = (string) $defaults['subject'];
        }
        if ($this->isBrokenDefaultBody($body)) {
            $body = (string) $defaults['body'];
        }

        return [
            'rule_key' => 'course_' . $courseRefId . '_inactivity',
            'course_ref_id' => $courseRefId,
            'rule_type' => 'inactivity',
            'step_ref_id' => 0,
            'delay_days' => max(1, (int) ($rule['delay_days'] ?? $defaults['delay_days'])),
            'repeat_every_days' => max(1, (int) ($rule['repeat_every_days'] ?? $defaults['repeat_every_days'])),
            'max_reminders' => max(1, (int) ($rule['max_reminders'] ?? $defaults['max_reminders'])),
            'allow_opt_out' => array_key_exists('allow_opt_out', $rule) ? !empty($rule['allow_opt_out']) : (bool) $defaults['allow_opt_out'],
            'active' => array_key_exists('active', $rule) ? (bool) $rule['active'] : (bool) $defaults['active'],
            'subject' => $subject,
            'body' => $body,
        ];
    }

    private function isBrokenDefaultSubject(string $subject): bool
    {
        $subject = trim($subject);

        return preg_match('/^\[ILIAS\]\s+Rappel d\'activité\s*-\s*\{\}\s*$/u', $subject) === 1
            || preg_match('/^\[ILIAS\]\s+Activity reminder\s*-\s*\{\}\s*$/u', $subject) === 1;
    }

    private function isBrokenDefaultBody(string $body): bool
    {
        $body = preg_replace("/\r\n?/", "\n", trim($body)) ?? trim($body);

        return (
            str_contains($body, 'Bonjour {},')
            && str_contains($body, '"{}" depuis {} jour(s).')
            && str_contains($body, 'Reprendre le cours : {}')
        ) || (
            str_contains($body, 'Hello {},')
            && str_contains($body, '"{}" for {} day(s).')
            && str_contains($body, 'Resume the course: {}')
        );
    }

    private function getDefaultCourseRule(int $courseRefId): array
    {
        return [
            'rule_key' => 'course_' . $courseRefId . '_inactivity',
            'course_ref_id' => $courseRefId,
            'rule_type' => 'inactivity',
            'step_ref_id' => 0,
            'delay_days' => 5,
            'repeat_every_days' => 5,
            'max_reminders' => 3,
            'allow_opt_out' => true,
            'active' => false,
            'subject' => '[ILIAS] Rappel d\'activité - {{COURSE_TITLE}}',
            'body' => "Bonjour {{FIRSTNAME}},\n\nAucune activité n'a été détectée dans le cours \"{{COURSE_TITLE}}\" depuis {{INACTIVITY_DAYS}} jour(s).\n\nReprendre le cours : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}",
        ];
    }
}
