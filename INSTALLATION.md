# GW2 Community Calendar - Installationsanleitung

Diese detaillierte Anleitung führt Sie durch die vollständige Installation und Konfiguration des GW2 Community Calendar Plugins.

## 📋 Voraussetzungen

- **WordPress** Version 5.0 oder höher
- **PHP** Version 7.4 oder höher
- **Administrator-Rechte** in WordPress
- **Schreibrechte** für das Plugin-Verzeichnis

## 🚀 Schritt-für-Schritt Installation

### 1. Plugin herunterladen und installieren

#### Option A: Manuelle Installation (empfohlen)

1. **Repository klonen oder herunterladen:**
   ```bash
   git clone https://github.com/teris/gw2-community-calendar.git
   ```

2. **Plugin-Ordner erstellen:**
   ```bash
   mkdir -p /path/to/wordpress/wp-content/plugins/gw2-community-calendar
   ```

3. **Dateien kopieren:**
   ```bash
   cp -r gw2-community-calendar/* /path/to/wordpress/wp-content/plugins/gw2-community-calendar/
   ```

#### Option B: ZIP-Datei Installation

1. Repository als ZIP herunterladen
2. ZIP-Datei in `/wp-content/plugins/` entpacken
3. Ordner umbenennen zu `gw2-community-calendar`

### 2. Plugin aktivieren

1. **WordPress Admin** öffnen
2. **Plugins → Installierte Plugins** aufrufen
3. **"GW2 Community Calendar"** finden
4. **"Aktivieren"** klicken

### 3. Abhängigkeiten einbinden

Fügen Sie folgenden Code in Ihre `functions.php` ein:

```php
function enqueue_fullcalendar() {
    // FullCalendar CSS
    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
    
    // FullCalendar JS
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), null, true);
    
    // Bootstrap (optional, für bessere Tooltips)
    wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', array(), null, true);
    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
}
add_action('wp_enqueue_scripts', 'enqueue_fullcalendar');
```

### 4. Cache-Verzeichnis erstellen

Das Plugin erstellt automatisch das Cache-Verzeichnis, aber Sie können es auch manuell erstellen:

```bash
mkdir -p /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache
chmod 755 /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache
```

### 5. Plugin konfigurieren

1. **Einstellungen → GW2 Kalender** aufrufen
2. **Cache-Informationen** überprüfen
3. **Download-Modus** wählen (Automatisch/Manuell)

## ⚙️ Erste Konfiguration

### Admin-Einstellungen aufrufen

1. **WordPress Admin** öffnen
2. **Einstellungen → GW2 Kalender** klicken
3. Sie sehen fünf Bereiche:
   - **Kalender-Verwaltung**
   - **ICS-Datei manuell hochladen**
   - **Shortcode Verwendung**
   - **Kalender-Einstellungen**
   - **Cache-Informationen**

### ICS-Datei hochladen

#### Option 1: Automatischer Download (Standard)

1. **"Kalender manuell herunterladen"** klicken
2. Warten Sie auf Erfolgsmeldung
3. **"Status prüfen"** für Bestätigung

#### Option 2: Manueller Upload (bei 403-Fehlern)

1. **ICS-Datei herunterladen:**
   - Browser öffnen
   - `https://de-forum.guildwars2.com/events/download/` aufrufen
   - Datei als `calendarEvents.ics` speichern

2. **Upload über Admin-Interface:**
   - **"Durchsuchen"** klicken
   - `calendarEvents.ics` auswählen
   - **"ICS-Datei hochladen"** klicken

3. **Upload über FTP:**
   ```bash
   # Datei in Cache-Verzeichnis kopieren
   cp calendarEvents.ics /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache/
   chmod 644 /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache/calendarEvents.ics
   ```

### Kalender-Einstellungen

#### Wochenstart konfigurieren

1. **"Kalender-Einstellungen"** Bereich
2. **Wochenstart** auswählen:
   - **Montag**: Europäischer Standard
   - **Sonntag**: Amerikanischer Standard
3. **"Einstellungen speichern"** klicken

#### Benutzerdefiniertes CSS

1. **"Benutzerdefiniertes CSS"** Textfeld
2. CSS-Code eingeben (siehe Beispiele unten)
3. **"Einstellungen speichern"** klicken

**CSS-Beispiele:**
```css
/* Kalender-Container anpassen */
.gw2-calendar-container {
    border: 2px solid #0073aa;
    border-radius: 8px;
    padding: 15px;
    background-color: #f8f9fa;
}

/* Event-Farben ändern */
.fc-event {
    background-color: #28a745 !important;
    border-color: #1e7e34 !important;
}

/* Header-Styling */
.fc-header-toolbar {
    background-color: #e9ecef;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .fc-header-toolbar {
        flex-direction: column;
        gap: 10px;
    }
}
```

## 📝 Kalender einbinden

### Shortcode verwenden

#### Grundlegende Verwendung
```php
[gw2_calendar]
```

