define([], function() {
    "use strict";

    /**
     * Handles course switching only: binds to course card clicks and updates
     * progress bar, heading, todo filter, peer comparison and quiz dropdown.
     * Does not handle quiz AJAX or chart rendering (see quiz_chart module).
     *
     * @param {Array} courseDetails Parsed array from la-course-details JSON.
     */
    return {
        init: function(courseDetails) {
            const cards = document.querySelectorAll('.la-course-card');
            const progressBar = document.getElementById('la-selected-progress-bar');
            const progressLabel = document.getElementById('la-selected-progress-label');
            const heading = document.getElementById('la-selected-course-heading');

            if (!cards.length || !progressBar || !progressLabel) {
                console.warn('[LA] Course selector: required elements not found');
                return;
            }

            const details = Array.isArray(courseDetails) ? courseDetails : [];

            function getDetails(courseId) {
                return details.find(function(d) {
                    return String(d.id) === String(courseId);
                });
            }

            function filterTodos(courseId) {
                document.querySelectorAll('.la-todo-item-wrapper').forEach(function(wrapper) {
                    const item = wrapper.querySelector('.la-todo-item');
                    if (!item) {
                        return;
                    }
                    const itemCourseId = item.dataset.courseid;
                    wrapper.style.display = (String(itemCourseId) === String(courseId)) ? '' : 'none';
                });
            }

            function updatePeerComparison(courseId) {
                const peerBar = document.getElementById('la-peer-progress-bar');
                const peerLabel = document.getElementById('la-peer-progress-label');
                if (!peerBar || !peerLabel) {
                    return;
                }
                const d = getDetails(courseId);
                if (!d) {
                    peerBar.style.width = '0%';
                    peerLabel.textContent = 'Keine Peer-Daten für diesen Kurs';
                    return;
                }
                const pct = Number(d.peer_percentile ?? 0);
                const mean = Number(d.peer_score ?? 0);
                const n = Number(d.peer_nactive ?? 0);
                peerBar.style.width = Math.max(0, Math.min(100, pct)) + '%';
                peerLabel.textContent = n > 0
                    ? `Aktiver als ${pct}% · Ø Peer-Score: ${mean} · n=${n}`
                    : 'Zu wenig Peer-Daten im Zeitraum';
            }

            function updateQuizSection(courseId) {
                const select = document.getElementById('la-quiz-select');
                if (!select) {
                    return;
                }
                const d = getDetails(courseId);
                const chartContainer = document.getElementById('la-quiz-chart');
                const emptyHint = document.getElementById('la-quiz-empty');

                if (!d || !d.quizzes || d.quizzes.length === 0) {
                    select.innerHTML = '<option value="">Keine Quiz in diesem Kurs</option>';
                    if (chartContainer) {
                        chartContainer.style.display = 'none';
                    }
                    if (emptyHint) {
                        emptyHint.style.display = 'block';
                    }
                    return;
                }

                select.innerHTML = '';
                d.quizzes.forEach(function(quiz) {
                    const option = document.createElement('option');
                    option.value = quiz.id;
                    option.textContent = quiz.name;
                    select.appendChild(option);
                });
                if (chartContainer) {
                    chartContainer.style.display = 'none';
                }
                if (emptyHint) {
                    emptyHint.style.display = 'block';
                }
                if (d.quizzes.length > 0) {
                    select.value = d.quizzes[0].id;
                    select.dispatchEvent(new Event('change'));
                }
            }

            function selectCourse(card) {
                const courseId = card.dataset.courseid;
                const courseName = card.dataset.coursename;
                const progress = card.dataset.progress;

                cards.forEach(function(c) {
                    c.classList.remove('la-course-card--selected');
                });
                card.classList.add('la-course-card--selected');

                if (progress !== undefined) {
                    progressBar.style.width = progress + '%';
                    progressLabel.textContent = progress + '%';
                } else {
                    progressBar.style.width = '0%';
                    progressLabel.textContent = '0%';
                }

                if (heading && courseName) {
                    heading.textContent = 'Aktueller Kurs: ' + courseName;
                }

                filterTodos(courseId);
                updatePeerComparison(courseId);
                updateQuizSection(courseId);
            }

            cards.forEach(function(card) {
                card.addEventListener('click', function() {
                    selectCourse(card);
                });
            });

            if (cards[0]) {
                selectCourse(cards[0]);
            }
        }
    };
});
