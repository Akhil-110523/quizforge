# QuizForge – XML-powered Quiz Engine

A mini project that delivers randomised quizzes from an XML question bank, scores them on the server, and keeps a leaderboard.

## Technologies and their role
| Tech | Role |
|------|------|
| HTML | Page structure, form, three screens (setup / play / result) |
| CSS | Dark responsive theme, progress bar, option cards |
| JavaScript | Quiz flow, 20-second timer, keyboard shortcuts |
| AJAX (fetch) | Loads quiz, submits answers, refreshes leaderboard without page reload |
| PHP | Picks random questions, shuffles options, scores answers, saves results |
| XML | `questions.xml` (question bank) and `scores.xml` (leaderboard) |
| DTD | `questions.dtd` restricts categories to a fixed list and enforces 4 options per question; `scores.dtd` validates every saved score |
| XSL | One stylesheet `quiz.xsl` renders the quiz, the result review and the leaderboard |

## Why it is efficient
- Correct answers never reach the browser: PHP strips them, and scoring is done on the server.
- One XSL file is downloaded and compiled once, then reused for all three screens.
- Only 3 requests per round: get quiz, submit answers, get leaderboard.
- `scores.xml` is validated against its DTD, written with a file lock, and pruned to the latest 100 entries.
- Adding questions needs no code change: add a `<question>` to `questions.xml` (the DTD will tell you if it is wrong).

## Run it
1. Install PHP (or XAMPP/WAMP).
2. In this folder run: `php -S localhost:8000`
3. Open http://localhost:8000
4. Make sure `scores.xml` is writable.

## Flow
Browser → AJAX → `api.php?action=quiz` → random XML (no answers) → `quiz.xsl` → question screen
→ answers → `api.php?action=submit` → score + review XML + save to `scores.xml` (DTD-checked) → `quiz.xsl` → result screen.
