<?php
/**
 * Plugin Name: GW2 Community Calendar
 * Description: Dynamischer Kalender für Guild Wars 2 Community Events
 * Version: 1.5.0
 * Author: TerisC
 * License: GPL v2 or later
 * Plugin URI: https://github.com/teris/gw2-community-calendar
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class GW2CommunityCalendar {
    
    private $ics_url = 'https://de-forum.guildwars2.com/events/download/';
    private $cache_file;
    private $cache_duration = 3600; // 1 Stunde in Sekunden (für dynamisch generierte ICS)
    private $use_manual_file = true; // Neue Option: Verwende manuelle Datei statt Download
    
    public function __construct() {
        $this->cache_file = plugin_dir_path(__FILE__) . 'cache/calendarEvents.ics';
        
        // Lade gespeicherte Einstellungen
        $this->use_manual_file = get_option('gw2_calendar_use_manual_file', true);
        $this->week_start = get_option('gw2_calendar_week_start', 'monday');
        $this->custom_css = get_option('gw2_calendar_custom_css', '');
        
        // Hooks registrieren
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_download_calendar', array($this, 'ajax_download_calendar'));
        add_action('wp_ajax_nopriv_download_calendar', array($this, 'ajax_download_calendar'));
        add_action('wp_ajax_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_nopriv_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_get_download_status', array($this, 'ajax_get_download_status'));
        add_action('wp_ajax_toggle_download_mode', array($this, 'ajax_toggle_download_mode'));
        add_action('wp_ajax_upload_ics_file', array($this, 'ajax_upload_ics_file'));
        add_action('wp_ajax_save_calendar_settings', array($this, 'ajax_save_calendar_settings'));
        add_action('wp_ajax_get_event_ids', array($this, 'ajax_get_event_ids'));
        add_action('wp_ajax_test_download_url', array($this, 'ajax_test_download_url'));
        
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Plugin-Aktionslinks hinzufügen
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        
        // Shortcode registrieren
        add_shortcode('gw2_calendar', array($this, 'calendar_shortcode'));
        add_shortcode('gw2_events_list', array($this, 'events_list_shortcode'));
        add_shortcode('gw2_next_events', array($this, 'next_events_shortcode'));
        add_shortcode('gw2_event_countdown', array($this, 'event_countdown_shortcode'));
        add_shortcode('gw2_today_events', array($this, 'today_events_shortcode'));
        
        // Cron Job für automatischen Download (nur wenn nicht manueller Modus)
        add_action('gw2_calendar_cron', array($this, 'download_calendar_data'));
        
        // Plugin Aktivierung/Deaktivierung
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Cache-Verzeichnis erstellen
        $cache_dir = dirname($this->cache_file);
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
    }
    
    public function activate() {
        // Cron Job einrichten (stündlich für dynamisch generierte ICS)
        if (!wp_next_scheduled('gw2_calendar_cron')) {
            wp_schedule_event(time(), 'hourly', 'gw2_calendar_cron');
        }
        
        // Ersten Download durchführen
        $this->download_calendar_data();
        
        // Test-Log für Debugging
        error_log('GW2 Calendar: Plugin aktiviert, Cache-Datei: ' . $this->cache_file);
    }
    
    public function deactivate() {
        // Cron Job entfernen
        wp_clear_scheduled_hook('gw2_calendar_cron');
    }
    
    public function enqueue_scripts() {
        // Nur laden wenn der GW2 Kalender Shortcode verwendet wird
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gw2_calendar')) {
            // Robuste FullCalendar-Integration mit mehreren CDN-Quellen
            $fullcalendar_css_url = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css';
            $fullcalendar_js_url = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js';
            
            // Fallback-URLs falls jsdelivr nicht funktioniert
            $fallback_css_url = 'https://unpkg.com/fullcalendar@6.1.8/index.global.min.css';
            $fallback_js_url = 'https://unpkg.com/fullcalendar@6.1.8/index.global.min.js';
            
            wp_enqueue_style('fullcalendar', $fullcalendar_css_url, array(), '6.1.8');
            wp_enqueue_script('fullcalendar', $fullcalendar_js_url, array(), '6.1.8', true);
            
            // Fallback-Skript für FullCalendar
            wp_add_inline_script('fullcalendar', "
                // Fallback für FullCalendar falls CDN fehlschlägt
                if (typeof FullCalendar === 'undefined') {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = '$fallback_css_url';
                    document.head.appendChild(link);
                    
                    var script = document.createElement('script');
                    script.src = '$fallback_js_url';
                    script.onload = function() {
                        console.log('FullCalendar über Fallback-CDN geladen');
                    };
                    document.head.appendChild(script);
                }
            ");
            
            // Bootstrap für bessere Tooltips (optional)
            wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', array(), '5.1.3', true);
            wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css', array(), '5.1.3');
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('gw2-calendar', plugin_dir_url(__FILE__) . 'js/calendar.js', array('jquery', 'fullcalendar'), '1.0.0', true);
        wp_enqueue_style('gw2-calendar', plugin_dir_url(__FILE__) . 'css/calendar.css', array(), '1.0.0');
        
        // Benutzerdefiniertes CSS hinzufügen
        if (!empty($this->custom_css)) {
            wp_add_inline_style('gw2-calendar', $this->custom_css);
        }
        
        // AJAX URL für JavaScript
        wp_localize_script('gw2-calendar', 'gw2_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gw2_calendar_nonce'),
            'admin_nonce' => wp_create_nonce('gw2_calendar_admin_nonce'),
            'week_start' => $this->week_start,
            'locale' => get_locale()
        ));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'GW2 Community Calendar',
            'GW2 Kalender',
            'manage_options',
            'gw2-community-calendar',
            array($this, 'admin_page')
        );
    }
    
    public function add_plugin_action_links($links) {
        // Einstellungs-Link hinzufügen
        $settings_link = '<a href="' . admin_url('options-general.php?page=gw2-community-calendar') . '">' . __('Einstellungen', 'gw2-calendar') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    public function admin_page() {
        // Nur laden wenn wir auf der Admin-Seite sind
        wp_enqueue_script('jquery');
        wp_enqueue_script('gw2-calendar-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('gw2-calendar-admin', plugin_dir_url(__FILE__) . 'css/admin.css', array(), '1.0.0');
        
        wp_localize_script('gw2-calendar-admin', 'gw2_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gw2_calendar_admin_nonce')
        ));
        
        // Admin-Seite HTML
        ?>
        <div class="wrap">
            <h1>GW2 Community Calendar Einstellungen</h1>
            
            <div class="gw2-admin-container">
                <div class="gw2-admin-columns">
                    <!-- Linke Spalte -->
                    <div class="gw2-admin-column-left">
                        <div class="gw2-admin-section">
                            <h2>Kalender-Verwaltung</h2>
                            <p>Verwalten Sie die GW2 Community Kalender-Events und Download-Einstellungen.</p>
                            
                            <div class="gw2-admin-controls">
                                <button id="gw2-download-calendar" class="button button-primary">Kalender herunterladen</button>
                                <button id="gw2-toggle-mode" class="button <?php echo $this->use_manual_file ? 'button-secondary' : 'button-primary'; ?>">
                                    <?php echo $this->use_manual_file ? 'Manueller Modus' : 'Automatischer Modus'; ?>
                                </button>
                                <button id="gw2-check-status" class="button button-secondary">Status prüfen</button>
                                <button id="gw2-test-urls" class="button button-secondary">Download-URLs testen</button>
                                <span id="gw2-download-status"></span>
                            </div>
                        </div>
                        
                        <div class="gw2-admin-section">
                            <h2>ICS-Datei manuell hochladen</h2>
                            <p>Laden Sie eine ICS-Datei direkt über das Admin-Interface hoch. Die Datei wird automatisch im Cache-Verzeichnis gespeichert und der manuelle Modus wird aktiviert.</p>
                            
                            <form id="gw2-upload-form" enctype="multipart/form-data">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="ics_file">ICS-Datei auswählen:</label>
                                        </th>
                                        <td>
                                            <input type="file" id="ics_file" name="ics_file" accept=".ics,text/calendar" required>
                                            <p class="description">Wählen Sie eine .ics Datei aus (z.B. calendarEvents.ics)</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div class="gw2-upload-controls">
                                    <button type="submit" id="gw2-upload-button" class="button button-primary">
                                        <span class="gw2-upload-text">ICS-Datei hochladen</span>
                                        <span class="gw2-upload-loading" style="display: none;">
                                            <span class="gw2-loading"></span>Lädt hoch...
                                        </span>
                                    </button>
                                    <span id="gw2-upload-status"></span>
                                </div>
                            </form>
                        </div>
                        
                        <div class="gw2-admin-section">
                            <h2>Kalender-Einstellungen</h2>
                            <p>Passen Sie das Aussehen und Verhalten des Kalenders an.</p>
                            
                            <form id="gw2-settings-form">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="week_start">Woche beginnt mit:</label>
                                        </th>
                                        <td>
                                            <select id="week_start" name="week_start">
                                                <option value="monday" <?php echo ($this->week_start === 'monday') ? 'selected' : ''; ?>>Montag</option>
                                                <option value="sunday" <?php echo ($this->week_start === 'sunday') ? 'selected' : ''; ?>>Sonntag</option>
                                            </select>
                                            <p class="description">Bestimmt, welcher Tag als erster Tag der Woche angezeigt wird.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="custom_css">Benutzerdefiniertes CSS:</label>
                                        </th>
                                        <td>
                                            <textarea id="custom_css" name="custom_css" rows="10" cols="50" placeholder="/* Hier können Sie das Aussehen des Kalenders anpassen */