#### Mit benutzerdefinierten Dimensionen
```php
[gw2_calendar width="800px" height="500px"]
```

#### In PHP-Code
```php
<?php echo do_shortcode('[gw2_calendar]'); ?>
```

#### In Template-Dateien
```php
<?php 
if (shortcode_exists('gw2_calendar')) {
    echo do_shortcode('[gw2_calendar]');
}
?>
```

### Seiten/Beiträge erstellen

1. **Neue Seite** oder **Beitrag** erstellen
2. **Shortcode** einfügen: `[gw2_calendar]`
3. **Seite veröffentlichen**
4. **Frontend** testen

## 🌍 Mehrsprachige Konfiguration

### WordPress-Sprache einstellen

1. **Einstellungen → Allgemein**
2. **Sprache** auswählen
3. **Änderungen speichern**

### Unterstützte Sprachen

Das Plugin unterstützt automatisch:
- **Deutsch** (de_DE)
- **Englisch** (en_US, en_GB)
- **Französisch** (fr_FR)
- **Spanisch** (es_ES)
- **Italienisch** (it_IT)
- **Niederländisch** (nl_NL)
- **Polnisch** (pl_PL)
- **Portugiesisch** (pt_PT, pt_BR)
- **Russisch** (ru_RU)
- **Schwedisch** (sv_SE)
- **Türkisch** (tr_TR)
- **Chinesisch** (zh_CN, zh_TW)
- **Japanisch** (ja)
- **Koreanisch** (ko_KR)

## 🔧 Erweiterte Konfiguration

### Cron Job konfigurieren

Das Plugin verwendet WordPress Cron für automatische Updates:

```php
// In wp-config.php für bessere Performance
define('DISABLE_WP_CRON', true);
```

Dann manuellen Cron Job einrichten:
```bash
# Alle 5 Minuten ausführen
*/5 * * * * wget -q -O /dev/null "https://your-site.com/wp-cron.php?doing_wp_cron"
```

### Cache-Optimierung

#### Cache-Verzeichnis-Berechtigungen
```bash
chmod 755 /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache
chmod 644 /path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache/calendarEvents.ics
```

#### Cache-Löschung
```php
// Cache manuell löschen
delete_option('gw2_calendar_cache_time');
unlink('/path/to/wordpress/wp-content/plugins/gw2-community-calendar/cache/calendarEvents.ics');
```

### Debug-Modus aktivieren

Fügen Sie in `wp-config.php` hinzu:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 🧪 Installation testen

### Funktionstests

1. **Admin-Bereich:**
   - Plugin aktiviert?
   - Einstellungsseite erreichbar?
   - Upload-Funktion funktioniert?

2. **Frontend:**
   - Kalender wird angezeigt?
   - Events werden geladen?
   - Responsive Design funktioniert?

3. **Browser-Konsole:**
   - JavaScript-Fehler?
   - AJAX-Requests erfolgreich?
   - FullCalendar geladen?

### Debug-Informationen

```php
// Debug-Informationen anzeigen
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<script>console.log("GW2 Calendar Debug:", ' . json_encode([
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gw2_calendar_nonce'),
            'locale' => get_locale(),
            'week_start' => get_option('gw2_calendar_week_start', 'monday')
        ]) . ');</script>';
    }
});
```

## 🚨 Troubleshooting

### Häufige Probleme

#### Plugin wird nicht aktiviert
- **PHP-Version** prüfen (≥ 7.4)
- **WordPress-Version** prüfen (≥ 5.0)
- **Fehlerprotokoll** überprüfen

#### Kalender wird nicht angezeigt
- **FullCalendar.js** korrekt eingebunden?
- **JavaScript-Fehler** in Browser-Konsole?
- **Shortcode** korrekt eingegeben?

#### Events werden nicht geladen
- **Cache-Datei** vorhanden?
- **Dateigröße** > 0?
- **Berechtigungen** korrekt?

#### Upload-Probleme
- **Dateigröße** ≤ 5MB?
- **Dateityp** .ics?
- **Cache-Verzeichnis** beschreibbar?

### Fehlerprotokoll prüfen

```bash
# WordPress Debug-Log
tail -f /path/to/wordpress/wp-content/debug.log

# PHP Error-Log
tail -f /var/log/php_errors.log

# Apache/Nginx Error-Log
tail -f /var/log/apache2/error.log
```

## 📞 Support

### Hilfe benötigt?

1. **Dokumentation** lesen: [README.md](README.md)
2. **Upload-Anleitung**: [MANUAL_UPLOAD_INSTRUCTIONS.md](MANUAL_UPLOAD_INSTRUCTIONS.md)
3. **GitHub Issues** durchsuchen
4. **Neues Issue** erstellen

### Issue erstellen

Bitte geben Sie an:
- **WordPress Version**
- **Plugin Version**
- **PHP Version**
- **Browser/System**
- **Fehlerbeschreibung**
- **Debug-Log** (falls verfügbar)

---

**Installation erfolgreich?** → [Zurück zur README.md](README.md) 