# AutoCourseReminder - ILIAS 10

AutoCourseReminder est un plugin compatible **ILIAS 10** permettant d’envoyer automatiquement des rappels aux utilisateurs inactifs dans un cours, tant que leur progression est encore **en cours**.

Les rappels sont envoyés dans la **messagerie interne ILIAS**.  
L’utilisateur peut se **désinscrire** des rappels pour un cours via un lien présent dans la notification.

---

## Fonctionnalités

- rappel automatique après une période définie d’inactivité dans un cours ;
- prise en compte des utilisateurs dont le statut de progression est **in progress** ;
- relances multiples possibles ;
- désactivation des rappels par l’utilisateur pour un cours donné ;
- notifications envoyées dans la messagerie interne ILIAS ;
- configuration technique centralisée au niveau du plugin et configuration des rappels dans chaque cours.

---

## Version cible

- **ILIAS 10.5**
- Base de données testée : **MariaDB**

---

## Installation

### 1. Copier le plugin

Déposer le dossier du plugin dans le répertoire suivant :

```bash
public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/AutoCourseReminder
```

### 2. Mettre à jour les artefacts ILIAS

Depuis la racine de l’installation ILIAS :

```bash
composer du
```

### 3. Installer le plugin

Dans l’administration ILIAS :

- aller dans **Administration**
- puis **Plugins**
- ouvrir le slot **User Interface Hook**
- installer **AutoCourseReminder**
- activer le plugin

### 4. Configurer le plugin

Dans l’écran de configuration du plugin, renseigner :

- `base_url`
- `client_id`
- `mail_from`
- `mail_from_name`

---

## Configuration

La configuration technique du plugin se fait toujours dans l’administration du plugin :

- `base_url`
- `client_id`
- `mail_from`
- `mail_from_name`

La configuration fonctionnelle des rappels se fait désormais **dans chaque cours**, depuis l’onglet **Paramètres** puis le sous-onglet **Rappels automatiques**.

### Paramètres disponibles par cours

- activation ou non des rappels ;
- délai avant le premier rappel ;
- délai entre deux relances ;
- nombre maximal de rappels ;
- autorisation de désinscription ;
- sujet du message ;
- corps du message.

### Variables disponibles dans le message

- `{{FIRSTNAME}}`
- `{{LASTNAME}}`
- `{{LOGIN}}`
- `{{COURSE_TITLE}}`
- `{{COURSE_URL}}`
- `{{INACTIVITY_DAYS}}`
- `{{MAIL_FROM_NAME}}`
- `{{OPTOUT_BLOCK}}`

---

## Fonctionnement

Le plugin surveille l’activité des utilisateurs dans les cours configurés.

Lorsqu’un utilisateur :

- est membre du cours ;
- a un statut de progression **en cours** ;
- est inactif depuis au moins `delay_days` ;
- n’a pas déjà atteint la limite de relances ;
- ne s’est pas désinscrit ;

alors le plugin envoie un rappel dans la **messagerie interne ILIAS**.

Les relances suivantes sont envoyées selon `repeat_every_days`.

---

## Désinscription des rappels

Si `allow_opt_out` est activé, le message contient un lien permettant à l’utilisateur de ne plus recevoir de rappels pour ce cours.

La désinscription est enregistrée en base dans la table :

```text
ui_uihk_acrm_optout
```

---

## Tables utilisées

Le plugin utilise les tables suivantes :

- `ui_uihk_acrm_settings`
- `ui_uihk_acrm_activity`
- `ui_uihk_acrm_dispatch`
- `ui_uihk_acrm_optout`
- `ui_uihk_acrm_crule`

### Rôle des tables

- `settings` : configuration du plugin
- `activity` : suivi d’activité par utilisateur et par cours
- `dispatch` : historique du dernier envoi par règle/utilisateur
- `optout` : désinscription des rappels
- `course_rules` : configuration des rappels par cours

---

## Test rapide

### Simuler une inactivité de plus de 24h

Exemple pour l’utilisateur `328` et le cours `89` :

```sql
UPDATE ui_uihk_acrm_activity
SET last_seen = NOW() - INTERVAL 2 DAY
WHERE user_id = 328
  AND course_ref_id = 89;
```

### Réinitialiser l’historique d’envoi

```sql
DELETE FROM ui_uihk_acrm_dispatch
WHERE user_id = 328
  AND rule_key = 'course_89_inactivity';
```

### Vérifier la désinscription

```sql
SELECT *
FROM ui_uihk_acrm_optout
WHERE user_id = 328
  AND course_ref_id = 89;
```

---

## Points importants

- le plugin envoie les rappels dans la **messagerie interne ILIAS** ;
- la redirection externe éventuelle dépend de la configuration et des préférences ILIAS ;
- une première activité dans le cours est nécessaire pour qu’un utilisateur soit ensuite considéré comme inactif ;
- si l’utilisateur se désinscrit, aucun nouveau rappel ne doit être envoyé pour le cours concerné.

---

## Limitations de cette première version

Cette version introduit une configuration par cours, mais reste volontairement simple :

- une règle d’inactivité par cours ;
- configuration technique séparée de la configuration métier ;
- pas encore de journal d’administration détaillé des envois ;
- l’intégration UI dans l’onglet Paramètres doit être validée dans votre ILIAS 10.5 exact.

---

## Feuille de route possible

- interface d’administration plus conviviale ;
- configuration par cours ;
- journalisation détaillée des envois ;
- réactivation des rappels par l’utilisateur ;
- support de règles supplémentaires basées sur des étapes ou objets spécifiques.

---

## Auteur

vince.syh@free.fr
