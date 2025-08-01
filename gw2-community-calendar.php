<?php
/**
 * Plugin Name: GW2 Community Calendar
 * Description: Dynamischer Kalender für Guild Wars 2 Community Events
 * Version: 1.1.0
 * Author: TerisC
 * License: GPL v2 or later
 * Plugin URI: https://github.com/teris/gw2-community-calendar
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class GW2CommunityCalendar {
    
    private $ics_url = 'webcal://de-forum.guildwars2.com/events/download/';
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
        
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Plugin-Aktionslinks hinzufügen
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        
        // Shortcode registrieren
        add_shortcode('gw2_calendar', array($this, 'calendar_shortcode'));
        
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
                <div class="gw2-admin-section">
                    <h2>Kalender-Verwaltung</h2>
                    <p>Verwalten Sie die GW2 Community Kalender-Events und Download-Einstellungen.</p>
                    
                    <div class="gw2-admin-controls">
                        <button id="gw2-download-calendar" class="button button-primary">Kalender herunterladen</button>
                        <button id="gw2-toggle-mode" class="button <?php echo $this->use_manual_file ? 'button-secondary' : 'button-primary'; ?>">
                            <?php echo $this->use_manual_file ? 'Manueller Modus' : 'Automatischer Modus'; ?>
                        </button>
                        <button id="gw2-check-status" class="button button-secondary">Status prüfen</button>
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
                    <h2>Shortcode Verwendung</h2>
                    <p>Fügen Sie den folgenden Shortcode in Ihre Seiten oder Beiträge ein:</p>
                    <code>[gw2_calendar]</code>
                    
                    <h3>Optionale Parameter:</h3>
                    <ul>
                        <li><code>width</code> - Breite des Kalenders (Standard: 100%)</li>
                        <li><code>height</code> - Höhe des Kalenders (Standard: 600px)</li>
                    </ul>
                    
                    <p><strong>Beispiel:</strong> <code>[gw2_calendar width="800px" height="500px"]</code></p>
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
        
        return '<div class="gw2-calendar-container" style="width: ' . esc_attr($atts['width']) . '; height: ' . esc_attr($atts['height']) . ';">
            <div id="gw2-calendar"></div>
        </div>';
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
                'webcal://de-forum.guildwars2.com/events/download/',
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
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'BEGIN:VEVENT') {
                $current_event = array();
            } elseif ($line === 'END:VEVENT') {
                if ($current_event) {
                    $events[] = $current_event;
                }
                $current_event = null;
            } elseif ($current_event !== null && strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Wert dekodieren
                $value = $this->decode_ics_value($value);
                
                switch ($key) {
                    case 'SUMMARY':
                        $current_event['title'] = $value;
                        break;
                    case 'DESCRIPTION':
                        $current_event['description'] = $value;
                        break;
                    case 'DTSTART':
                        $current_event['start'] = $this->parse_ics_date($value);
                        break;
                    case 'DTEND':
                        $current_event['end'] = $this->parse_ics_date($value);
                        break;
                    case 'LOCATION':
                        $current_event['location'] = $value;
                        break;
                    case 'UID':
                        $current_event['id'] = $value;
                        break;
                }
            }
        }
        
        return $events;
    }
    
    private function decode_ics_value($value) {
        // Entferne Zeilenumbrüche und dekodiere
        $value = str_replace(array('\n', '\N'), "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        return $value;
    }
    
    private function parse_ics_date($date_string) {
        // Entferne Zeitzone und konvertiere zu ISO-Format
        $date_string = preg_replace('/[A-Z]{3}$/', '', $date_string);
        
        if (strlen($date_string) === 8) {
            // Nur Datum (YYYYMMDD)
            $year = substr($date_string, 0, 4);
            $month = substr($date_string, 4, 2);
            $day = substr($date_string, 6, 2);
            return $year . '-' . $month . '-' . $day . 'T00:00:00';
        } elseif (strlen($date_string) === 15) {
            // Datum und Zeit (YYYYMMDDTHHMMSS)
            $year = substr($date_string, 0, 4);
            $month = substr($date_string, 4, 2);
            $day = substr($date_string, 6, 2);
            $hour = substr($date_string, 9, 2);
            $minute = substr($date_string, 11, 2);
            $second = substr($date_string, 13, 2);
            return $year . '-' . $month . '-' . $day . 'T' . $hour . ':' . $minute . ':' . $second;
        }
        
        return $date_string;
    }
    
    private function try_download_url($url) {
        // webcal:// zu https:// umwandeln
        $url = str_replace('webcal://', 'https://', $url);
        
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