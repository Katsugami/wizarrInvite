# wizarrInvite – Organizr Plugin

Plugin Organizr permettant de **générer et afficher automatiquement des invitations Wizarr** directement depuis Organizr.

Ce plugin permet de simplifier la distribution d’accès à votre serveur multimédia en utilisant Wizarr et un système d’invitation automatisé.

---

# Fonctionnalités

* Génération **manuelle** d'invitations Wizarr
* Génération **automatique** d’invitations
* Vérification automatique de la validité du code
* Recréation automatique si les paramètres changent
* Sélection des **serveurs et bibliothèques**
* Gestion des autorisations :

  * TV en direct
  * téléchargements
  * uploads mobiles
* Page **Display personnalisée**

  * Français
  * Anglais
  * Espagnol
* Compatible avec la traduction automatique Organizr

---

# Fonctionnement du mode automatique

Lorsque la page **Display** est chargée :

1. Le plugin vérifie si une invitation Wizarr existe
2. Le plugin compare les paramètres configurés avec ceux du code existant
3. Si un paramètre a changé :

   * l'ancien code est supprimé
   * un nouveau code est créé automatiquement

Paramètres vérifiés :

* durée d'accès
* expiration du code
* accès TV live
* téléchargement autorisé
* upload mobile autorisé
* serveurs sélectionnés
* bibliothèques sélectionnées

---

# Installation

1. Télécharger ou cloner ce dépôt dans :

```
Organizr/api/plugins/wizarrInvite
```

2. Vérifier que les fichiers sont présents :

```
plugin.php
api.php
page.php
main.js
settings.js
config.php
display-fr.php
display-en.php
display-es.php
logo.png
```

3. Redémarrer Organizr si nécessaire.

4. Le plugin apparaîtra dans :

```
Settings → Plugins
```

---

# Configuration

Dans les paramètres du plugin :

### Connexion Wizarr

Configurer l’URL de votre serveur Wizarr et votre clé API.

### Invitation manuelle

Permet de générer un code à la demande.

### Invitation automatique

Permet de générer un code automatiquement avec :

* durée d'accès
* expiration
* serveurs
* bibliothèques
* permissions

### Display

Permet d'afficher la page publique contenant l’invitation.

---

# Utilisation du Display

La page display peut être utilisée pour partager une invitation publique :

```
https://votre-organizr/api/plugins/wizarrInvite/display-fr.php
```

ou

```
display-en.php
display-es.php
```

Lorsque la page est chargée :

* le plugin vérifie si un code est valide
* sinon un nouveau code est créé automatiquement

---

# Compatibilité

* Organizr v2
* Wizarr API

---

# Auteur

Katsugami

---

# Licence

Projet personnel.
