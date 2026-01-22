# LearnplacesMap - ILIAS Plugin

**Table of Contents**

- [📖 Introduction](#-introduction)
- [🏷️ Compatibility](#-compatibility)
- [📦 Installation](#-installation)
- [💡 Activation](#-activation)

## 📖 Introduction

### EN

This plugin provides a new page component that is available only in courses and groups. With this component, you can create maps of learning places, which are displayed both in ILIAS and in the app.
There are two map types: Tour and Collection.
- **Tour:** Learning places from the current context are selected individually and displayed on the tour map with sequential numbering. The order can be adjusted via the table.
- **Collection:** All selected tag groups are displayed. Tags can be configured in each learning place’s settings. The chosen tag groups appear on the map with their defined color or an uploaded icon.

### DE
Dieses Plugin stellt eine neue PageComponent bereit, die ausschließlich in Kursen und Gruppen verfügbar ist. Mit der Komponente können Karten mit Lernorten erstellt werden, die sowohl in ILIAS als auch in der App angezeigt werden.
Es stehen zwei Kartentypen zur Auswahl: Tour und Sammlung.
- **Tour:** Lernorte aus dem aktuellen Kontext werden einzeln ausgewählt und in der Tourkarte fortlaufend nummeriert dargestellt. Die Reihenfolge kann über die Tabelle angepasst werden.
- **Sammlung:** Alle gewählten Tag‑Gruppen werden angezeigt. Tags lassen sich in jedem Lernort in den Einstellungen konfigurieren. Die ausgewählten Tag‑Gruppen erscheinen in der Karte mit der definierten Farbe oder einem hochgeladenen Icon.


### 🏷️ Compatibility
| Plugin Branches | ILIAS Versions | PHP Versions |
|-----------------|----------------|--------------|
| release_9       | 9              | 8.2          |

## 📦 Installation

1. Launch a terminal instance running `bash` from the project's root directory.
2. Enter the following commands to proceed with the plugin installation.

**Create directories**
```bash
mkdir -p Customizing/global/plugins/Services/COPage/PageComponent
cd Customizing/global/plugins/Services/COPage/PageComponent
```

**Clone with SSH**
```bash
git clone git@github.com:kroepelin-projekte/LearnplacesMap.git LearnplacesMap
```

**Or clone with HTTPS**
```bash
git clone https://github.com/kroepelin-projekte/LearnplacesMap.git LearnplacesMap
```

**Switch to branch**
```bash
cd LearnplacesMap
git switch main
```

**Run 'composer du' in the project's root directory.**
```bash
cd /var/www/html
composer du
```

## 💡 Activation

1. Sign in to ILIAS with Administrator privileges.
2. Proceed to `Administration » Extending ILIAS » Plugins`
3. Locate the desired plugin, then select `Actions » Install`, and subsequently, `Actions » Activate`.
