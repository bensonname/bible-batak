<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$selectedAbbr = isset($_GET['book']) ? trim((string) $_GET['book']) : '';
$selectedChapter = isset($_GET['chapter']) ? max(0, (int) $_GET['chapter']) : 0;
$selectedChapters = [];

$rawChapters = $_REQUEST['chapters'] ?? [];
if (is_array($rawChapters)) {
    foreach ($rawChapters as $chapter) {
        $chapterId = max(0, (int) $chapter);
        if ($chapterId > 0) {
            $selectedChapters[] = $chapterId;
        }
    }
} elseif (is_scalar($rawChapters)) {
    $chapterId = max(0, (int) $rawChapters);
    if ($chapterId > 0) {
        $selectedChapters[] = $chapterId;
    }
}

$selectedChapters = array_values(array_unique($selectedChapters));

$booksRes = $conn->query(
    "SELECT id, book_name, testement, abbrevation, chapter_count FROM bible_books ORDER BY id"
);

$selectedBook = null;
if ($selectedAbbr !== '' && $booksRes) {
    while ($book = $booksRes->fetch_assoc()) {
        if (strcasecmp($book['abbrevation'], $selectedAbbr) === 0) {
            $selectedBook = $book;
            break;
        }
    }

    $booksRes->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bible HKBP – Baca Kitab</title>
    <!-- Google Font for a nicer look -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #eef3f8;
            --bg-panel: #f8fafc;
            --bg-sidebar: #f3f6fb;
            --surface: #ffffff;
            --surface-alt: #edf2f7;
            --primary: #2e7d32;
            --primary-deep: #1d5f26;
            --accent: #2563eb;
            --accent-soft: #dfeaff;
            --text: #1f2937;
            --muted: #5b6472;
            --border: #dfe6ee;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            --verse-size: clamp(8pt, 0.7vw + 0.5rem, 11pt);
            --title-size: clamp(1.2rem, 1.4vw + 1rem, 2rem);
            --subtitle-size: clamp(1rem, 1vw + 0.8rem, 1.5rem);
            --small-size: clamp(0.72rem, 0.5vw + 0.55rem, 0.9rem);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Merriweather', serif;
            margin: 0;
            background: var(--bg-main);
            color: var(--text);
            font-size: var(--verse-size);
            line-height: 1.75;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { color: var(--primary); }

        .header {
            background: linear-gradient(135deg, #2f6f39 0%, #1d4f27 100%);
            color: #fff;
            padding: 18px 0;
            text-align: center;
            box-shadow: 0 4px 14px rgba(29, 79, 39, 0.15);
        }

        .header h1 {
            margin: 0;
            font-weight: 400;
            letter-spacing: 0.02em;
            font-size: clamp(1.3rem, 2vw, 2.3rem);
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 22px;
        }

        .app-shell {
            display: flex;
            gap: 22px;
            align-items: flex-start;
        }

        .sidebar {
            width: 280px;
            min-width: 280px;
            background: var(--bg-sidebar);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px 14px;
            position: sticky;
            top: 20px;
        }

        .sidebar-header {
            margin-bottom: 16px;
            padding: 10px 12px;
            background: linear-gradient(135deg, #ffffff, #edf2fa);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary-deep);
            text-align: center;
        }

        .sidebar form {
            margin-bottom: 18px;
        }

        .sidebar label {
            display: block;
            font-size: var(--small-size);
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--muted);
        }

        .sidebar select,
        .sidebar button,
        .chapter-list input[type=checkbox],
        .chapter-list button {
            font-family: 'Merriweather', serif;
        }

        .sidebar select,
        .sidebar button {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            font-size: var(--small-size);
        }

        .sidebar button {
            margin-top: 8px;
            background: linear-gradient(135deg, var(--accent), #1d4ed8);
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .book-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 72vh;
            overflow-y: auto;
            padding-right: 6px;
        }

        .book-list a {
            display: block;
            padding: 9px 10px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid var(--border);
            font-size: var(--small-size);
            color: var(--text);
            transition: all 0.2s ease;
        }

        .book-list a.active,
        .book-list a:hover {
            background: var(--accent-soft);
            border-color: #bfd2ff;
            color: var(--primary-deep);
            font-weight: 700;
        }

        .main-panel {
            flex: 1;
            min-width: 0;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: clamp(14px, 2vw, 24px);
            margin-bottom: 20px;
        }

        .book-header {
            text-align: center;
            margin: 0 0 18px;
            font-size: var(--title-size);
            line-height: 1.35;
            color: var(--primary-deep);
            font-weight: 700;
        }

        .chapter-list {
            text-align: center;
            padding: 8px 0;
        }

        .chapter-list strong {
            font-size: var(--small-size);
            color: var(--muted);
        }

        .chapter-list label {
            margin: 0 8px;
            font-size: var(--small-size);
            display: inline-block;
            padding: 2px 0;
        }

        .chapter-list input[type=checkbox] {
            margin-right: 4px;
            accent-color: var(--primary);
        }

        .chapter-title {
            text-align: center;
            margin: 18px 0 12px;
            font-size: var(--subtitle-size);
            line-height: 1.4;
            color: var(--primary);
            font-weight: 700;
        }

        .perikop {
            margin: 18px 0 8px;
            text-align: center;
            font-weight: 700;
            font-size: var(--small-size);
            color: var(--primary-deep);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .verse {
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
            padding: clamp(10px, 1vw, 14px) clamp(12px, 1.4vw, 18px);
            margin-bottom: 8px;
            border-left: 4px solid var(--primary);
            border-radius: 10px;
            display: block;
            font-size: var(--verse-size);
            line-height: 1.8;
            box-shadow: inset 0 0 0 1px rgba(46,125,50,0.04);
        }

        .verse strong {
            color: var(--primary-deep);
            font-weight: 700;
            margin-right: 5px;
        }

        .verse-text {
            display: inline;
        }

        .verse-text::first-letter {
            font-size: clamp(12px, 1.2vw + 0.8rem, 14px);
            font-weight: 700;
            color: var(--primary-deep);
        }

        .nav {
            text-align: center;
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
        }

        .nav a {
            padding: 8px 12px;
            border-radius: 10px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: var(--small-size);
        }

        .nav a:hover {
            background: #e4ecf8;
            border-color: #cfe0ff;
            color: var(--primary-deep);
        }

        @media (max-width: 980px) {
            .app-shell {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                position: static;
            }

            .book-list {
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            .container { padding: 12px; }
            .card { padding: 14px 12px; }
            .header { padding: 16px 0; }
            .sidebar { padding: 14px 12px; }
            .nav a {
                width: 100%;
            }
            .chapter-list label {
                display: inline-block;
                margin: 4px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="header"><h1>Bible HKBP – Baca Kitab</h1></div>
    <div class="container">
        <div class="app-shell">
            <aside class="sidebar">
                <div class="sidebar-header">Bible HKBP</div>

                <form method="get" action="">
                    <label for="bookSelect">Pilih Kitab</label>
                    <select name="book" id="bookSelect" required>
                        <option value="" disabled selected>-- Pilih Kitab --</option>
                        <?php if ($booksRes): $booksRes->data_seek(0); while ($b = $booksRes->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($b['abbrevation']) ?>"
                                <?= strcasecmp($b['abbrevation'], $selectedAbbr) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['book_name'] . ' (' . $b['abbrevation'] . ')') ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <button type="submit">Baca</button>
                </form>

                <?php if ($booksRes): $booksRes->data_seek(0); ?>
                    <nav class="book-list">
                        <?php while ($bookItem = $booksRes->fetch_assoc()): ?>
                            <a href="?book=<?= urlencode($bookItem['abbrevation']) ?>"
                               class="<?= strcasecmp($bookItem['abbrevation'], $selectedAbbr) === 0 ? 'active' : '' ?>">
                                <?= htmlspecialchars($bookItem['book_name']) ?>
                            </a>
                        <?php endwhile; ?>
                    </nav>
                <?php endif; ?>
            </aside>

            <main class="main-panel">
                <?php if ($selectedBook): ?>
                    <div class="card">
                        <h2 class="book-header">
                            Kode Kitab: <?=htmlspecialchars($selectedBook['book_name'])?> (<?=htmlspecialchars($selectedBook['abbrevation'])?>)
                        </h2>
                        <?php
                        $showChapterSelection = ($selectedChapter === 0 && empty($selectedChapters));
                        if ($showChapterSelection):
                            $chapRes = $conn->prepare(
                                "SELECT DISTINCT chapter FROM bible_verses WHERE book_id = ? ORDER BY chapter"
                            );
                            $chapRes->bind_param('i', $selectedBook['id']);
                            $chapRes->execute();
                            $chapResult = $chapRes->get_result();
                        ?>
                            <form method="post" action="?book=<?= urlencode($selectedAbbr) ?>" class="chapter-list">
                                <input type="hidden" name="book" value="<?= htmlspecialchars($selectedAbbr) ?>">
                                <strong>Pilih Pasal (centang satu atau lebih):</strong><br>
                                <?php while ($c = $chapResult->fetch_assoc()): ?>
                                    <?php $chapterValue = (int) $c['chapter']; ?>
                                    <label>
                                        <input type="checkbox" name="chapters[]" value="<?= $chapterValue ?>"
                                            <?= in_array($chapterValue, $selectedChapters, true) ? 'checked' : '' ?>>
                                        <?= $chapterValue ?>
                                    </label>
                                <?php endwhile; ?>
                                <br><br>
                                <button type="submit" name="view" value="1">Lihat Ayat</button>
                            </form>
                        <?php else: ?>
                            <?php
                            $chaptersToShow = [];
                            if (!empty($selectedChapters)) {
                                $chaptersToShow = array_values(array_unique(array_map('intval', $selectedChapters)));
                            } elseif ($selectedChapter > 0) {
                                $chaptersToShow = [(int) $selectedChapter];
                            }

                            foreach ($chaptersToShow as $chap):
                                $perikopStmt = $conn->prepare(
                                    "SELECT verses, title FROM chapter_periskop WHERE book_id = ? AND chapter = ? ORDER BY verses"
                                );
                                $perikopStmt->bind_param('ii', $selectedBook['id'], $chap);
                                $perikopStmt->execute();
                                $perikopTitleResult = $perikopStmt->get_result();
                                $perikopRows = [];
                                while ($perikopRow = $perikopTitleResult->fetch_assoc()) {
                                    $perikopRows[] = [
                                        'verse' => (int) $perikopRow['verses'],
                                        'title' => $perikopRow['title'],
                                    ];
                                }

                                $chapterEndStmt = $conn->prepare(
                                    "SELECT MAX(verse) AS last_verse FROM bible_verses WHERE book_id = ? AND chapter = ?"
                                );
                                $chapterEndStmt->bind_param('ii', $selectedBook['id'], $chap);
                                $chapterEndStmt->execute();
                                $chapterEnd = (int) $chapterEndStmt->get_result()->fetch_assoc()['last_verse'];

                                $perikopByVerse = [];
                                foreach ($perikopRows as $index => $perikopRow) {
                                    $startVerse = $perikopRow['verse'];
                                    $endVerse = isset($perikopRows[$index + 1])
                                        ? $perikopRows[$index + 1]['verse'] - 1
                                        : $chapterEnd;
                                    $range = $startVerse === $endVerse
                                        ? (string) $startVerse
                                        : $startVerse . '-' . $endVerse;
                                    $title = trim($perikopRow['title']);
                                    $rangePattern = '/(?:^|\\s|\\()' . preg_quote($range, '/') . '\\.?\\)?$/u';
                                    $perikopByVerse[$startVerse] = preg_match($rangePattern, $title)
                                        ? $title
                                        : $title . ' (' . $range . ')';
                                }

                                $perikopTitleVerse = $perikopRows[0]['verse'] ?? null;
                                $perikopTitle = $perikopTitleVerse !== null
                                    ? $perikopByVerse[$perikopTitleVerse]
                                    : null;

                                $versesStmt = $conn->prepare(
                                    "SELECT v.verse, v.text
                                     FROM bible_verses v
                                     WHERE v.book_id = ? AND v.chapter = ?
                                     ORDER BY v.verse"
                                );
                                $versesStmt->bind_param('ii', $selectedBook['id'], $chap);
                                $versesStmt->execute();
                                $versesRes = $versesStmt->get_result();

                                $prevStmt = $conn->prepare(
                                    "SELECT MAX(chapter) AS prev FROM bible_verses WHERE book_id = ? AND chapter < ?"
                                );
                                $prevStmt->bind_param('ii', $selectedBook['id'], $chap);
                                $prevStmt->execute();
                                $prev = $prevStmt->get_result()->fetch_assoc()['prev'];

                                $nextStmt = $conn->prepare(
                                    "SELECT MIN(chapter) AS next FROM bible_verses WHERE book_id = ? AND chapter > ?"
                                );
                                $nextStmt->bind_param('ii', $selectedBook['id'], $chap);
                                $nextStmt->execute();
                                $next = $nextStmt->get_result()->fetch_assoc()['next'];
                            ?>
                                <h3 class="chapter-title">
                                    Pasal <?= (int) $chap ?>
                                </h3>
                                <?php if ($perikopTitle): ?>
                                    <div class="perikop"><?= htmlspecialchars($perikopTitle) ?></div>
                                <?php endif; ?>
                                <?php if ($versesRes && $versesRes->num_rows > 0): ?>
                                    <div class="verses">
                                        <?php while ($v = $versesRes->fetch_assoc()): ?>
                                            <?php if (isset($perikopByVerse[(int) $v['verse']]) && (int) $v['verse'] !== $perikopTitleVerse): ?>
                                                <div class="perikop"><?= htmlspecialchars($perikopByVerse[(int) $v['verse']]) ?></div>
                                            <?php endif; ?>
                                            <div class="verse"><strong><?= (int) $v['verse'] ?>.</strong> <span class="verse-text"><?= htmlspecialchars($v['text']) ?></span></div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align:center;">Tidak ada ayat pada pasal ini.</p>
                                <?php endif; ?>
                                <div class="nav">
                                    <?php if ($prev !== null): ?>
                                        <a href="?book=<?= urlencode($selectedAbbr) ?>&chapter=<?= (int) $prev ?>">← Pasal <?= (int) $prev ?></a>
                                    <?php endif; ?>
                                    <a href="?book=<?= urlencode($selectedAbbr) ?>">Daftar Pasal</a>
                                    <?php if ($next !== null): ?>
                                        <a href="?book=<?= urlencode($selectedAbbr) ?>&chapter=<?= (int) $next ?>">Pasal <?= (int) $next ?> →</a>
                                    <?php endif; ?>
                                    <a href="index.php">← Kembali ke Pilihan Buku</a>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
