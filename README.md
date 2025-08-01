# GW2 Community Calendar - WordPress Plugin

[![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)](https://github.com/your-username/gw2-community-calendar)
[![WordPress](https://img.shields.io/badge/WordPress-Plugin-green.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Ein dynamisches WordPress-Plugin für die Anzeige von Guild Wars 2 Community Events aus der offiziellen ICS-Datei. Das Plugin bietet eine vollständige Kalenderlösung mit automatischem Download, manueller Upload-Funktion und umfangreichen Anpassungsmöglichkeiten.

## 🌟 Features

- **📅 Vollständiger Kalender**: Monats-, Wochen-, Tages- und Listenansicht
- **🔄 Automatischer Download**: Lädt täglich automatisch die neuesten Events
- **📤 Manueller Upload**: ICS-Datei direkt über Admin-Interface hochladen
- **🌍 Mehrsprachige Unterstützung**: 14 Sprachen unterstützt
- **🎨 Anpassbare Einstellungen**: Wochenstart, benutzerdefiniertes CSS
- **📱 Responsive Design**: Funktioniert auf allen Geräten
- **⚡ Cache-System**: Effiziente Speicherung der Events
- **🔧 Admin-Interface**: Umfassende Verwaltung über WordPress Admin

## 🚀 Schnellstart

### Installation

1. **Plugin herunterladen** und in `/wp-content/plugins/gw2-community-calendar/` entpacken
2. **Plugin aktivieren** im WordPress Admin unter "Plugins"
3. **Einstellungen konfigurieren** unter "Einstellungen → GW2 Kalender"
4. **Shortcode einbinden**: `[gw2_calendar]`

### Kalender einbinden

```php
// In Seiten oder Beiträgen
[gw2_calendar]

// Mit benutzerdefinierten Dimensionen
[gw2_calendar width="800px" height="500px"]
```

## 📋 Detaillierte Installation

Für eine vollständige Anleitung siehe [INSTALLATION.md](INSTALLATION.md).

### Abhängigkeiten

Das Plugin benötigt FullCalendar.js. Fügen Sie folgendes in Ihre `functions.php` ein:

```php
function enqueue_fullcalendar() {
    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), null, true);
    wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', array(), null, true);
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
}
add_action('wp_enqueue_scripts', 'enqueue_fullcalendar');
```

## ⚙️ Konfiguration

### Admin-Einstellungen

Zugriff über **WordPress Admin → Einstellungen → GW2 Kalender**

#### Verfügbare Funktionen:
- **Kalender-Verwaltung**: Download-Buttons und Modus-Umschaltung
- **ICS-Datei Upload**: Direkter Upload über Admin-Interface
- **Kalender-Einstellungen**: Wochenstart und benutzerdefiniertes CSS
- **Cache-Informationen**: Detaillierte Status-Anzeige

#### Wochenstart
- **Montag**: Kalender beginnt mit Montag (europäischer Standard)
- **Sonntag**: Kalender beginnt mit Sonntag (amerikanischer Standard)

#### Benutzerdefiniertes CSS
```css
/* Beispiel: Kalender-Container anpassen */
.gw2-calendar-container {
    border: 2px solid #0073aa;
    border-radius: 8px;
    padding: 15px;
}

/* Beispiel: Event-Farben ändern */
.fc-event {
    background-color: #28a745 !important;
    border-color: #1e7e34 !important;
}
```

## 🌍 Mehrsprachige Unterstützung

Das Plugin unterstützt automatisch die WordPress-Spracheinstellung:

### Unterstützte Sprachen
- Deutsch (de), Englisch (en), Französisch (fr), Spanisch (es)
- Italienisch (it), Niederländisch (nl), Polnisch (pl), Portugiesisch (pt)
- Russisch (ru), Schwedisch (sv), Türkisch (tr), Chinesisch (zh)
- Japanisch (ja), Koreanisch (ko)

### Lokalisierte Elemente
- **Header-Buttons**: "today", "month", "week", "day", "list"
- **Datumsformatierung**: Event-Details im lokalen Format
- **Kalender-Navigation**: Monats- und Tagesnamen

## 📁 Dateistruktur

```
gw2-community-calendar/
├── gw2-community-calendar.php    # Haupt-Plugin-Datei
├── js/
│   ├── calendar.js               # Frontend JavaScript
│   └── admin.js                  # Admin JavaScript
├── css/
│   ├── calendar.css              # Frontend Styles
│   └── admin.css                 # Admin Styles
├── cache/                        # Cache-Verzeichnis
│   ├── .htaccess                 # Schutz-Datei
│   ├── index.php                 # Schutz-Datei
│   └── calendarEvents.ics        # ICS-Datei (wird automatisch erstellt)
├── README.md                     # Diese Datei
├── INSTALLATION.md               # Detaillierte Installationsanleitung
└── MANUAL_UPLOAD_INSTRUCTIONS.md # Upload-Anleitung
```

## 🔧 Troubleshooting

### Häufige Probleme

**Kalender wird nicht angezeigt:**
- FullCalendar.js korrekt eingebunden?
- JavaScript-Fehler in Browser-Konsole?
- Plugin aktiviert?

**Events werden nicht geladen:**
- Cache-Verzeichnis beschreibbar?
- ICS-Datei vorhanden?
- Manueller Download getestet?

**Upload-Probleme:**
- Datei ≤ 5MB?
- Nur .ics Dateien
- Cache-Verzeichnis-Berechtigungen

### HTTP 403 Fehler

Bei 403-Fehlern beim automatischen Download:
1. Verwenden Sie die **manuelle Upload-Funktion** im Admin
2. Laden Sie die ICS-Datei direkt hoch
3. Das Plugin wechselt automatisch in den manuellen Modus

## 📈 Changelog

### Version 1.1.0
- ✨ **Neue Features:**
  - ICS-Datei Upload über Admin-Interface
  - Mehrsprachige Unterstützung (14 Sprachen)
  - Anpassbare Kalender-Einstellungen
  - Cache-Verzeichnis im Plugin-Ordner
  - Plugin-Einstellungen Link in Plugin-Liste
- 🔧 **Verbesserungen:**
  - FullCalendar v6 Lokalisierung
  - Dynamische Button-Text-Übersetzung
  - Verbesserte Fehlerbehandlung
  - Umfassende Admin-Oberfläche
- 🐛 **Bugfixes:**
  - HTTP 403 Fehler-Behandlung
  - Lokalisierungsdatei-Probleme behoben

### Version 1.0.0
- Erste Veröffentlichung
- Automatischer und manueller Download
- Vollständiger Kalender mit verschiedenen Ansichten
- Responsive Design
- Deutsche Lokalisierung

## 🤝 Support

### Hilfe benötigt?

1. **Dokumentation lesen**: [INSTALLATION.md](INSTALLATION.md)
2. **Upload-Anleitung**: [MANUAL_UPLOAD_INSTRUCTIONS.md](MANUAL_UPLOAD_INSTRUCTIONS.md)
3. **WordPress Debug-Logs** überprüfen
4. **Saubere Installation** testen

### Fehler melden

Bitte erstellen Sie ein Issue auf GitHub mit:
- WordPress Version
- Plugin Version
- Fehlerbeschreibung
- Browser/System-Informationen

## 📄 Lizenz

Dieses Plugin ist unter der **GPL v2 oder später** lizenziert.

## 🙏 Credits

- **FullCalendar.js**: Für die Kalender-Funktionalität
- **Guild Wars 2 Community**: Für die Event-Daten
- **WordPress**: Für das Plugin-Framework
- **Bootstrap**: Für UI-Komponenten

---

**Entwickelt von TerisC** | [GitHub Repository](https://github.com/teris/gw2-community-calendar) 