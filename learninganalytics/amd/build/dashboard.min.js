define(['block_learninganalytics/course_selector'], function(courseSelector) {
    "use strict";

    /**
     * Initialise the block UI: read course details from the page and delegate
     * course-switch behaviour to the course selector. Does not handle quiz
     * or chart logic (handled by quiz_chart module loaded separately).
     */
    return {
        init: function() {
            const detailsEl = document.getElementById('la-course-details');
            let courseDetails = [];
            try {
                courseDetails = detailsEl ? JSON.parse(detailsEl.textContent) : [];
            } catch (e) {
                console.warn('[LA] Could not parse course_details_json', e);
            }
            courseSelector.init(courseDetails);
        }
    };
});
