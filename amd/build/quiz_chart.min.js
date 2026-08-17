define(['jquery', 'core/ajax'], function($, ajax) {
    "use strict";

    return {
        init: function(courseDetailsJson, selectedCourseId) {
            console.log('[LA] Quiz Chart geladen');

            const select = document.getElementById('la-quiz-select');
            if (!select) {
                console.warn('[LA] Quiz-Select nicht gefunden');
                return;
            }

            select.addEventListener('change', function() {
                const quizId = select.value;
                if (!quizId) {
                    // Zurücksetzen
                    const chartContainer = document.getElementById('la-quiz-chart');
                    const emptyHint = document.getElementById('la-quiz-empty');
                    if (chartContainer) chartContainer.style.display = 'none';
                    if (emptyHint) emptyHint.style.display = 'block';
                    return;
                }

                // AJAX-Aufruf
                ajax.call([{
                    methodname: 'block_learninganalytics_get_quiz_data',
                    args: { quizid: parseInt(quizId) },
                    done: function(response) {
                        // Validierung der Daten
                        if (!response || response.user_score === null || response.user_score === undefined) {
                            // Nachricht im Diagramm-Container
                            const chartContainer = document.getElementById('la-quiz-chart');
                            if (chartContainer) {
                                chartContainer.innerHTML = '<p>Bisher keine Ergebnisse für dieses Quiz</p>';
                                chartContainer.style.display = 'block';
                            }
                            const emptyHint = document.getElementById('la-quiz-empty');
                            if (emptyHint) emptyHint.style.display = 'none';
                            return;
                        }

                        // Chart zeichnen
                        drawChart(response.user_score, response.average_score, response.max_score);
                    },
                    fail: function(error) {
                        console.error('[LA] AJAX-Fehler:', error);
                    }
                }]);
            });

            function drawChart(userScore, avgScore, maxScore) {
                const canvas = document.getElementById('la-quiz-chart-canvas');
                const chartContainer = document.getElementById('la-quiz-chart');
                const emptyHint = document.getElementById('la-quiz-empty');

                if (!chartContainer) return;

                // Vorheriges Chart entfernen
                chartContainer.innerHTML = '';

                // Einfaches HTML/CSS Chart
                var userHeight = (userScore / maxScore) * 150;
                var avgHeight = (avgScore / maxScore) * 150;

                var chartHtml = '<div style="display: flex; align-items: end; height: 150px; margin: 20px 0;">' +
                    '<div style="flex: 1; text-align: center;">' +
                        '<div style="height: ' + userHeight + 'px; background-color: rgba(75, 192, 192, 0.2); border: 1px solid rgba(75, 192, 192, 1); margin-bottom: 5px; min-height: 20px;"></div>' +
                        '<div>Dein Score: ' + userScore + '</div>' +
                    '</div>' +
                    '<div style="width: 20px;"></div>' +
                    '<div style="flex: 1; text-align: center;">' +
                        '<div style="height: ' + avgHeight + 'px; background-color: rgba(255, 99, 132, 0.2); border: 1px solid rgba(255, 99, 132, 1); margin-bottom: 5px; min-height: 20px;"></div>' +
                        '<div>Durchschnitt: ' + avgScore.toFixed(1) + '</div>' +
                    '</div>' +
                '</div>';

                chartContainer.innerHTML = chartHtml;

                // Anzeigen
                if (chartContainer) chartContainer.style.display = 'block';
                if (emptyHint) emptyHint.style.display = 'none';
            }
        }
    };
});
