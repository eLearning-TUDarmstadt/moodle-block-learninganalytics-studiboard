# Learning Analytics Block

A Moodle block that shows learners a personalised dashboard with course progress, peer comparison, quiz comparison and todo lists across their enrolled courses.

## Purpose

The block helps learners see at a glance:

- Progress per course and overall
- How their activity compares to peers (based on weighted log actions)
- Their quiz results vs. course average
- A combined todo list (important items and deadlines, plus material and discussion)

## Main features

- **Course overview**: List of enrolled courses with progress bars; selecting a course updates the detail view.
- **Course progress**: Progress per course based on completion of assignments, quizzes, wikis and material (with configurable weighting).
- **Peer comparison**: Activity comparison against other students in the same course (last 14 days, weighted actions from the standard log store).
- **Quiz comparison**: Per-quiz view of the current user’s score vs. course average, loaded via AJAX when a quiz is selected.
- **Todo list**: Two sections — “Important & deadlines” (assignments, quizzes, wikis) and “Material & discussion” — with completion state and due dates where applicable.

## Technical architecture

- **Block entry**: `block_learninganalytics.php` only handles block lifecycle, CSS and template rendering; all data is provided by `analytics_service`.
- **Services** (under `classes/local/`):
  - `analytics_service`: Orchestrates the other services and builds the single data structure for the block template.
  - `course_service`: Course progress, completion checks, module categories and due dates.
  - `todo_service`: Builds important and material todo lists and their sorting.
  - `quiz_service`: Quiz list per course and quiz scores (user, average, max).
  - `peer_service`: Weighted activity scores and peer comparison metrics from the log store.
- **External API**: `classes/external/get_quiz_data.php` exposes the quiz comparison data via the web service `block_learninganalytics_get_quiz_data` (AJAX). A legacy endpoint `block_learninganalytics_get_quiz_comparison` is still registered in `db/services.php`.
- **Templates**: Main template is `templates/block.mustache`; it includes partials `quiz_chart.mustache` and `todo_list.mustache`. Data is passed as one structure; no API changes for the front end.
- **JavaScript (AMD)**: `dashboard.js` initialises the block and delegates course switching to `course_selector.js`. `quiz_chart.js` handles the quiz dropdown and AJAX chart display. No other AMD modules are loaded by the block.

## Building AMD modules

Moodle serves AMD modules from `amd/build/*.min.js`. If you change files in `amd/src/`, rebuild from the Moodle root:

```bash
npm install   # once, if not already done
npx grunt amd
```

Alternatively, run `npx grunt amd` from `blocks/learninganalytics` to build only this block. Without built files, the block’s JavaScript (course switching, quiz chart) will not load.

## Block icon

The block ships with a minimal placeholder `pix/icon.png`. For a clearer block list appearance, replace it with a 24×24 or 32×32 PNG (e.g. chart/graph style for learning analytics).

## Installation

### From Git (e.g. GitLab)

Clone the repository into your Moodle `blocks/` directory so that the plugin path is `blocks/learninganalytics/`:

```bash
cd /path/to/moodle/blocks
git clone <your-gitlab-repo-url> learninganalytics
```

### Manual

1. Copy the `learninganalytics` folder into `blocks/` of your Moodle installation.
2. Visit **Site administration → Notifications** and complete the upgrade.
3. Add the block to the dashboard (or other block regions) as needed.
4. The block uses the standard log store (`logstore_standard_log`) for peer comparison; ensure it is enabled if you use that feature.
5. For quiz comparison, the block and the quiz module must be available; no extra plugins are required.

## Quiz comparison

- The block shows a dropdown of quizzes for the currently selected course. When a quiz is selected, the front end calls the web service `block_learninganalytics_get_quiz_data` with the quiz id.
- The backend returns the current user’s score, the average score of all participants and the maximum score. The diagram is rendered in the browser.
- The webservice is restricted to the current user’s data and validates context. The quiz list and scores are provided by `quiz_service` and the external class `block_learninganalytics\external\get_quiz_data`.

## Testing

- PHPUnit tests are in the `tests/` directory:
  - `tests/quiz_service_test.php`: Unit tests for `quiz_service` (quiz list, scores with and without attempts).
  - `tests/external/get_quiz_data_test.php`: Tests for the external `get_quiz_data` API (structure, current user, parameters/returns).
  - `tests/todo_service_test.php`: Basic tests for `todo_service` (empty courses, course with no modules).
- Run from the Moodle root after initialising PHPUnit, for example:
  - `vendor/bin/phpunit blocks/learninganalytics/tests/`
  - Or run a single test file, e.g. `vendor/bin/phpunit blocks/learninganalytics/tests/quiz_service_test.php`

## Known limitations and open points

- **Peer comparison** depends on `logstore_standard_log`. If it is disabled or empty, the peer section shows a fallback message. The “student” role is used to restrict peers when present; otherwise all users with log entries in the period are considered.
- **Course progress** uses Moodle completion and, for material, “viewed” actions in the log store. Courses or activities without completion tracking may show limited progress.
- **Context for quiz webservice**: The external quiz endpoint currently validates system context; tightening to course context is possible if the calling context is extended.
- **Legacy code**: The legacy webservice `block_learninganalytics_get_quiz_comparison` and the class `block_learninganalytics\external` in `classes/external.php` are still registered for backward compatibility; they can be removed in a future version if no longer needed.
- **Language**: The block interface and tooltips are currently in German; language strings are in `lang/en/block_learninganalytics.php` (and can be overridden per language pack).
