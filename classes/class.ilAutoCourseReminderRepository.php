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

    public function normalizeRule(array $rule): array
    {
        $ruleKey = trim((string) ($rule['rule_key'] ?? ''));
        $courseRefId = (int) ($rule['course_ref_id'] ?? 0);
        $type = trim((string) ($rule['rule_type'] ?? 'inactivity'));

        if ($ruleKey === '') {
            $ruleKey = $type . '_' . $courseRefId . '_' . (int) ($rule['step_ref_id'] ?? 0);
        }

        return [
            'rule_key' => $ruleKey,
            'course_ref_id' => $courseRefId,
            'rule_type' => $type,
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
}