.gw2-calendar-container {
    /* Ihre CSS-Regeln hier */
}"><?php echo esc_textarea($this->custom_css); ?></textarea>
                                            <p class="description">Fügen Sie hier Ihre eigenen CSS-Regeln hinzu, um das Aussehen des Kalenders anzupassen.</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div class="gw2-settings-controls">
                                    <button type="submit" id="gw2-save-settings" class="button button-primary">
                                        <span class="gw2-save-text">Einstellungen speichern</span>
                                        <span class="gw2-save-loading" style="display: none;">
                                            <span class="gw2-loading"></span>Speichere...
                                        </span>
                                    </button>
                                    <span id="gw2-settings-status"></span>
                                </div>
                            </form>
                        </div>
                        
                        <div class="gw2-admin-section">
                            <h2>Cache-Informationen</h2>
                            <div id="gw2-cache-info">
                                <p>Laden...</p>
                            </div>
                        </div>
                        
                        <div class="gw2-admin-section">
                            <h2>Verfügbare Event-IDs</h2>
                            <p>Hier finden Sie alle verfügbaren Event-IDs für den Countdown-Shortcode:</p>
                            <div id="gw2-event-ids-list">
                                <p>Laden...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rechte Spalte -->
                    <div class="gw2-admin-column-right">
                        <div class="gw2-admin-section">
                            <h2>Shortcode Verwendung</h2>
                            <p>Fügen Sie einen der folgenden Shortcodes in Ihre Seiten oder Beiträge ein:</p>
                            
                            <h3>Vollständiger Kalender:</h3>
                            <code>[gw2_calendar]</code>
                            <ul>
                                <li><code>width</code> - Breite des Kalenders (Standard: 100%)</li>
                                <li><code>height</code> - Höhe des Kalenders (Standard: 600px)</li>
                            </ul>
                            <p><strong>Beispiel:</strong> <code>[gw2_calendar width="800px" height="500px"]</code></p>
                            
                            <h3>Event-Liste:</h3>
                            <code>[gw2_events_list]</code>
                            <ul>
                                <li><code>limit</code> - Anzahl der Events (Standard: 10)</li>
                                <li><code>show_date</code> - Datum anzeigen (Standard: true)</li>
                                <li><code>show_time</code> - Zeit anzeigen (Standard: true)</li>
                                <li><code>show_location</code> - Ort anzeigen (Standard: true)</li>
                                <li><code>show_description</code> - Beschreibung anzeigen (Standard: false)</li>
                                <li><code>css_class</code> - CSS-Klasse für das Container-Element (Standard: gw2-events-list)</li>
                                <li><code>debug</code> - Debug-Informationen anzeigen (Standard: false)</li>
                            </ul>
                            <p><strong>Beispiel:</strong> <code>[gw2_events_list limit="5" show_description="true"]</code></p>
                            <p><strong>Debug-Beispiel:</strong> <code>[gw2_events_list debug="true"]</code> - Zeigt Debug-Informationen für Datum-Parsing</p>
                            
                            <h3>Nächste Events:</h3>
                            <code>[gw2_next_events]</code>
                            <ul>
                                <li><code>count</code> - Anzahl der Events (Standard: 5)</li>
                                <li><code>days_ahead</code> - Tage in die Zukunft (Standard: 30)</li>
                            </ul>
                            <p><strong>Beispiel:</strong> <code>[gw2_next_events count="3" days_ahead="14"]</code></p>
                            
                            <h3>Event Countdown:</h3>
                            <code>[gw2_event_countdown]</code>
                            <ul>
                                <li><code>event_id</code> - Spezifische Event-ID (Standard: nächstes Event)</li>
                            </ul>
                            <p><strong>Beispiel:</strong> <code>[gw2_event_countdown event_id="event123"]</code></p>
                            
                            <h3>Heutige Events:</h3>
                            <code>[gw2_today_events]</code>
                            <ul>
                                <li><code>show_time</code> - Zeit anzeigen (Standard: true)</li>
                                <li><code>show_location</code> - Ort anzeigen (Standard: true)</li>
                            </ul>
                            <p><strong>Beispiel:</strong> <code>[gw2_today_events show_location="false"]</code></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_enqueue_scripts() {
        // Nur auf der Admin-Seite laden
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_gw2-community-calendar') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('gw2-calendar-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('gw2-calendar-admin', plugin_dir_url(__FILE__) . 'css/admin.css', array(), '1.0.0');
            
            wp_localize_script('gw2-calendar-admin', 'gw2_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gw2_calendar_admin_nonce')
            ));
        }
        
        // Globale AJAX-Variablen für Frontend
        wp_localize_script('gw2-calendar-admin', 'gw2_calendar_nonce', wp_create_nonce('gw2_calendar_nonce'));
    }
    
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px'
        ), $atts);
        
        // Generate unique ID for each calendar instance
        static $calendar_counter = 0;
        $calendar_counter++;
        $unique_id = 'gw2-calendar-' . $calendar_counter;
        
        return '<div class="gw2-calendar-container" style="width: ' . esc_attr($atts['width']) . ';">
            <div id="' . $unique_id . '" class="gw2-calendar"></div>
        </div>';
    }
    
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_date' => 'true',
            'show_time' => 'true',
            'show_location' => 'true',
            'show_description' => 'false',
            'css_class' => 'gw2-events-list',
            'debug' => 'false'
        ), $atts);
        
        $events = $this->get_calendar_events();
        $limit = intval($atts['limit']);
        $show_date = $atts['show_date'] === 'true';
        $show_time = $atts['show_time'] === 'true';
        $show_location = $atts['show_location'] === 'true';
        $show_description = $atts['show_description'] === 'true';
        $debug = $atts['debug'] === 'true';
        
        // Debug: Show all events before sorting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GW2 Calendar events_list_shortcode - All events before sorting:');
            error_log('GW2 Calendar events_list_shortcode - WordPress timezone: ' . get_option('timezone_string', 'Not set'));
            error_log('GW2 Calendar events_list_shortcode - PHP timezone: ' . date_default_timezone_get());
            error_log('GW2 Calendar events_list_shortcode - Current date/time: ' . date('Y-m-d H:i:s T'));
            error_log('GW2 Calendar events_list_shortcode - Current UTC: ' . gmdate('Y-m-d H:i:s'));
            foreach ($events as $event) {
                error_log('  - ' . $event['title'] . ' (UID: ' . $event['id'] . ') - Start: ' . $event['start']);
            }
        }
        
        // Sortiere Events nach Datum
        usort($events, function($a, $b) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GW2 Calendar events_list_shortcode - Sorting: ' . $a['title'] . ' (' . $a['start'] . ') vs ' . $b['title'] . ' (' . $b['start'] . ')');
            }
            // Use DateTime objects instead of strtotime for more reliable sorting
            $date_a = new DateTime($a['start'], new DateTimeZone('UTC'));
            $date_b = new DateTime($b['start'], new DateTimeZone('UTC'));
            return $date_a <=> $date_b;
        });
        
        // Begrenze Anzahl
        $events = array_slice($events, 0, $limit);
        
        $output = '<div class="' . esc_attr($atts['css_class']) . '">';
        $output .= '<h3>GW2 Community Events</h3>';
        
        if (empty($events)) {
            $output .= '<p>Keine Events verfügbar.</p>';
        } else {
            if ($debug) {
                $output .= '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
                $output .= '<strong>Debug-Informationen:</strong><br>';
                $output .= 'Anzahl Events: ' . count($events) . '<br>';
                $output .= 'Server-Zeitzone: ' . date_default_timezone_get() . '<br>';
                $output .= 'Aktuelles Datum: ' . date('Y-m-d H:i:s T') . '<br>';
                $output .= '</div>';
            }
            
            $output .= '<ul class="gw2-events-ul">';
            foreach ($events as $event) {
                $output .= '<li class="gw2-event-item">';
                $output .= '<h4>' . esc_html($event['title']) . '</h4>';
                
                // Special debug for the problematic event
                if (defined('WP_DEBUG') && WP_DEBUG && $event['id'] === '164-1-47fbf5e5b75e97f949cf6d7a8831ed71@de-forum.guildwars2.com') {
                    error_log('GW2 Calendar - PROBLEMATIC EVENT FOUND:');
                    error_log('  Title: ' . $event['title']);
                    error_log('  UID: ' . $event['id']);
                    error_log('  Start: ' . $event['start']);
                    $test_date = new DateTime($event['start'], new DateTimeZone('UTC'));
                    error_log('  Parsed DateTime: ' . $test_date->format('Y-m-d H:i:s T'));
                    error_log('  Formatted date: ' . $test_date->format('d.m.Y'));
                }
                
                if ($show_date || $show_time) {
                    // Erstelle DateTime-Objekt mit UTC Zeitzone um Konvertierungsprobleme zu vermeiden
                    $event_date = new DateTime($event['start'], new DateTimeZone('UTC'));
                    
                    // Debug-Ausgabe (kann später entfernt werden)
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GW2 Calendar Debug - Event: ' . $event['title'] . ', Original start: ' . $event['start'] . ', Parsed date: ' . $event_date->format('Y-m-d H:i:s T'));
                    }
                    
                    if ($debug) {
                        $output .= '<div style="font-size: 0.8em; color: #666; margin: 5px 0;">';
                        $output .= 'Debug: Original start="' . esc_html($event['start']) . '", Parsed date="' . $event_date->format('Y-m-d H:i:s T') . '"';
                        $output .= '</div>';
                    }
                    
                    $output .= '<div class="gw2-event-time">';
                    if ($show_date) {
                        $output .= '<span class="gw2-event-date">' . $event_date->format('d.m.Y') . '</span>';
                    }
                    if ($show_time) {
                        $output .= '<span class="gw2-event-time">' . $event_date->format('H:i') . ' Uhr</span>';
                    }
                    $output .= '</div>';
                }
                
                if ($show_location && !empty($event['location'])) {
                    $output .= '<div class="gw2-event-location">📍 ' . esc_html($event['location']) . '</div>';
                }
                
                if ($show_description && !empty($event['description'])) {
                    $output .= '<div class="gw2-event-description">' . esc_html($event['description']) . '</div>';
                }
                
                $output .= '</li>';
            }
            $output .= '</ul>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    public function next_events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 5,
            'days_ahead' => 30,
            'css_class' => 'gw2-next-events'
        ), $atts);
        
        $events = $this->get_calendar_events();
        $count = intval($atts['count']);
        $days_ahead = intval($atts['days_ahead']);
        
        // Filtere Events in der Zukunft
        $now = new DateTime();
        $future_limit = clone $now;
        $future_limit->add(new DateInterval('P' . $days_ahead . 'D'));
        
        $future_events = array_filter($events, function($event) use ($now, $future_limit) {
            $event_date = new DateTime($event['start']);
            return $event_date >= $now && $event_date <= $future_limit;
        });
        
        // Sortiere nach Datum
        usort($future_events, function($a, $b) {
            // Use DateTime objects instead of strtotime for more reliable sorting
            $date_a = new DateTime($a['start'], new DateTimeZone('UTC'));
            $date_b = new DateTime($b['start'], new DateTimeZone('UTC'));
            return $date_a <=> $date_b;
        });
        
        // Begrenze Anzahl
        $future_events = array_slice($future_events, 0, $count);
        
        $output = '<div class="' . esc_attr($atts['css_class']) . '">';
        $output .= '<h3>Nächste GW2 Events</h3>';
        
        if (empty($future_events)) {
            $output .= '<p>Keine anstehenden Events in den nächsten ' . $days_ahead . ' Tagen.</p>';
        } else {
            $output .= '<ul class="gw2-next-events-ul">';
            foreach ($future_events as $event) {
                $event_date = new DateTime($event['start']);
                $days_until = $now->diff($event_date)->days;
                
                $output .= '<li class="gw2-next-event-item">';
                $output .= '<div class="gw2-next-event-title">' . esc_html($event['title']) . '</div>';
                $output .= '<div class="gw2-next-event-date">' . $event_date->format('d.m.Y H:i') . ' Uhr</div>';
                $output .= '<div class="gw2-next-event-countdown">in ' . $days_until . ' Tag' . ($days_until != 1 ? 'en' : '') . '</div>';
                
                if (!empty($event['location'])) {
                    $output .= '<div class="gw2-next-event-location">📍 ' . esc_html($event['location']) . '</div>';
                }
                
                $output .= '</li>';
            }
            $output .= '</ul>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    public function event_countdown_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => '',
            'css_class' => 'gw2-event-countdown'
        ), $atts);
        
        $events = $this->get_calendar_events();
        $event_id = $atts['event_id'];
        
        // Finde das spezifische Event
        $target_event = null;
        if (!empty($event_id)) {
            foreach ($events as $event) {
                if (isset($event['id']) && $event['id'] === $event_id) {
                    $target_event = $event;
                    break;
                }
            }
        } else {
            // Nehme das nächste Event
            $now = new DateTime();
            $future_events = array_filter($events, function($event) use ($now) {
                $event_date = new DateTime($event['start']);
                return $event_date > $now;
            });
            
            if (!empty($future_events)) {
                usort($future_events, function($a, $b) {
                    // Use DateTime objects instead of strtotime for more reliable sorting
                    $date_a = new DateTime($a['start'], new DateTimeZone('UTC'));
                    $date_b = new DateTime($b['start'], new DateTimeZone('UTC'));
                    return $date_a <=> $date_b;
                });
                $target_event = $future_events[0];
            }
        }
        
        $output = '<div class="' . esc_attr($atts['css_class']) . '">';
        
        if ($target_event) {
            $event_date = new DateTime($target_event['start']);
            $now = new DateTime();
            $interval = $now->diff($event_date);
            
            $output .= '<h3>Countdown: ' . esc_html($target_event['title']) . '</h3>';
            $output .= '<div class="gw2-countdown-timer">';
            $output .= '<div class="gw2-countdown-days">' . $interval->days . ' Tage</div>';
            $output .= '<div class="gw2-countdown-time">' . $interval->h . 'h ' . $interval->i . 'm</div>';
            $output .= '</div>';
            $output .= '<div class="gw2-countdown-date">' . $event_date->format('d.m.Y H:i') . ' Uhr</div>';
            
            if (!empty($target_event['location'])) {
                $output .= '<div class="gw2-countdown-location">📍 ' . esc_html($target_event['location']) . '</div>';
            }
        } else {
            $output .= '<p>Kein Event für Countdown gefunden.</p>';
        }
        
        $output .= '</div>';
        return $output;
    }
    

    
    public function today_events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'css_class' => 'gw2-today-events',
            'show_time' => 'true',
            'show_location' => 'true'
        ), $atts);
        
        $events = $this->get_calendar_events();
        $show_time = $atts['show_time'] === 'true';
        $show_location = $atts['show_location'] === 'true';
        
        // Filtere Events von heute
        $today = new DateTime();
        $today_start = clone $today;
        $today_start->setTime(0, 0, 0);
        $today_end = clone $today;
        $today_end->setTime(23, 59, 59);
        
        $today_events = array_filter($events, function($event) use ($today_start, $today_end) {
            $event_date = new DateTime($event['start']);
            return $event_date >= $today_start && $event_date <= $today_end;
        });
        
        // Sortiere nach Zeit
        usort($today_events, function($a, $b) {
            // Use DateTime objects instead of strtotime for more reliable sorting
            $date_a = new DateTime($a['start'], new DateTimeZone('UTC'));
            $date_b = new DateTime($b['start'], new DateTimeZone('UTC'));
            return $date_a <=> $date_b;
        });
        
        $output = '<div class="' . esc_attr($atts['css_class']) . '">';
        $output .= '<h3>Heutige GW2 Events</h3>';
        
        if (empty($today_events)) {
            $output .= '<p>Heute keine Events geplant.</p>';
        } else {
            $output .= '<ul class="gw2-today-events-ul">';
            foreach ($today_events as $event) {
                $event_date = new DateTime($event['start']);
                
                $output .= '<li class="gw2-today-event-item">';
                $output .= '<div class="gw2-today-event-title">' . esc_html($event['title']) . '</div>';
                
                if ($show_time) {
                    $output .= '<div class="gw2-today-event-time">' . $event_date->format('H:i') . ' Uhr</div>';
                }
                
                if ($show_location && !empty($event['location'])) {
                    $output .= '<div class="gw2-today-event-location">📍 ' . esc_html($event['location']) . '</div>';
                }
                
                $output .= '</li>';
            }
            $output .= '</ul>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    public function ajax_download_calendar() {
        // Sicherheitscheck
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }
        
        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');
        
        error_log('GW2 Calendar: Manueller Download gestartet');
        
        $result = $this->download_calendar_data();
        
        if ($result['success']) {
            error_log('GW2 Calendar: Manueller Download erfolgreich');
            wp_send_json_success($result['message']);
        } else {
            error_log('GW2 Calendar: Manueller Download fehlgeschlagen - ' . $result['message']);
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_get_calendar_events() {
        check_ajax_referer('gw2_calendar_nonce', 'nonce');
        
        $events = $this->get_calendar_events();
        wp_send_json_success($events);
    }
    
    public function ajax_get_download_status() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }
        
        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');
        
        $status = array(
            'cache_file_exists' => file_exists($this->cache_file),
            'cache_file_size' => file_exists($this->cache_file) ? filesize($this->cache_file) : 0,
            'cache_file_time' => file_exists($this->cache_file) ? filemtime($this->cache_file) : 0,
            'cache_dir_writable' => is_writable(dirname($this->cache_file)),
            'ics_url' => $this->ics_url,
            'download_url' => 'https://de-forum.guildwars2.com/events/download/',
            'use_manual_file' => $this->use_manual_file,
            'cache_file_path' => $this->cache_file
        );
        
        wp_send_json_success($status);
    }

    public function ajax_toggle_download_mode() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }

        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');

        $this->use_manual_file = !$this->use_manual_file;
        update_option('gw2_calendar_use_manual_file', $this->use_manual_file);

        $message = $this->use_manual_file ? 'Manueller Download aktiviert' : 'Automatischer Download aktiviert';
        wp_send_json_success($message);
    }

    public function ajax_upload_ics_file() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }

        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');

        if (!isset($_FILES['ics_file']) || $_FILES['ics_file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Fehler beim Hochladen der ICS-Datei.';
            if (isset($_FILES['ics_file']['error'])) {
                switch ($_FILES['ics_file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message = 'Die Datei ist zu groß (PHP upload_max_filesize überschritten).';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'Die Datei ist zu groß (HTML MAX_FILE_SIZE überschritten).';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'Die Datei wurde nur teilweise hochgeladen.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'Keine Datei hochgeladen.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = 'Temporäres Verzeichnis fehlt.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = 'Fehler beim Schreiben der Datei auf Disk.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = 'PHP-Erweiterung hat den Upload gestoppt.';
                        break;
                }
            }
            wp_send_json_error($error_message);
            return;
        }

        // Prüfe Dateityp
        $file_info = pathinfo($_FILES['ics_file']['name']);
        if (strtolower($file_info['extension']) !== 'ics') {
            wp_send_json_error('Bitte wählen Sie eine .ics Datei aus.');
            return;
        }

        // Prüfe Dateigröße (max 5MB)
        if ($_FILES['ics_file']['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('Die Datei ist zu groß. Maximale Größe: 5MB');
            return;
        }

        // Cache-Verzeichnis erstellen falls nicht vorhanden
        $cache_dir = dirname($this->cache_file);
        if (!file_exists($cache_dir)) {
            $mkdir_result = wp_mkdir_p($cache_dir);
            if (!$mkdir_result) {
                wp_send_json_error('Fehler beim Erstellen des Cache-Verzeichnisses: ' . $cache_dir);
                return;
            }
        }

        // Prüfe Schreibrechte
        if (!is_writable($cache_dir)) {
            wp_send_json_error('Cache-Verzeichnis ist nicht beschreibbar: ' . $cache_dir);
            return;
        }

        // Datei in Cache-Verzeichnis verschieben
        if (move_uploaded_file($_FILES['ics_file']['tmp_name'], $this->cache_file)) {
            // Prüfe ob es sich um eine gültige ICS-Datei handelt
            $content = file_get_contents($this->cache_file);
            if (!$this->is_valid_ics_content($content)) {
                unlink($this->cache_file); // Lösche ungültige Datei
                wp_send_json_error('Die hochgeladene Datei ist keine gültige ICS-Datei.');
                return;
            }

            // Aktiviere manuellen Modus
            $this->use_manual_file = true;
            update_option('gw2_calendar_use_manual_file', true);

            $file_size = filesize($this->cache_file);
            wp_send_json_success('ICS-Datei erfolgreich hochgeladen (' . $file_size . ' Bytes). Manueller Modus wurde aktiviert.');
                 } else {
             wp_send_json_error('Fehler beim Speichern der Datei im Cache-Verzeichnis.');
         }
     }
     
     public function ajax_save_calendar_settings() {
         if (!current_user_can('manage_options')) {
             wp_die('Nicht autorisiert');
         }
 
         check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');
 
         $week_start = sanitize_text_field($_POST['week_start']);
         $custom_css = sanitize_textarea_field($_POST['custom_css']);
 
         // Validiere week_start
         if (!in_array($week_start, array('monday', 'sunday'))) {
             wp_send_json_error('Ungültiger Wert für Wochenstart.');
             return;
         }
 
         // Speichere Einstellungen
         update_option('gw2_calendar_week_start', $week_start);
         update_option('gw2_calendar_custom_css', $custom_css);
 
         // Aktualisiere lokale Variablen
         $this->week_start = $week_start;
         $this->custom_css = $custom_css;
 
                 wp_send_json_success('Einstellungen erfolgreich gespeichert.');
    }
    
    public function ajax_get_event_ids() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }
        
        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');
        
        $events = $this->get_calendar_events();
        $event_ids = array();
        
        // Aktueller Monat und Jahr
        $now = new DateTime();
        $current_month_start = new DateTime($now->format('Y-m-01'));
        $current_month_end = new DateTime($now->format('Y-m-t 23:59:59'));
        
        foreach ($events as $event) {
            if (isset($event['id']) && !empty($event['id'])) {
                $event_date = new DateTime($event['start']);
                
                // Filtere nur Events vom aktuellen Monat und zukünftige Events
                if ($event_date >= $current_month_start) {
                    $event_ids[] = array(
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'date' => $event_date->format('d.m.Y H:i'),
                        'is_future' => $event_date > $now,
                        'days_until' => $now->diff($event_date)->days
                    );
                }
            }
        }
        
        // Sortiere nach Datum
        usort($event_ids, function($a, $b) {
            // Use DateTime objects instead of strtotime for more reliable sorting
            $date_a = new DateTime($a['date'], new DateTimeZone('UTC'));
            $date_b = new DateTime($b['date'], new DateTimeZone('UTC'));
            return $date_a <=> $date_b;
        });
        
        wp_send_json_success($event_ids);
    }
    
    public function ajax_test_download_url() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht autorisiert');
        }
        
        check_ajax_referer('gw2_calendar_admin_nonce', 'nonce');
        
        $test_urls = array(
            'https://de-forum.guildwars2.com/events/download/',
            'https://de-forum.guildwars2.com/events/download/calendarEvents.ics'
        );
        
        $results = array();
        
        foreach ($test_urls as $url) {
            error_log('GW2 Calendar: Teste Download-URL: ' . $url);
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'user-agent' => 'GW2 Community Calendar Plugin Test',
                'sslverify' => false,
                'headers' => array(
                    'Accept' => 'text/calendar, application/ics, */*',
                    'Cache-Control' => 'no-cache'
                )
            ));
            
            if (is_wp_error($response)) {
                $results[$url] = array(
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'code' => 'wp_error'
                );
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $headers = wp_remote_retrieve_headers($response);
                
                $results[$url] = array(
                    'success' => ($status_code === 200),
                    'status_code' => $status_code,
                    'content_length' => strlen($body),
                    'content_type' => $headers->get('content-type'),
                    'is_valid_ics' => $this->is_valid_ics_content($body),
                    'body_preview' => substr($body, 0, 200) . '...'
                );
            }
        }
        
        wp_send_json_success($results);
    }
     
     private function download_calendar_data() {
        // Wenn manueller Modus aktiv ist, prüfe nur ob die Datei existiert
        if ($this->use_manual_file) {
            error_log('GW2 Calendar: Manueller Modus aktiv - verwende lokale Datei');
            
            if (!file_exists($this->cache_file)) {
                error_log('GW2 Calendar: Manuelle Datei nicht gefunden: ' . $this->cache_file);
                return array(
                    'success' => false,
                    'message' => 'Manuelle Datei nicht gefunden. Bitte laden Sie die calendarEvents.ics über das Admin-Interface hoch oder in ' . dirname($this->cache_file) . ' hoch.'
                );
            }
            
            $file_size = filesize($this->cache_file);
            error_log('GW2 Calendar: Verwende manuelle Datei: ' . $this->cache_file . ' (' . $file_size . ' Bytes)');
            return array(
                'success' => true,
                'message' => 'Verwende manuelle Datei (' . $file_size . ' Bytes)'
            );
        }
        
        // Debug-Logging
        error_log('GW2 Calendar: Starte Download von ' . $this->ics_url);
        
        // Versuche zuerst die direkte URL
        $response = $this->try_download_url($this->ics_url);
        
        // Falls fehlgeschlagen, versuche alternative URLs
        if (is_wp_error($response)) {
            error_log('GW2 Calendar: Direkte URL fehlgeschlagen, versuche Alternativen');
            
            $alternative_urls = array(
                'https://de-forum.guildwars2.com/events/download/',
                'https://de-forum.guildwars2.com/events/download/calendarEvents.ics'
            );
            
            foreach ($alternative_urls as $alt_url) {
                if ($alt_url !== $this->ics_url) {
                    error_log('GW2 Calendar: Versuche alternative URL: ' . $alt_url);
                    $response = $this->try_download_url($alt_url);
                    if (!is_wp_error($response)) {
                        break;
                    }
                }
            }
        }
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('GW2 Calendar: Download-Fehler - ' . $error_message);
            return array(
                'success' => false,
                'message' => 'Download-Fehler: ' . $error_message
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        
        error_log('GW2 Calendar: HTTP Status: ' . $status_code);
        error_log('GW2 Calendar: Content-Length: ' . strlen($body));
        
        // Spezielle Behandlung für 403-Fehler
        if ($status_code === 403) {
            error_log('GW2 Calendar: HTTP 403 - Zugriff verweigert. Wechsle zu manuellem Modus.');
            $this->use_manual_file = true;
            update_option('gw2_calendar_use_manual_file', true);
            
            if (file_exists($this->cache_file)) {
                $file_size = filesize($this->cache_file);
                return array(
                    'success' => true,
                    'message' => 'Download blockiert (403). Verwende manuelle Datei (' . $file_size . ' Bytes)'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Download blockiert (403). Bitte laden Sie die calendarEvents.ics über das Admin-Interface hoch oder in ' . dirname($this->cache_file) . ' hoch.'
                );
            }
        }
        
        if ($status_code !== 200) {
            $error_message = 'HTTP Status: ' . $status_code;
            error_log('GW2 Calendar: ' . $error_message);
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        if (empty($body)) {
            error_log('GW2 Calendar: Leere Antwort vom Server');
            return array(
                'success' => false,
                'message' => 'Leere Antwort vom Server'
            );
        }
        
        // Prüfen ob es sich um eine gültige ICS-Datei handelt
        if (!$this->is_valid_ics_content($body)) {
            error_log('GW2 Calendar: Ungültiger ICS-Inhalt erhalten');
            return array(
                'success' => false,
                'message' => 'Ungültiger ICS-Inhalt erhalten'
            );
        }
        
        // Cache-Verzeichnis erstellen falls nicht vorhanden
        $cache_dir = dirname($this->cache_file);
        if (!file_exists($cache_dir)) {
            $mkdir_result = wp_mkdir_p($cache_dir);
            if (!$mkdir_result) {
                error_log('GW2 Calendar: Fehler beim Erstellen des Cache-Verzeichnisses: ' . $cache_dir);
                return array(
                    'success' => false,
                    'message' => 'Fehler beim Erstellen des Cache-Verzeichnisses'
                );
            }
        }
        
        // Schreibrechte prüfen
        if (!is_writable($cache_dir)) {
            error_log('GW2 Calendar: Cache-Verzeichnis nicht beschreibbar: ' . $cache_dir);
            return array(
                'success' => false,
                'message' => 'Cache-Verzeichnis nicht beschreibbar'
            );
        }
        
        // Datei speichern
        $result = file_put_contents($this->cache_file, $body);
        
        if ($result === false) {
            error_log('GW2 Calendar: Fehler beim Speichern der Datei: ' . $this->cache_file);
            return array(
                'success' => false,
                'message' => 'Fehler beim Speichern der Datei'
            );
        }
        
        error_log('GW2 Calendar: Datei erfolgreich gespeichert: ' . $this->cache_file . ' (' . $result . ' Bytes)');
        return array(
            'success' => true,
            'message' => 'Kalender erfolgreich heruntergeladen (' . $result . ' Bytes)'
        );
    }
    
    private function get_calendar_events() {
        // Prüfen ob Cache-Datei existiert
        if (!file_exists($this->cache_file)) {
            // Versuche neuen Download (nur wenn nicht manueller Modus)
            if (!$this->use_manual_file) {
                $download_result = $this->download_calendar_data();
                if (!$download_result['success']) {
                    return array();
                }
            } else {
                // Im manuellen Modus: Wenn Datei nicht existiert, gib leeres Array zurück
                error_log('GW2 Calendar: Manuelle Datei nicht gefunden: ' . $this->cache_file);
                return array();
            }
        } else {
            // Prüfe Cache-Dauer nur im automatischen Modus
            if (!$this->use_manual_file && (time() - filemtime($this->cache_file)) > $this->cache_duration) {
                $download_result = $this->download_calendar_data();
                if (!$download_result['success']) {
                    // Verwende alte Datei falls Download fehlschlägt
                    error_log('GW2 Calendar: Download fehlgeschlagen, verwende alte Datei');
                }
            }
        }
        
        $ics_content = file_get_contents($this->cache_file);
        if (!$ics_content) {
            return array();
        }
        
        return $this->parse_ics_file($ics_content);
    }
    
    private function parse_ics_file($content) {
        $events = array();
        $lines = explode("\n", $content);
        $current_event = null;
        $current_key = null;
        $current_value = '';
        
        // Debug: Count total events found
        $total_events = 0;
        $events_with_titles = 0;
        
        foreach ($lines as $line) {
            if (trim($line) === 'BEGIN:VEVENT') {
                $current_event = array();
                $current_key = null;
                $current_value = '';
            } elseif (trim($line) === 'END:VEVENT') {
                // Process the last field if we have one
                if ($current_event !== null && $current_key !== null) {
                    $this->process_ics_field($current_event, $current_key, $current_value);
                }
                
                if ($current_event) {
                    $total_events++;
                    if (isset($current_event['title'])) {
                        $events_with_titles++;
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('GW2 Calendar parse_ics_file - Event without title: ' . json_encode($current_event));
                        }
                    }
                    $events[] = $current_event;
                }
                $current_event = null;
                $current_key = null;
                $current_value = '';
            } elseif ($current_event !== null) {
                // Skip empty lines
                if (empty(trim($line))) {
                    continue;
                }
                
                // Check if this is a continuation line (starts with space or tab)
                if (strlen($line) > 0 && ($line[0] === ' ' || $line[0] === "\t") && $current_key !== null) {
                    // This is a continuation of the previous field
                    $current_value .= ' ' . trim($line);
                } elseif (strpos($line, ':') !== false) {
                    // Process the previous field if we have one
                    if ($current_key !== null) {
                        $this->process_ics_field($current_event, $current_key, $current_value);
                    }
                    
                    // Handle ICS format with parameters (e.g., DTSTART;VALUE=DATE:20251027)
                    $last_colon_pos = strrpos($line, ':');
                    if ($last_colon_pos !== false) {
                        $key_part = substr($line, 0, $last_colon_pos);
                        $value = substr($line, $last_colon_pos + 1);
                        
                        // Extract the base key (before any semicolons)
                        $key = strpos($key_part, ';') !== false ? substr($key_part, 0, strpos($key_part, ';')) : $key_part;
                        $current_key = trim($key);
                        $current_value = trim($value);
                        
                        // Debug logging for DTSTART lines
                        if ($current_key === 'DTSTART' && defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('GW2 Calendar parse_ics_file - DTSTART line: ' . $line);
                            error_log('GW2 Calendar parse_ics_file - Parsed key: ' . $current_key . ', value: ' . $current_value);
                        }
                    }
                }
            }
        }
        
        // Debug: Log parsing statistics
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GW2 Calendar parse_ics_file - Parsing complete: ' . $total_events . ' total events, ' . $events_with_titles . ' with titles');
        }
        
        return $events;
    }
    
    private function process_ics_field(&$event, $key, $value) {
        // Wert dekodieren
        $value = $this->decode_ics_value($value);
        
        // Debug logging for SUMMARY fields
        if ($key === 'SUMMARY' && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GW2 Calendar process_ics_field - Processing SUMMARY:"' . $value . '"');
        }
        
        switch ($key) {
            case 'SUMMARY':
                $event['title'] = $value;
                break;
            case 'DESCRIPTION':
                $event['description'] = $value;
                break;
            case 'DTSTART':
                $event['start'] = $this->parse_ics_date($value);
                break;
            case 'DTEND':
                $event['end'] = $this->parse_ics_date($value);
                break;
            case 'LOCATION':
                $event['location'] = $value;
                break;
            case 'UID':
                $event['id'] = $value;
                break;
        }
    }
    
    private function decode_ics_value($value) {
        // Entferne Zeilenumbrüche und dekodiere
        $value = str_replace(array('\n', '\N'), "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        return $value;
    }
    
    private function parse_ics_date($date_string) {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GW2 Calendar parse_ics_date - Input: ' . $date_string);
        }
        
        // Prüfe ob Zeitzone vorhanden ist
        $has_timezone = preg_match('/[A-Z]{3}$/', $date_string);
        $timezone = null;
        
        if ($has_timezone) {
            $timezone = substr($date_string, -3);
            $date_string = substr($date_string, 0, -3);
        }
        
        if (strlen($date_string) === 8) {
            // Nur Datum (YYYYMMDD)
            $year = substr($date_string, 0, 4);
            $month = substr($date_string, 4, 2);
            $day = substr($date_string, 6, 2);
            $iso_date = $year . '-' . $month . '-' . $day . 'T00:00:00';
        } elseif (strlen($date_string) === 15) {
            // Datum und Zeit (YYYYMMDDTHHMMSS)
            $year = substr($date_string, 0, 4);
            $month = substr($date_string, 4, 2);
            $day = substr($date_string, 6, 2);
            $hour = substr($date_string, 9, 2);
            $minute = substr($date_string, 11, 2);
            $second = substr($date_string, 13, 2);
            $iso_date = $year . '-' . $month . '-' . $day . 'T' . $hour . ':' . $minute . ':' . $second;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GW2 Calendar parse_ics_date - Unknown format, returning as-is: ' . $date_string);
            }
            return $date_string;
        }
        
        // Wenn Zeitzone vorhanden, füge sie hinzu
        if ($timezone) {
            // Konvertiere 3-Buchstaben-Zeitzone zu vollständiger Zeitzone
            $timezone_map = array(
                'CET' => 'Europe/Berlin',
                'CEST' => 'Europe/Berlin',
                'UTC' => 'UTC',
                'GMT' => 'GMT'
            );
            
            $full_timezone = isset($timezone_map[$timezone]) ? $timezone_map[$timezone] : 'UTC';
            $iso_date .= ' ' . $full_timezone;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GW2 Calendar parse_ics_date - Output: ' . $iso_date);
        }
        
        return $iso_date;
    }
    
    private function try_download_url($url) {
        error_log('GW2 Calendar: Versuche Download von: ' . $url);
        
        return wp_remote_get($url, array(
            'timeout' => 60, // Längere Timeout für dynamische Generierung
            'user-agent' => 'GW2 Community Calendar Plugin',
            'sslverify' => false, // Für lokale Entwicklung
            'headers' => array(
                'Accept' => 'text/calendar, application/ics, */*',
                'Cache-Control' => 'no-cache',
                'User-Agent' => 'Mozilla/5.0 (compatible; GW2 Calendar Plugin)'
            )
        ));
    }
    
    private function is_valid_ics_content($content) {
        // Prüfen ob der Inhalt ICS-Format hat
        $content = trim($content);
        
        // Mindestlänge prüfen
        if (strlen($content) < 50) {
            return false;
        }
        
        // Prüfen ob VCALENDAR enthalten ist
        if (strpos($content, 'BEGIN:VCALENDAR') === false) {
            return false;
        }
        
        // Prüfen ob mindestens ein VEVENT enthalten ist
        if (strpos($content, 'BEGIN:VEVENT') === false) {
            return false;
        }
        
        return true;
    }
}

// Plugin initialisieren
new GW2CommunityCalendar(); 