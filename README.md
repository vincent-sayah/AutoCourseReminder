# AutoCourseReminder for ILIAS 10

## Slot plugin

User Interface Hook plugin, with cron job provider.

## Installation path

```text
<ILIAS>/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/AutoCourseReminder
```

## Important cleanup before install

Remove **all previous attempts** of this plugin from both slots if they exist:

```bash
rm -rf <ILIAS>/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/AutoCourseReminder
rm -rf <ILIAS>/public/Customizing/global/plugins/Services/Cron/CronHook/AutoCourseReminder
```

Then copy this package only into the UIHook slot and run:

```bash
cd <ILIAS>
composer du
```

## What this plugin does

- Tracks user activity in a course through the UI hook.
- Provides a cron job visible in ILIAS cron administration.
- Sends reminder emails for:
  - inactivity in a course while LP is in progress
  - missing completion of a configured step item
- Supports repeated reminders.
- Lets the user disable reminders by email link.

## Configuration

Configure the plugin in administration and paste JSON rules.

Example:

```json
[
  {
    "rule_key": "course_123_inactivity",
    "course_ref_id": 123,
    "rule_type": "inactivity",
    "delay_days": 5,
    "repeat_every_days": 5,
    "max_reminders": 3,
    "allow_opt_out": true,
    "active": true,
    "subject": "[ILIAS] Rappel d'activité - {{COURSE_TITLE}}",
    "body": "Bonjour {{FIRSTNAME}},\n\nAucune activité n'a été détectée dans le cours \"{{COURSE_TITLE}}\" depuis {{INACTIVITY_DAYS}} jour(s).\n\nReprendre : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}"
  },
  {
    "rule_key": "course_123_step_456",
    "course_ref_id": 123,
    "rule_type": "step",
    "step_ref_id": 456,
    "delay_days": 7,
    "repeat_every_days": 4,
    "max_reminders": 2,
    "allow_opt_out": true,
    "active": false,
    "subject": "[ILIAS] Étape à compléter - {{COURSE_TITLE}}",
    "body": "Bonjour {{FIRSTNAME}},\n\nUne étape du cours \"{{COURSE_TITLE}}\" n'est pas encore complétée.\n\nReprendre : {{COURSE_URL}}\n{{OPTOUT_BLOCK}}\n\nCordialement,\n{{MAIL_FROM_NAME}}"
  }
]
```

## Notes

- The cron job must be activated in **Administration > General Settings > Cron Jobs**.
- `step_ref_id` must be the ref_id of the step item to monitor.
- Activity tracking starts when users navigate inside the course tree after plugin activation.
