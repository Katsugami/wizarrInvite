# wizarrInvite – Organizr Plugin

wizarrInvite is an Organizr plugin that allows you to **generate and manage Wizarr invitation links directly from Organizr**.

The plugin can automatically create invitation codes, verify them, and recreate them if the configuration changes.

This makes it easier to share controlled access to your media server through Wizarr.

---

# Recommended Installation (Marketplace)

The easiest way to install this plugin is through the **custom Organizr marketplace repository**.

Repository:

https://github.com/Katsugami/Organizr-Plugins

### Add the repository to Organizr

Open Organizr and go to:

Settings → Plugins → Settings → Marketplace

Then in **External Marketplace Repo**, add:

```
https://github.com/Katsugami/Organizr-Plugins
```

After saving, go to:

Plugins → Marketplace

The **wizarrInvite plugin will appear and can be installed directly from Organizr.**

---

# Features

* Manual Wizarr invitation creation
* Automatic invitation generation
* Automatic invitation validation check
* Automatic regeneration if parameters change
* Server selection
* Library selection
* Permission management:

  * Live TV access
  * Downloads
  * Mobile uploads
* Public invitation display page
* Multi-language display support:

  * French
  * English
  * Spanish
* Compatible with Organizr auto-translation

---

# Automatic Invitation Mode

When the **Display page is loaded**, the plugin performs the following checks:

1. Verify if an invitation already exists
2. Compare the existing invitation parameters with the configured settings
3. If the parameters do not match:

   * the existing invitation is deleted
   * a new invitation is automatically created

Parameters checked include:

* access duration
* invitation expiration
* Live TV permission
* download permission
* mobile upload permission
* selected servers
* selected libraries

---

# Manual Installation

If you prefer manual installation, download or clone this repository into the Organizr plugins directory:

```
Organizr/api/plugins/wizarrInvite
```

Required files:

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

Restart Organizr if necessary.

The plugin will appear in:

```
Settings → Plugins
```

---

# Configuration

In the plugin settings panel you can configure:

### Wizarr Connection

* Wizarr server URL
* Wizarr API key

### Manual Invitation

Create a Wizarr invitation code manually.

### Automatic Invitation

Automatically maintain a valid invitation code with configurable settings:

* access duration
* expiration time
* servers
* libraries
* permissions

### Display Page

Public invitation display pages are available:

```
display-fr.php
display-en.php
display-es.php
```

Example:

```
https://your-organizr/api/plugins/wizarrInvite/display-en.php
```

When the page loads:

* the plugin checks if a valid invitation exists
* if not, a new invitation is created automatically

---

# Compatibility

* Organizr v2
* Wizarr API

---

# Authors

Katsugami

AI development assistance: ChatGPT

Most of the code for this plugin was generated with the help of ChatGPT.
The project structure, integration, testing, and final assembly were performed by Katsugami.

---

# License

Personal project.
