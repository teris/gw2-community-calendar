jQuery(document).ready(function($) {
    
    // Admin Download Button Handler
    $('#gw2-download-calendar').click(function() {
        var button = $(this);
        var status = $('#gw2-download-status');
        
        // Button deaktivieren und Loading-Status anzeigen
        button.prop('disabled', true);
        button.html('<span class="gw2-loading"></span>Lädt herunter...');
        status.text('').removeClass('success error');
        
        // AJAX Request
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'download_calendar',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.text(response.data).addClass('success');
                    
                    // Erfolgsmeldung nach 3 Sekunden ausblenden
                    setTimeout(function() {
                        status.text('').removeClass('success');
                    }, 3000);
                    
                    // Optional: Seite neu laden um neue Events anzuzeigen
                    if (typeof calendar !== 'undefined') {
                        calendar.refetchEvents();
                    }
                } else {
                    status.text(response.data).addClass('error');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Netzwerkfehler';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                status.text(errorMessage).addClass('error');
            },
            complete: function() {
                // Button wieder aktivieren
                button.prop('disabled', false);
                button.text('Kalender manuell herunterladen');
            }
        });
    });
    
    // Toggle-Modus Button Handler
    $('#gw2-toggle-mode').click(function() {
        var button = $(this);
        var status = $('#gw2-download-status');
        
        button.prop('disabled', true);
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_download_mode',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.text(response.data).addClass('success');
                    
                    // Button-Text und Klasse aktualisieren
                    if (button.text().includes('Manueller')) {
                        button.text('Automatischer Modus').removeClass('button-secondary').addClass('button-primary');
                    } else {
                        button.text('Manueller Modus').removeClass('button-primary').addClass('button-secondary');
                    }
                    
                    // Erfolgsmeldung nach 3 Sekunden ausblenden
                    setTimeout(function() {
                        status.text('').removeClass('success');
                    }, 3000);
                } else {
                    status.text(response.data).addClass('error');
                }
            },
            error: function() {
                status.text('Fehler beim Umschalten des Modus').addClass('error');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Status-Button für Debugging
    $('#gw2-check-status').click(function() {
        var button = $(this);
        var status = $('#gw2-download-status');
        
        button.prop('disabled', true);
        button.text('Prüfe...');
        
        loadCacheInfo();
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_download_status',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var statusText = 'Modus: ' + (data.use_manual_file ? 'Manuell' : 'Automatisch');
                    statusText += ' | Cache-Datei: ' + (data.cache_file_exists ? 'Ja' : 'Nein');
                    if (data.cache_file_exists) {
                        statusText += ' (' + data.cache_file_size + ' Bytes, ' + new Date(data.cache_file_time * 1000).toLocaleString() + ')';
                    }
                    statusText += ' | Verzeichnis beschreibbar: ' + (data.cache_dir_writable ? 'Ja' : 'Nein');
                    
                    status.text(statusText).addClass('success').removeClass('error');
                } else {
                    status.text('Fehler beim Abrufen des Status').addClass('error');
                }
            },
            error: function() {
                status.text('Netzwerkfehler beim Status-Abruf').addClass('error');
            },
            complete: function() {
                button.prop('disabled', false);
                button.text('Status prüfen');
            }
        });
    });
    
    // Cache-Informationen laden
    function loadCacheInfo() {
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_download_status',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<table class="form-table">';
                    html += '<tr><th>Download-Modus:</th><td>' + (data.use_manual_file ? 'Manuell' : 'Automatisch') + '</td></tr>';
                    html += '<tr><th>Cache-Datei:</th><td>' + (data.cache_file_exists ? 'Vorhanden' : 'Nicht gefunden') + '</td></tr>';
                    if (data.cache_file_exists) {
                        html += '<tr><th>Dateigröße:</th><td>' + data.cache_file_size + ' Bytes</td></tr>';
                        html += '<tr><th>Letzte Änderung:</th><td>' + new Date(data.cache_file_time * 1000).toLocaleString() + '</td></tr>';
                    }
                    html += '<tr><th>Cache-Verzeichnis:</th><td>' + (data.cache_dir_writable ? 'Beschreibbar' : 'Nicht beschreibbar') + '</td></tr>';
                    html += '<tr><th>Dateipfad:</th><td><code>' + data.cache_file_path + '</code></td></tr>';
                    html += '<tr><th>ICS-URL:</th><td><a href="' + data.ics_url + '" target="_blank"><code>' + data.ics_url + '</code></a></td></tr>';
                    html += '</table>';
                    
                    $('#gw2-cache-info').html(html);
                } else {
                    $('#gw2-cache-info').html('<p class="error">Fehler beim Laden der Cache-Informationen</p>');
                }
            },
            error: function() {
                $('#gw2-cache-info').html('<p class="error">Netzwerkfehler beim Laden der Cache-Informationen</p>');
            }
        });
    }
    
    // Cache-Informationen beim Laden der Seite abrufen
    loadCacheInfo();
    
    // ICS-Datei Upload Handler
    $('#gw2-upload-form').submit(function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = $('#gw2-upload-button');
        var status = $('#gw2-upload-status');
        var fileInput = $('#ics_file')[0];
        
        // Prüfe ob Datei ausgewählt wurde
        if (!fileInput.files || fileInput.files.length === 0) {
            status.text('Bitte wählen Sie eine ICS-Datei aus.').addClass('error').removeClass('success');
            return;
        }
        
        // Prüfe Dateityp
        var fileName = fileInput.files[0].name;
        if (!fileName.toLowerCase().endsWith('.ics')) {
            status.text('Bitte wählen Sie eine .ics Datei aus.').addClass('error').removeClass('success');
            return;
        }
        
        // Prüfe Dateigröße (max 5MB)
        if (fileInput.files[0].size > 5 * 1024 * 1024) {
            status.text('Die Datei ist zu groß. Maximale Größe: 5MB').addClass('error').removeClass('success');
            return;
        }
        
        // Button deaktivieren und Loading-Status anzeigen
        button.prop('disabled', true);
        $('.gw2-upload-text').hide();
        $('.gw2-upload-loading').show();
        status.text('').removeClass('success error');
        
        // FormData für AJAX-Upload erstellen
        var formData = new FormData();
        formData.append('action', 'upload_ics_file');
        formData.append('nonce', gw2_admin_ajax.nonce);
        formData.append('ics_file', fileInput.files[0]);
        
        // AJAX Upload
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    status.text(response.data).addClass('success').removeClass('error');
                    
                    // Form zurücksetzen
                    form[0].reset();
                    
                    // Cache-Informationen aktualisieren
                    loadCacheInfo();
                    
                    // Erfolgsmeldung nach 5 Sekunden ausblenden
                    setTimeout(function() {
                        status.text('').removeClass('success');
                    }, 5000);
                } else {
                    status.text(response.data).addClass('error').removeClass('success');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Netzwerkfehler beim Upload';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                $('#gw2-upload-status').text(errorMessage).addClass('error').removeClass('success');
            },
            complete: function() {
                // Button wieder aktivieren
                button.prop('disabled', false);
                $('.gw2-upload-text').show();
                $('.gw2-upload-loading').hide();
            }
        });
    });
    
    // Einstellungen speichern
    $('#gw2-settings-form').submit(function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = $('#gw2-save-settings');
        var status = $('#gw2-settings-status');
        var saveText = button.find('.gw2-save-text');
        var saveLoading = button.find('.gw2-save-loading');
        
        // Button-Status ändern
        saveText.hide();
        saveLoading.show();
        button.prop('disabled', true);
        status.removeClass('success error').text('');
        
        // Formulardaten sammeln
        var formData = new FormData();
        formData.append('action', 'save_calendar_settings');
        formData.append('nonce', gw2_admin_ajax.nonce);
        formData.append('week_start', $('#week_start').val());
        formData.append('custom_css', $('#custom_css').val());
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    status.text(response.data).addClass('success').removeClass('error');
                } else {
                    status.text(response.data || 'Fehler beim Speichern der Einstellungen.').addClass('error').removeClass('success');
                }
            },
            error: function(xhr, status, error) {
                status.text('Netzwerkfehler beim Speichern der Einstellungen.').addClass('error').removeClass('success');
                console.error('Einstellungen speichern Fehler:', error);
            },
            complete: function() {
                // Button-Status zurücksetzen
                saveText.show();
                saveLoading.hide();
                button.prop('disabled', false);
            }
        });
    });
    
    // Keyboard Shortcuts für Admins
    $(document).keydown(function(e) {
        // Ctrl+Shift+R für manuellen Download
        if (e.ctrlKey && e.shiftKey && e.keyCode === 82) {
            e.preventDefault();
            $('#gw2-download-calendar').click();
        }
    });
}); 