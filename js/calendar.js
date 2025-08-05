jQuery(document).ready(function($) {

    var maxWaitTime = 10000; // 10 Sekunden maximale Wartezeit
    var startTime = Date.now();

    function initializeCalendar() {
        // Find all calendar elements
        var calendarElements = document.querySelectorAll('.gw2-calendar');
        if (calendarElements.length === 0) return;

        // Prüfen, ob maximale Wartezeit überschritten wurde
        if (Date.now() - startTime > maxWaitTime) {
            console.error('FullCalendar konnte nicht geladen werden. Bitte laden Sie die Seite neu.');
            return;
        }

        // Warten, bis FullCalendar geladen ist
        if (typeof FullCalendar === 'undefined') {
            console.log('FullCalendar noch nicht geladen, warte...');
            setTimeout(initializeCalendar, 100);
            return;
        }

        // Zusätzliche Prüfung für FullCalendar.Calendar
        if (typeof FullCalendar.Calendar === 'undefined') {
            console.log('FullCalendar.Calendar noch nicht verfügbar, warte...');
            setTimeout(initializeCalendar, 100);
            return;
        }

        // Prüfen ob FullCalendar korrekt geladen wurde
        try {
            // Test-Objekt erstellen um zu prüfen ob FullCalendar funktioniert
            var testCalendar = new FullCalendar.Calendar(document.createElement('div'), {
                initialView: 'dayGridMonth'
            });
        } catch (error) {
            console.error('FullCalendar Fehler:', error);
            console.log('Versuche FullCalendar erneut zu laden...');
            setTimeout(initializeCalendar, 500);
            return;
        }

        // WordPress Locale zu FullCalendar Locale konvertieren
        var wpLocale = gw2_ajax.locale || 'de_DE';
        var fcLocale = wpLocale.split('_')[0]; // 'de_DE' -> 'de'
        
        // Lokalisierte Button-Texte basierend auf WordPress Locale
        var buttonTexts = {
            'de': {
                today: 'Heute',
                month: 'Monat',
                week: 'Woche',
                day: 'Tag',
                list: 'Liste'
            },
            'en': {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day',
                list: 'List'
            },
            'fr': {
                today: 'Aujourd\'hui',
                month: 'Mois',
                week: 'Semaine',
                day: 'Jour',
                list: 'Liste'
            },
            'es': {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Lista'
            },
            'it': {
                today: 'Oggi',
                month: 'Mese',
                week: 'Settimana',
                day: 'Giorno',
                list: 'Lista'
            },
            'nl': {
                today: 'Vandaag',
                month: 'Maand',
                week: 'Week',
                day: 'Dag',
                list: 'Lijst'
            },
            'pl': {
                today: 'Dzisiaj',
                month: 'Miesiąc',
                week: 'Tydzień',
                day: 'Dzień',
                list: 'Lista'
            },
            'pt': {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia',
                list: 'Lista'
            },
            'ru': {
                today: 'Сегодня',
                month: 'Месяц',
                week: 'Неделя',
                day: 'День',
                list: 'Список'
            },
            'sv': {
                today: 'Idag',
                month: 'Månad',
                week: 'Vecka',
                day: 'Dag',
                list: 'Lista'
            },
            'tr': {
                today: 'Bugün',
                month: 'Ay',
                week: 'Hafta',
                day: 'Gün',
                list: 'Liste'
            },
            'zh': {
                today: '今天',
                month: '月',
                week: '周',
                day: '日',
                list: '列表'
            },
            'ja': {
                today: '今日',
                month: '月',
                week: '週',
                day: '日',
                list: 'リスト'
            },
            'ko': {
                today: '오늘',
                month: '월',
                week: '주',
                day: '일',
                list: '목록'
            }
        };
        
        // Button-Texte für aktuelle Locale auswählen (Standard: Deutsch)
        var currentButtonTexts = buttonTexts[fcLocale] || buttonTexts['de'];
        
        // FullCalendar v6 unterstützt eingebaute Lokalisierung
        // Wir verwenden die Locale direkt ohne separate Skripte zu laden
        console.log('FullCalendar Lokalisierung verwenden für:', fcLocale);
        
        // Initialize all calendar instances
        var calendars = [];
        calendarElements.forEach(function(calendarEl, index) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: fcLocale, // FullCalendar v6 hat eingebaute Lokalisierung
                firstDay: gw2_ajax.week_start === 'monday' ? 1 : 0, // 1 = Montag, 0 = Sonntag
                headerToolbar: {
                    left: 'prev,next,today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: currentButtonTexts,
                events: function(info, successCallback, failureCallback) {
                    $.ajax({
                        url: gw2_ajax.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'get_calendar_events',
                            nonce: gw2_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data);
                            } else {
                                console.error('AJAX Fehler:', response);
                                failureCallback('Fehler beim Laden der Kalenderdaten: ' + (response.data || 'Unbekannter Fehler'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Netzwerkfehler:', status, error);
                            console.error('HTTP Status:', xhr.status);
                            console.error('Response:', xhr.responseText);
                            failureCallback('Netzwerkfehler beim Laden der Kalenderdaten. Status: ' + xhr.status);
                        }
                    });
                },
                eventClick: function(info) {
                    showEventDetails(info.event);
                },
                eventDidMount: function(info) {
                    $(info.el).tooltip({
                        title: info.event.title,
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body'
                    });
                },
                loading: function(isLoading) {
                    if (isLoading) {
                        $(calendarEl).addClass('loading');
                    } else {
                        $(calendarEl).removeClass('loading');
                    }
                }
            });

            calendar.render();
            calendars.push(calendar);
        });
        
        // Store calendars in window object for access
        window.gw2Calendars = calendars;
        if (calendars.length > 0) {
            window.gw2Calendar = calendars[0]; // Keep backward compatibility
        }

        // Event Details Modal
        function showEventDetails(event) {
            var modal = $('<div class="gw2-event-modal">' +
                '<div class="gw2-event-modal-content">' +
                '<span class="gw2-event-modal-close">&times;</span>' +
                '<h3>' + event.title + '</h3>' +
                '<div class="gw2-event-details">' +
                '<p><strong>Start:</strong> ' + formatDateTime(event.start) + '</p>' +
                (event.end ? '<p><strong>Ende:</strong> ' + formatDateTime(event.end) + '</p>' : '') +
                (event.extendedProps.location ? '<p><strong>Ort:</strong> ' + event.extendedProps.location + '</p>' : '') +
                (event.extendedProps.description ? '<p><strong>Beschreibung:</strong> ' + event.extendedProps.description + '</p>' : '') +
                '</div>' +
                '</div>' +
                '</div>');
            $('body').append(modal);
            modal.find('.gw2-event-modal-close').click(function() {
                modal.remove();
            });
            modal.click(function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        }

        function formatDateTime(date) {
            if (!date) return '';
            var d = new Date(date);
            var options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            // WordPress Locale für Datumsformatierung verwenden
            var wpLocale = gw2_ajax.locale || 'de_DE';
            return d.toLocaleDateString(wpLocale, options);
        }

        // Admin Download Button
        $('#gw2-download-calendar').click(function() {
            var button = $(this);
            var status = $('#gw2-download-status');
            button.prop('disabled', true).text('Lädt herunter...');
            status.text('').removeClass('success error');
            $.ajax({
                url: gw2_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'download_calendar',
                    nonce: gw2_ajax.admin_nonce
                },
                success: function(response) {
                    if (response.success) {
                        status.text(response.data).addClass('success');
                        if (window.gw2Calendars) {
                            window.gw2Calendars.forEach(function(calendar) {
                                calendar.refetchEvents();
                            });
                        }
                    } else {
                        status.text(response.data).addClass('error');
                    }
                },
                error: function() {
                    status.text('Netzwerkfehler').addClass('error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Kalender manuell herunterladen');
                }
            });
        });

        // Auto-Refresh alle 5 Minuten
        setInterval(function() {
            if (window.gw2Calendars) {
                window.gw2Calendars.forEach(function(calendar) {
                    calendar.refetchEvents();
                });
            }
        }, 300000);
    }

    // Debugging-Informationen
    console.log('GW2 Calendar: Initialisierung gestartet');
    console.log('GW2 Calendar: AJAX URL:', gw2_ajax.ajax_url);
    console.log('GW2 Calendar: Nonce verfügbar:', !!gw2_ajax.nonce);
    console.log('GW2 Calendar: Admin Nonce verfügbar:', !!gw2_ajax.admin_nonce);

    // Initialisierung starten
    initializeCalendar();

});