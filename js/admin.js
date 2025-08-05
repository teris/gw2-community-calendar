jQuery(document).ready(function($) {
    
    // Event-ID-Liste laden
    function loadEventIds() {
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_event_ids',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayEventIds(response.data);
                } else {
                    $('#gw2-event-ids-list').html('<p class="error">Fehler beim Laden der Event-IDs.</p>');
                }
            },
            error: function() {
                $('#gw2-event-ids-list').html('<p class="error">Fehler beim Laden der Event-IDs.</p>');
            }
        });
    }
    
    // Event-IDs anzeigen
    function displayEventIds(events) {
        if (events.length === 0) {
            $('#gw2-event-ids-list').html('<p>Keine Events mit IDs gefunden.</p>');
            return;
        }
        
        var html = '<div class="gw2-event-ids-container">';
        html += '<table class="widefat">';
        html += '<thead><tr>';
        html += '<th>Event-ID</th>';
        html += '<th>Event-Titel</th>';
        html += '<th>Datum & Zeit</th>';
        html += '<th>Status</th>';
        html += '<th>Shortcode</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        events.forEach(function(event) {
            var statusClass = event.is_future ? 'future-event' : 'past-event';
            var statusText = event.is_future ? 'Zukünftig' : 'Vergangen';
            var shortcode = '[gw2_event_countdown event_id="' + event.id + '"]';
            
            html += '<tr class="' + statusClass + '">';
            html += '<td><code>' + event.id + '</code></td>';
            html += '<td>' + event.title + '</td>';
            html += '<td>' + event.date + ' Uhr</td>';
            html += '<td><span class="event-status">' + statusText + '</span></td>';
            html += '<td><code>' + shortcode + '</code></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';

        html += '</div>';
        
        $('#gw2-event-ids-list').html(html);
    }
    

    
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
                    displayCacheInfo(response.data);
                } else {
                    $('#gw2-cache-info').html('<p class="error">Fehler beim Laden der Cache-Informationen.</p>');
                }
            },
            error: function() {
                $('#gw2-cache-info').html('<p class="error">Fehler beim Laden der Cache-Informationen.</p>');
            }
        });
    }
    
    // Cache-Informationen anzeigen
    function displayCacheInfo(data) {
        var html = '<table class="widefat">';
        html += '<tr><td><strong>Cache-Datei existiert:</strong></td><td>' + (data.cache_file_exists ? 'Ja' : 'Nein') + '</td></tr>';
        
        if (data.cache_file_exists) {
            html += '<tr><td><strong>Dateigröße:</strong></td><td>' + formatBytes(data.cache_file_size) + '</td></tr>';
            html += '<tr><td><strong>Letzte Änderung:</strong></td><td>' + new Date(data.cache_file_time * 1000).toLocaleString('de-DE') + '</td></tr>';
        }
        
        html += '<tr><td><strong>Cache-Verzeichnis beschreibbar:</strong></td><td>' + (data.cache_dir_writable ? 'Ja' : 'Nein') + '</td></tr>';
        html += '<tr><td><strong>Modus:</strong></td><td>' + (data.use_manual_file ? 'Manuell' : 'Automatisch') + '</td></tr>';
        html += '<tr><td><strong>Download-URL:</strong></td><td><a href="' + data.download_url + '" target="_blank">' + data.download_url + '</a></td></tr>';
        html += '<tr><td><strong>Cache-Datei Pfad:</strong></td><td><code>' + data.cache_file_path + '</code></td></tr>';
        html += '</table>';
        
        $('#gw2-cache-info').html(html);
    }
    
    // Bytes formatieren
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Kalender herunterladen
    $('#gw2-download-calendar').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Lädt herunter...');
        $('#gw2-download-status').removeClass('success error').text('');
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'download_calendar',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#gw2-download-status').addClass('success').text(response.data);
                    loadCacheInfo();
                    loadEventIds();
                } else {
                    $('#gw2-download-status').addClass('error').text(response.data);
                }
            },
            error: function() {
                $('#gw2-download-status').addClass('error').text('Fehler beim Herunterladen.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Modus umschalten
    $('#gw2-toggle-mode').click(function() {
        var button = $(this);
        var originalText = button.text();
        
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
                    location.reload();
                } else {
                    alert('Fehler beim Umschalten des Modus.');
                }
            },
            error: function() {
                alert('Fehler beim Umschalten des Modus.');
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Status prüfen
    $('#gw2-check-status').click(function() {
        loadCacheInfo();
        loadEventIds();
    });
    
    // Download-URLs testen
    $('#gw2-test-urls').click(function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Teste URLs...');
        $('#gw2-download-status').removeClass('success error').text('');
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'test_download_url',
                nonce: gw2_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    var statusHtml = '<div class="gw2-test-results">';
                    statusHtml += '<h4>Download-URL Test Ergebnisse:</h4>';
                    
                    for (var url in results) {
                        var result = results[url];
                        statusHtml += '<div class="gw2-test-result">';
                        statusHtml += '<strong>URL:</strong> <a href="' + url + '" target="_blank">' + url + '</a><br>';
                        
                        if (result.success) {
                            statusHtml += '<span class="success">✓ Erfolgreich</span><br>';
                            statusHtml += '<strong>Status Code:</strong> ' + result.status_code + '<br>';
                            statusHtml += '<strong>Content Length:</strong> ' + result.content_length + ' Bytes<br>';
                            statusHtml += '<strong>Content Type:</strong> ' + (result.content_type || 'Nicht angegeben') + '<br>';
                            statusHtml += '<strong>Gültige ICS:</strong> ' + (result.is_valid_ics ? 'Ja' : 'Nein') + '<br>';
                            if (result.body_preview) {
                                statusHtml += '<strong>Vorschau:</strong> <code>' + result.body_preview + '</code><br>';
                            }
                        } else {
                            statusHtml += '<span class="error">✗ Fehlgeschlagen</span><br>';
                            if (result.error) {
                                statusHtml += '<strong>Fehler:</strong> ' + result.error + '<br>';
                            }
                            if (result.status_code) {
                                statusHtml += '<strong>Status Code:</strong> ' + result.status_code + '<br>';
                            }
                        }
                        statusHtml += '</div>';
                    }
                    statusHtml += '</div>';
                    
                    $('#gw2-download-status').addClass('success').html(statusHtml);
                } else {
                    $('#gw2-download-status').addClass('error').text('Fehler beim Testen der URLs.');
                }
            },
            error: function() {
                $('#gw2-download-status').addClass('error').text('Fehler beim Testen der URLs.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // ICS-Datei Upload
    $('#gw2-upload-form').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'upload_ics_file');
        formData.append('nonce', gw2_admin_ajax.nonce);
        
        var button = $('#gw2-upload-button');
        var originalText = button.find('.gw2-upload-text').text();
        
        button.prop('disabled', true);
        button.find('.gw2-upload-text').hide();
        button.find('.gw2-upload-loading').show();
        $('#gw2-upload-status').removeClass('success error').text('');
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#gw2-upload-status').addClass('success').text(response.data);
                    loadCacheInfo();
                    loadEventIds();
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#gw2-upload-status').addClass('error').text(response.data);
                }
            },
            error: function() {
                $('#gw2-upload-status').addClass('error').text('Fehler beim Hochladen.');
            },
            complete: function() {
                button.prop('disabled', false);
                button.find('.gw2-upload-text').show();
                button.find('.gw2-upload-loading').hide();
            }
        });
    });
    
    // Einstellungen speichern
    $('#gw2-settings-form').submit(function(e) {
        e.preventDefault();
        
        var button = $('#gw2-save-settings');
        var originalText = button.find('.gw2-save-text').text();
        
        button.prop('disabled', true);
        button.find('.gw2-save-text').hide();
        button.find('.gw2-save-loading').show();
        $('#gw2-settings-status').removeClass('success error').text('');
        
        $.ajax({
            url: gw2_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_calendar_settings',
                nonce: gw2_admin_ajax.nonce,
                week_start: $('#week_start').val(),
                custom_css: $('#custom_css').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#gw2-settings-status').addClass('success').text(response.data);
                } else {
                    $('#gw2-settings-status').addClass('error').text(response.data);
                }
            },
            error: function() {
                $('#gw2-settings-status').addClass('error').text('Fehler beim Speichern der Einstellungen.');
            },
            complete: function() {
                button.prop('disabled', false);
                button.find('.gw2-save-text').show();
                button.find('.gw2-save-loading').hide();
            }
        });
    });
    
    // Initial laden
    loadCacheInfo();
    loadEventIds();
}); 