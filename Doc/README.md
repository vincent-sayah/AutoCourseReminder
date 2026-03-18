# AutoCourseReminder - Documentation technique

## Références du document
- Plugin : AutoCourseReminder
- Identifiant : `acrm`
- Version : `1.1.0`
- Cible : ILIAS 10.5
- Dépôt : `github.com/vincent-sayah/AutoCourseReminder`

## 1. Synthèse d’architecture
AutoCourseReminder est un plugin ILIAS de type **User Interface Hook**. Il assure trois fonctions :
1. le suivi de l’activité utilisateur dans les cours ;
2. l’administration des paramètres globaux et des paramètres par cours ;
3. l’exécution d’un job cron qui évalue les règles puis crée des notifications dans la messagerie interne ILIAS.

### Composants principaux
- `plugin.php` : manifeste du plugin.
- `class.ilAutoCourseReminderPlugin.php` : bootstrap principal, service et repository.
- `class.ilAutoCourseReminderUIHookGUI.php` : hook UI, suivi d’activité et désinscription.
- `class.ilAutoCourseReminderConfigGUI.php` : configuration globale.
- `class.ilAutoCourseReminderCourseSettingsGUI.php` : écran de configuration par cours.
- `class.ilAutoCourseReminderService.php` : logique métier.
- `class.ilAutoCourseReminderRepository.php` : accès aux données.
- `class.ilAutoCourseReminderCronJob.php` : déclaration et exécution du cron.
- `class.ilAutoCourseReminderMailAdapter.php` et `class.ilAutoCourseReminderInternalMailNotification.php` : intégration avec la messagerie interne ILIAS.

> Le code conserve une compatibilité arrière avec un ancien mode `rules_json` et avec des règles de type `step`, bien que l’interface 1.1.0 expose uniquement une règle d’inactivité par cours.

## 2. Intégration ILIAS
- Hérite de `ilUserInterfaceHookPlugin`.
- Implémente `ilCronJobProvider`.
- Utilise `ilObject`, `ilLPStatusWrapper`, `ilCourseParticipants`, `ilObjUser`, `ilMailNotification`, `ilUtil` et les services du DIC.

## 3. Configuration globale
Clés gérées :
- `base_url`
- `client_id`
- `mail_from`
- `mail_from_name`
- `token_secret` (généré automatiquement si absent)
- `rules_json` (fallback legacy)

## 4. Configuration par cours
L’onglet **Paramètres > Rappels automatiques** permet de définir :
- activation ;
- délai avant le premier rappel ;
- délai entre deux relances ;
- nombre maximal de rappels ;
- désinscription autorisée ;
- sujet ;
- corps du message.

Les champs numériques sont validés comme entiers strictement positifs.

## 5. Variables de template
- `{{FIRSTNAME}}`
- `{{LASTNAME}}`
- `{{LOGIN}}`
- `{{COURSE_TITLE}}`
- `{{COURSE_URL}}`
- `{{INACTIVITY_DAYS}}`
- `{{MAIL_FROM_NAME}}`
- `{{OPTOUT_BLOCK}}`
- `{{DISABLE_URL}}` (disponible côté service)

## 6. Modèle de données
### Tables
- `ui_uihk_acrm_settings`
- `ui_uihk_acrm_activity`
- `ui_uihk_acrm_dispatch`
- `ui_uihk_acrm_optout`
- `ui_uihk_acrm_crule`

### Rôle des tables
- `settings` : réglages globaux.
- `activity` : première et dernière activité utilisateur par cours.
- `dispatch` : historique du dernier envoi et compteur.
- `optout` : désinscriptions.
- `crule` : règle d’inactivité par cours.

## 7. Flux d’exécution

### 7.1 Suivi d’activité
- `ilAutoCourseReminderUIHookGUI::getHTML()` appelle une seule fois `handleOptOutRequest()` puis `trackCurrentRequest()`.
- `trackCurrentRequest()` ignore les anonymes, lit `ref_id`, résout le cours parent éventuel puis met à jour `ui_uihk_acrm_activity`.

### 7.2 Désinscription
- Le lien de désinscription passe par :
  - `acr_action=disable`
  - `acr_u`
  - `acr_c`
  - `acr_t`
- `acr_t` est un HMAC SHA-256 calculé sur `user_id:course_ref_id` avec `token_secret`.
- En cas de succès, l’utilisateur est enregistré dans `ui_uihk_acrm_optout` puis redirigé vers le cours.

### 7.3 Cron
Le job `autocoursereminder_job` :
1. charge les règles actives ;
2. récupère membres, utilisateurs en progression et utilisateurs déjà complets ;
3. vérifie activité, opt-out, limite de relance et délai minimal ;
4. génère le message ;
5. crée une notification interne ILIAS ;
6. met à jour `ui_uihk_acrm_dispatch`.

## 8. Algorithme d’éligibilité
Pour une règle d’inactivité :
- l’utilisateur doit être membre du cours ;
- son learning progress doit être `in progress` ;
- le cours ne doit pas être déjà complet ;
- il ne doit pas être désinscrit ;
- une activité préalable doit exister ;
- `now - last_seen >= delay_days` ;
- `sent_count < max_reminders` ;
- `now - last_sent >= repeat_every_days`.

## 9. Messagerie
L’envoi se fait par la **messagerie interne ILIAS** via `ilMailNotification`.
L’adaptateur journalise :
- succès ;
- login vide ;
- erreur sur exception.

## 10. Valeurs par défaut
- `active = false`
- `delay_days = 5`
- `repeat_every_days = 5`
- `max_reminders = 3`
- `allow_opt_out = true`
- sujet par défaut : `[ILIAS] Rappel d’activité - {{COURSE_TITLE}}`

Le repository corrige également d’anciens modèles défectueux utilisant `{}`.

## 11. Sécurité et robustesse
- contrôle d’accès `write` pour l’écran de configuration par cours ;
- exclusion des utilisateurs anonymes ;
- HMAC SHA-256 pour l’intégrité du lien de désinscription ;
- pas d’expiration native des tokens ;
- pas de purge automatique des tables métier.

## 12. Installation et exploitation
Chemin de déploiement :
```text
public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/AutoCourseReminder
```

Après activation :
- configurer le plugin ;
- configurer les cours concernés ;
- planifier le cron.

Types de planification autorisés :
- quotidien ;
- tous les N jours ;
- toutes les N heures.

## 13. Tests techniques
```sql
UPDATE ui_uihk_acrm_activity
SET last_seen = NOW() - INTERVAL 2 DAY
WHERE user_id = 328
  AND course_ref_id = 89;

DELETE FROM ui_uihk_acrm_dispatch
WHERE user_id = 328
  AND rule_key = 'course_89_inactivity';

SELECT *
FROM ui_uihk_acrm_optout
WHERE user_id = 328
  AND course_ref_id = 89;
```

## 14. Limites
- une seule règle d’inactivité par cours dans l’UI 1.1.0 ;
- pas de console d’administration dédiée aux envois ;
- pas de réactivation UI native après désinscription ;
- dépendance au markup HTML de la page de paramètres ILIAS ;
- support `step` seulement en compatibilité legacy.

## 15. Conclusion
La version 1.1.0 fournit une base technique propre pour ILIAS 10.5, avec persistance dédiée, configuration bi-niveau, suivi d’activité intégré au hook UI et notifications via la messagerie interne.
