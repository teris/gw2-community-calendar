# ICS-Datei Upload - Lösung für HTTP 403 Fehler

Diese Anleitung erklärt, wie Sie ICS-Dateien manuell hochladen können, wenn der automatische Download aufgrund von HTTP 403 Fehlern nicht funktioniert.

## 🚨 Problem

Der GW2 Community Calendar erhält einen **HTTP 403 Fehler** beim Versuch, die ICS-Datei automatisch herunterzuladen. Dies liegt an Server-Einschränkungen oder CORS-Policies.

## ✅ Lösung: Manueller Upload

### Option 1: Upload über Admin-Interface (empfohlen)

1. **WordPress Admin** → **Einstellungen** → **GW2 Kalender**
2. Scrollen Sie zum Bereich **"ICS-Datei manuell hochladen"**
3. **"Durchsuchen"** klicken und `calendarEvents.ics` auswählen
4. **"ICS-Datei hochladen"** klicken
5. Das Plugin speichert die Datei und aktiviert automatisch den manuellen Modus

### Option 2: Manueller Upload über FTP/Dateimanager

#### Schritt 1: ICS-Datei herunterladen
- **Browser öffnen**
- **URL aufrufen**: `https://de-forum.guildwars2.com/events/download/`
- **Datei speichern** als: `calendarEvents.ics`

#### Schritt 2: Datei hochladen
- **WordPress-Verzeichnis** navigieren
- **Pfad**: `wp-content/plugins/gw2-community-calendar/cache/`
- **Verzeichnis erstellen** (falls nicht vorhanden)
- **Datei hochladen**: `calendarEvents.ics`

## ⚙️ Plugin konfigurieren

### Admin-Einstellungen aufrufen

1. **WordPress Admin** → **Einstellungen** → **GW2 Kalender**
2. **Fünf Bereiche** verfügbar:
   - **Kalender-Verwaltung**: Download-Buttons und Modus-Umschaltung
   - **ICS-Datei manuell hochladen**: Upload-Formular
   - **Shortcode Verwendung**: Anleitung für die Einbindung
   - **Kalender-Einstellungen**: Wochenstart und benutzerdefiniertes CSS
   - **Cache-Informationen**: Detaillierte Status-Anzeige

### Download-Modus umschalten

1. **"Manueller Modus"** klicken (aktiviert manuellen Modus)
2. **Button ändert sich** zu "Automatischer Modus"
3. **Status prüfen** für Bestätigung

**Hinweis**: Bei Upload über Admin-Interface wird der manuelle Modus automatisch aktiviert.

## 🎨 Kalender-Einstellungen anpassen

### Wochenstart
- **Montag**: Kalender beginnt mit Montag (europäischer Standard)
- **Sonntag**: Kalender beginnt mit Sonntag (amerikanischer Standard)

### Benutzerdefiniertes CSS
```css
/* Kalender-Container anpassen */
.gw2-calendar-container {
    border: 2px solid #0073aa;
    border-radius: 8px;
    padding: 15px;
}

/* Event-Farben ändern */
.fc-event {
    background-color: #28a745 !important;
    border-color: #1e7e34 !important;
}

/* Header-Styling */
.fc-header-toolbar {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
}
```

## 🌍 Mehrsprachige Unterstützung

Das Plugin unterstützt automatisch die WordPress-Spracheinstellung:

### Unterstützte Sprachen
- **Deutsch** (de), **Englisch** (en), **Französisch** (fr), **Spanisch** (es)
- **Italienisch** (it), **Niederländisch** (nl), **Polnisch** (pl), **Portugiesisch** (pt)
- **Russisch** (ru), **Schwedisch** (sv), **Türkisch** (tr), **Chinesisch** (zh)
- **Japanisch** (ja), **Koreanisch** (ko)

### Lokalisierte Elemente
- **Header-Buttons**: "today", "month", "week", "day", "list"
- **Datumsformatierung**: Event-Details im lokalen Format
- **Kalender-Navigation**: Monats- und Tagesnamen
- **FullCalendar v6**: Nutzt eingebaute Lokalisierungsfunktionen

### Konfiguration
Die Lokalisierung erfolgt automatisch basierend auf der WordPress-Spracheinstellung unter **Einstellungen > Allgemein > Sprache**.

## 📁 Dateistruktur

```
wp-content/
└── plugins/
    └── gw2-community-calendar/
        ├── cache/
        │   ├── .htaccess                 # Schutz-Datei
        │   ├── index.php                 # Schutz-Datei
        │   └── calendarEvents.ics        # ICS-Datei (wird hier gespeichert)
        ├── gw2-community-calendar.php
        ├── js/
        └── css/
```

## 🔄 Automatische Aktualisierung

### Option 1: Manueller Modus (empfohlen)
- **Regelmäßig** ICS-Datei manuell hochladen
- **Admin-Interface** oder **FTP** verwenden
- **Keine 403-Fehler** mehr

### Option 2: Automatischer Modus
- Plugin versucht automatischen Download
- **Bei 403-Fehler** → automatischer Wechsel zu manueller Modus
- **Datei manuell hochladen** erforderlich

## 🚨 Troubleshooting

### Upload-Probleme
- **Datei zu groß**: Maximale Größe ist 5MB
- **Falscher Dateityp**: Nur .ics Dateien werden akzeptiert
- **Ungültige ICS-Datei**: Plugin prüft Inhalt und lehnt ungültige Dateien ab
- **Berechtigungsfehler**: Cache-Verzeichnis muss beschreibbar sein

### Datei wird nicht gefunden
- **Pfad prüfen**: `wp-content/plugins/gw2-community-calendar/cache/calendarEvents.ics`
- **Dateiname**: Muss exakt `calendarEvents.ics` sein
- **Dateirechte**: 644 oder 664

### Kalender zeigt keine Events
1. **Einstellungen** → **GW2 Kalender**
2. **Cache-Informationen** prüfen
3. **Cache-Datei** existiert?
4. **Dateigröße** > 0?

### Wechsel zwischen Modi
- **Manueller Modus**: Verwendet lokale Datei
- **Automatischer Modus**: Versucht Download von GW2-Server
- **Bei 403-Fehler**: Automatischer Wechsel zu manueller Modus

## 📞 Support

### Hilfe benötigt?
1. **WordPress-Fehlerprotokolle** prüfen
2. **Cache-Verzeichnis** beschreibbar?
3. **Direkten Zugriff** auf ICS-URL im Browser testen
4. **Admin-Einstellungsseite** für detaillierte Status-Informationen
5. **Upload-Funktion** für einfacheres Datei-Management

### Weitere Dokumentation
- **[README.md](README.md)**: Übersicht und Features
- **[INSTALLATION.md](INSTALLATION.md)**: Detaillierte Installationsanleitung

---

**Upload erfolgreich?** → [Zurück zur README.md](README.md) 