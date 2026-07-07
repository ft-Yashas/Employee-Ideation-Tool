<?php
require_once __DIR__ . '/config.php';

// ═══════════════════════════════════════════════════════════════════════════════
// HEURISTIC HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Returns true if the text contains quantitative evidence —
 * multi-digit numbers, percentages, currency, or unit-paired figures.
 * Used to reward ideas that measure both the problem and the solution.
 */
function isQuantified(string $text): bool
{
    if ($text === '') return false;
    // Numbers paired with a unit or suffix (e.g. "30%", "₹5000", "2 days")
    if (preg_match('/\d+\s*(%|percent|rs\.?|inr|₹|\$|hr|hour|day|min|unit|piece|time|x\b)/i', $text)) return true;
    // Stand-alone multi-digit number (single digits are too vague)
    if (preg_match('/\b\d{2,}\b/', $text)) return true;
    return false;
}

/**
 * Returns true if the text shows genuine depth:
 * enough total words AND enough unique vocabulary (type-token ratio).
 * Filters out padded or copy-pasted text.
 */
function isDetailed(string $text, int $minWords = 25, float $minTTR = 0.50): bool
{
    $words = preg_split('/\s+/', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    $total = count($words);
    if ($total < $minWords) return false;
    return (count(array_unique($words)) / $total) >= $minTTR;
}

/**
 * Counts approximate sentences by terminal punctuation.
 * Returns at least 1 if the text is non-empty.
 */
function countSentences(string $text): int
{
    $text = trim($text);
    if ($text === '') return 0;
    $n = preg_match_all('/[.!?]+(?:\s|$)/', $text, $m);
    return max(1, $n ?: 1);
}

/**
 * Lexical diversity: unique words ÷ total words (type-token ratio).
 * Range 0.0–1.0. Higher = richer, less repetitive vocabulary.
 * Scores near 1.0 on very short texts — only meaningful at ≥15 words.
 */
function lexicalDiversity(string $text): float
{
    $words = preg_split('/\s+/', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    $total = count($words);
    if ($total === 0) return 0.0;
    return count(array_unique($words)) / $total;
}

/**
 * Returns true if the solution text describes HOW something will be done —
 * not just WHAT, but implementation-oriented language.
 * Detects modal-verb + action patterns, sequencing words, and proposal phrases.
 */
function hasActionableSteps(string $text): bool
{
    if ($text === '') return false;
    $patterns = [
        // Modal + action verb (e.g. "will implement", "can be deployed")
        '/\b(will|can|shall)\s+(be\s+)?(implement|introduc|deploy|install|replac|creat|establish|develop|train|monitor|audit|track|measur|digitiz|automat)/i',
        // "by implementing", "by using", "through integrating"
        '/\bby\s+(implement|introduc|deploy|install|using|integrat|conduct|establish|train)/i',
        '/\bthrough\s+\w+/i',
        // "propose to", "proposed to"
        '/\bpropos(e|ed|ing)\s+to\s+\w+/i',
        // Sequential steps or phases
        '/\b(step\s*\d|phase\s*\d|first[,\s]|second[,\s]|then[,\s]|next[,\s]|finally[,\s])/i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $text)) return true;
    }
    return false;
}

/**
 * Returns a penalty score (0–9) for generic low-value phrases.
 * These phrases indicate the submitter hasn't thought through the idea.
 * Each hit adds 3 pts penalty, capped at 9.
 */
function genericPhrasePenalty(string $text): int
{
    $text = mb_strtolower($text);
    $phrases = [
        'improve the system', 'make it better', 'enhance efficiency',
        'resolve the issue', 'fix the problem', 'improve process',
        'better performance', 'increase productivity', 'needs improvement',
        'should be improved', 'can be better', 'more efficient way',
        'optimize the process', 'improve overall', 'generally improve',
    ];
    $hits = 0;
    foreach ($phrases as $phrase) {
        if (str_contains($text, $phrase)) $hits++;
    }
    return min(9, $hits * 3);
}

/**
 * Word count helper. Returns number of words in a string.
 */
function wordCount(string $text): int
{
    return count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));
}


// ═══════════════════════════════════════════════════════════════════════════════
// DIMENSION SCORERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Dimension 1 — Problem Clarity (0–20)
 *
 * Evaluates how well the submitter has articulated the current situation.
 * A high-scoring description is specific, structured, and evidence-backed —
 * not a vague complaint.
 *
 * Signals rewarded:
 *   a) Multi-sentence structure (+5) — shows organised thinking
 *   b) Lexical diversity (+5)        — not repetitive padding
 *   c) Quantitative evidence (+5)    — numbers ground the problem in reality
 *   d) Cause-effect language (+5)    — shows root-cause understanding
 *
 * Penalties:
 *   – Generic phrases  (up to –3)
 *   – Under 15 words   (–5)
 */
function scoreProblemClarity(string $sit): int
{
    if (trim($sit) === '') return 0;
    $score = 0;

    // a) Sentence structure
    $sentences = countSentences($sit);
    if      ($sentences >= 4) $score += 5;
    elseif  ($sentences >= 2) $score += 3;
    else                      $score += 1;

    // b) Vocabulary richness (TTR meaningful at ≥15 words)
    $ttr = lexicalDiversity($sit);
    if      ($ttr >= 0.70) $score += 5;
    elseif  ($ttr >= 0.55) $score += 3;
    elseif  ($ttr >= 0.40) $score += 1;

    // c) Numbers / measurements describing the problem scope
    if (isQuantified($sit)) $score += 5;

    // d) Causal / contextual connectors
    $lower      = mb_strtolower($sit);
    $causeWords = ['because','due to','results in','causing','leads to','result of',
                   'currently','at present','since','therefore','consequently','as a result'];
    $causalHits = 0;
    foreach ($causeWords as $w) {
        if (str_contains($lower, $w)) $causalHits++;
    }
    if      ($causalHits >= 3) $score += 5;
    elseif  ($causalHits >= 1) $score += 3;

    // Penalties
    $score -= (int)ceil(genericPhrasePenalty($sit) / 3);
    if (wordCount($sit) < 15) $score -= 5;

    return max(0, min(20, $score));
}

/**
 * Dimension 2 — Solution Quality (0–20)
 *
 * Evaluates whether the proposed solution is concrete and well-explained.
 * A "what" without a "how" scores low. Specificity and mechanism matter.
 *
 * Signals rewarded:
 *   a) Multi-sentence explanation (+5) — structured proposal
 *   b) Actionable / implementation language (+6) — HOW, not just WHAT
 *   c) Lexical diversity (+5)          — rich description, not repetition
 *   d) Specific tool / mechanism (+4)  — names a system, method, or artefact
 *
 * Penalties:
 *   – Generic phrases  (up to –5)
 *   – Under 15 words   (–5)
 */
function scoreSolutionQuality(string $sol): int
{
    if (trim($sol) === '') return 0;
    $score = 0;

    // a) Sentence structure
    $sentences = countSentences($sol);
    if      ($sentences >= 4) $score += 5;
    elseif  ($sentences >= 2) $score += 3;
    else                      $score += 1;

    // b) Implementation-oriented language (HOW)
    if (hasActionableSteps($sol)) $score += 6;

    // c) Vocabulary richness
    $ttr = lexicalDiversity($sol);
    if      ($ttr >= 0.65) $score += 5;
    elseif  ($ttr >= 0.50) $score += 3;
    elseif  ($ttr >= 0.35) $score += 1;

    // d) Specific mechanism — names a tool, artefact, or formal method
    $lower     = mb_strtolower($sol);
    $mechanisms = ['system','software','database','dashboard','checklist','form',
                   'procedure','protocol','template','sensor','scanner','camera',
                   'algorithm','workflow','portal','module','report','alert','erp',
                   'application','barcode','rfid','qr code','spreadsheet'];
    foreach ($mechanisms as $m) {
        if (str_contains($lower, $m)) { $score += 4; break; }
    }

    // Penalties
    $score -= (int)ceil(genericPhrasePenalty($sol) / 2);
    if (wordCount($sol) < 15) $score -= 5;

    return max(0, min(20, $score));
}

/**
 * Dimension 3 — Feasibility (0–15)
 *
 * Assesses how realistic and grounded the proposed solution is.
 * Ideas that mention resources, roles, or timelines score higher.
 * Overreaching promises (e.g. "zero defects") are penalised.
 *
 * Signals rewarded:
 *   a) Resource / people / time awareness (+6) — shows practical thinking
 *   b) Absence of overreach claims (+4)        — realistic scope
 *   c) Solution depth matches impact claim (+5) — high-impact ideas must be detailed
 */
function scoreFeasibility(string $sol, string $sit, string $impactLevel): int
{
    $score = 0;
    $combined = mb_strtolower($sol . ' ' . $sit);

    // a) Mentions responsible parties, tools, timelines, or budgets
    $resourceWords = ['team','department','manager','operator','staff','vendor',
                      'supplier','month','week','quarter','phase','pilot','trial',
                      'budget','cost','investment','existing','available','current system'];
    $resourceHits = 0;
    foreach ($resourceWords as $w) {
        if (str_contains($combined, $w)) $resourceHits++;
    }
    if      ($resourceHits >= 4) $score += 6;
    elseif  ($resourceHits >= 2) $score += 4;
    elseif  ($resourceHits >= 1) $score += 2;

    // b) Penalise overreach / impossibility claims
    $overreach = ['completely eliminate','zero defect','fully automate everything',
                  'no human error','100% accuracy','eliminate all errors','perfect system'];
    $overreachHits = 0;
    foreach ($overreach as $w) {
        if (str_contains($combined, $w)) $overreachHits++;
    }
    $score += ($overreachHits === 0) ? 4 : ($overreachHits === 1 ? 2 : 0);

    // c) Solution depth must match impact claim
    $solWords = wordCount($sol);
    if      ($impactLevel === 'High'   && $solWords >= 40) $score += 5;
    elseif  ($impactLevel === 'Medium' && $solWords >= 20) $score += 4;
    elseif  ($impactLevel === 'Low')                       $score += 3;
    elseif  ($solWords >= 20)                              $score += 2;

    return max(0, min(15, $score));
}

/**
 * Dimension 4 — Business Impact (0–20)
 *
 * Evaluates the significance and breadth of the idea's organisational effect.
 * The declared impact level is the anchor; impact areas and tangible benefit
 * provide corroborating evidence that raises or validates the claim.
 *
 * Signals rewarded:
 *   a) Declared impact level anchor (High=9, Medium=6, Low=3)
 *   b) Number of impact areas — breadth of organisational reach (up to +7)
 *   c) Tangible benefit stated and quantified (up to +4)
 */
function scoreBusinessImpact(string $impactLevel, array $impAreas,
                              string $tangible, string $intangible): int
{
    $score = 0;

    // a) Impact level anchor
    $levelMap = ['High' => 9, 'Medium' => 6, 'Low' => 3];
    $score += $levelMap[$impactLevel] ?? 6;

    // b) Impact area breadth
    $areaCount = count($impAreas);
    if      ($areaCount >= 5) $score += 7;
    elseif  ($areaCount >= 3) $score += 5;
    elseif  ($areaCount >= 2) $score += 3;
    elseif  ($areaCount === 1) $score += 1;

    // c) Tangible benefit as evidence
    if (trim($tangible) !== '') {
        $score += 2;
        if (isQuantified($tangible)) $score += 2;
    }

    return max(0, min(20, $score));
}

/**
 * Dimension 5 — Measurability (0–10)
 *
 * Checks whether the expected outcomes are quantified and measurable.
 * Ideas with baseline + target numbers score highest.
 * A non-empty tangible benefit without numbers gets partial credit.
 *
 * Signals rewarded:
 *   a) Tangible benefit is quantified (+5) or just present (+2)
 *   b) Problem description has baseline numbers (+3)
 *   c) From-to targets or benchmark language in solution/benefit (+2)
 */
function scoreMeasurability(string $tangible, string $sit, string $sol): int
{
    $score = 0;

    // a) Tangible benefit quality
    if      (isQuantified($tangible))    $score += 5;
    elseif  (trim($tangible) !== '')     $score += 2;

    // b) Baseline quantification in the situation
    if (isQuantified($sit)) $score += 3;

    // c) From→to targets or benchmark language
    $combined = mb_strtolower($sol . ' ' . $tangible);
    if (preg_match('/\bfrom\s+\d+.*?to\s+\d+|\bby\s+\d+\s*(%|percent)|\btarget\b|\bgoal\b|\bbenchmark\b/i', $combined)) {
        $score += 2;
    }

    return max(0, min(10, $score));
}

/**
 * Dimension 6 — Innovation / Uniqueness (0–15)
 *
 * Rewards ideas that go beyond the obvious incremental fix.
 * Technology adoption, new process design, cross-functional scope,
 * and root-cause focus are all positive signals.
 *
 * Signals rewarded:
 *   a) Technology / digital transformation angle (up to +5)
 *   b) New methodology or process design (+4)
 *   c) Cross-functional impact — 3+ areas (+3) or 2 areas (+1)
 *   d) Root-cause orientation (+3)
 */
function scoreInnovation(string $sol, string $sit, array $impAreas): int
{
    $score = 0;
    $combined = mb_strtolower($sol . ' ' . $sit);

    // a) Technology / digital angle
    $techWords = ['digital','software','app','application','automation','automated',
                  'sensor','iot','barcode','qr','rfid','ai','machine learning',
                  'real-time','cloud','dashboard','analytics','erp','api','database'];
    $techHits = 0;
    foreach ($techWords as $w) {
        if (str_contains($combined, $w)) $techHits++;
    }
    if      ($techHits >= 3) $score += 5;
    elseif  ($techHits >= 1) $score += 3;

    // b) New process or methodology being designed from scratch
    $newProcess = ['new process','new procedure','redesign','restructure','new workflow',
                   'new system','new approach','novel','innovative','introduce a',
                   'establish a','create a','develop a'];
    foreach ($newProcess as $w) {
        if (str_contains($combined, $w)) { $score += 4; break; }
    }

    // c) Cross-functional breadth
    $areaCount = count($impAreas);
    if      ($areaCount >= 3) $score += 3;
    elseif  ($areaCount >= 2) $score += 1;

    // d) Root-cause orientation (not just treating symptoms)
    $rootWords = ['root cause','underlying','fundamental','source of the',
                  'prevent recurrence','prevent future','systemic','recurring'];
    foreach ($rootWords as $w) {
        if (str_contains($combined, $w)) { $score += 3; break; }
    }

    return max(0, min(15, $score));
}


// ═══════════════════════════════════════════════════════════════════════════════
// CORE SCORING ENGINE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Heuristic Idea Scoring Model (v2)
 * ===================================
 * Evaluates an idea across 6 weighted dimensions. Returns the total score
 * (0–100) and a per-dimension breakdown. Uses semantic heuristics rather
 * than raw character counts or keyword frequency.
 *
 * DIMENSION WEIGHTS
 * -----------------
 *   1. Problem Clarity      0–20  Does the submitter understand the problem?
 *   2. Solution Quality     0–20  Is the solution concrete and actionable?
 *   3. Feasibility          0–15  Can it realistically be implemented?
 *   4. Business Impact      0–20  How significant is the organisational effect?
 *   5. Measurability        0–10  Are outcomes quantified or trackable?
 *   6. Innovation           0–15  Does this go beyond the obvious?
 *                           ────
 *   TOTAL                   0–100
 *
 * DESIGN PRINCIPLES
 * -----------------
 *   – Text length is a weak signal only; richness and structure matter more.
 *   – Keyword presence is checked categorically, not counted for frequency.
 *   – Generic low-value phrases are penalised.
 *   – Overreaching or unsubstantiated claims reduce feasibility.
 *   – Quantification is rewarded at multiple dimensions independently.
 *
 * @param  array $idea  Associative array with idea fields from the DB.
 * @return array        ['score' => int, 'breakdown' => [...]]
 */
function scoreIdeaWithBreakdown(array $idea): array
{
    $sit      = trim((string)($idea['present_situation']  ?? ''));
    $sol      = trim((string)($idea['proposed_solution']  ?? ''));
    $level    = trim((string)($idea['impact_level']       ?? 'Medium'));
    $tangible = trim((string)($idea['tangible_benefit']   ?? ''));
    $intang   = trim((string)($idea['intangible_benefit'] ?? ''));

    $impAreas = array_values(array_filter(
        array_map('trim', explode(',', (string)($idea['impact_areas'] ?? '')))
    ));

    $problem     = scoreProblemClarity($sit);
    $solution    = scoreSolutionQuality($sol);
    $feasibility = scoreFeasibility($sol, $sit, $level);
    $impact      = scoreBusinessImpact($level, $impAreas, $tangible, $intang);
    $measure     = scoreMeasurability($tangible, $sit, $sol);
    $innovation  = scoreInnovation($sol, $sit, $impAreas);

    $total = max(0, min(100, $problem + $solution + $feasibility + $impact + $measure + $innovation));

    return [
        'score'     => $total,
        'breakdown' => [
            'problem'       => $problem,
            'solution'      => $solution,
            'feasibility'   => $feasibility,
            'impact'        => $impact,
            'measurability' => $measure,
            'innovation'    => $innovation,
        ],
    ];
}

/**
 * Generates a human-readable fallback reason from a scoring breakdown.
 * Called when OpenAI is unavailable and the heuristic model runs instead.
 */
function buildFallbackReason(array $bd): string
{
    $strengths  = [];
    $weaknesses = [];

    if ($bd['problem'] >= 15)      $strengths[]  = 'problem is clearly defined';
    elseif ($bd['problem'] < 8)    $weaknesses[] = 'problem statement needs more specificity';

    if ($bd['solution'] >= 15)     $strengths[]  = 'solution is well-articulated and actionable';
    elseif ($bd['solution'] < 8)   $weaknesses[] = 'solution could be more detailed and concrete';

    if ($bd['feasibility'] >= 10)  $strengths[]  = 'implementation appears realistic';
    elseif ($bd['feasibility'] < 5) $weaknesses[] = 'feasibility is unclear — consider naming resources or timelines';

    if ($bd['impact'] >= 15)       $strengths[]  = 'strong and broad business impact';

    if ($bd['measurability'] >= 7) $strengths[]  = 'outcomes are quantified';
    elseif ($bd['measurability'] < 3) $weaknesses[] = 'consider adding measurable targets or baseline numbers';

    if ($bd['innovation'] >= 10)   $strengths[]  = 'innovative approach';

    $parts = [];
    if (!empty($strengths))  $parts[] = ucfirst(implode(', ', $strengths));
    if (!empty($weaknesses)) $parts[] = ucfirst(implode('; ', $weaknesses));

    $body = !empty($parts)
        ? implode('. ', $parts) . '.'
        : 'Scored using the structured heuristic model.';

    return "Heuristic: {$body}";
}


// ═══════════════════════════════════════════════════════════════════════════════
// PUBLIC API — preserves existing call signatures
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Returns the integer score (0–100) for an idea.
 * Thin wrapper around scoreIdeaWithBreakdown() — preserves backward compatibility
 * with all existing callers (ideas.php, batch_rescore, etc.).
 */
function computeIdeaScore(array $idea): int
{
    return scoreIdeaWithBreakdown($idea)['score'];
}

/**
 * Primary scoring entry point.
 * Attempts OpenAI scoring first; falls back to the heuristic model on failure.
 * Returns score, human-readable reason, source tag, and per-dimension breakdown.
 */
function computeAIScoreWithReason(array $idea): array
{
    $title = (string)($idea['title']             ?? '');
    $sit   = (string)($idea['present_situation'] ?? '');
    $sol   = (string)($idea['proposed_solution'] ?? '');
    $areas = (string)($idea['impact_areas']      ?? '');
    $level = (string)($idea['impact_level']      ?? 'Medium');

    $prompt = <<<PROMPT
Evaluate this employee improvement idea for an operations/manufacturing company.

Return ONLY valid JSON in this exact format — no markdown, no code fences, no extra text:
{"score": <integer 0-100>, "reason": "<one sentence explanation>"}

The reason must be a single sentence (max 20 words) summarising the key strength or weakness that most influenced the score.

Score based on:
- Innovation: Is it a fresh or creative approach?
- Feasibility: Can it realistically be implemented?
- Business Impact: Does it improve cost, quality, safety, or efficiency?

Idea:
Title: {$title}
Present Situation: {$sit}
Proposed Solution: {$sol}
Impact Areas: {$areas}
Impact Level: {$level}
PROMPT;

    $content = callGemini($prompt);

    if ($content !== null) {
        $cleaned = trim($content);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/i',           '', $cleaned);
        $cleaned = trim($cleaned);

        if (preg_match('/\{.*\}/s', $cleaned, $matches)) {
            $parsed = json_decode($matches[0], true);

            if (
                is_array($parsed)
                && array_key_exists('score', $parsed)
                && is_numeric($parsed['score'])
            ) {
                $score  = max(0, min(100, (int)round((float)$parsed['score'])));
                $reason = trim((string)($parsed['reason'] ?? 'Evaluated by AI.'));

                $heuristic = scoreIdeaWithBreakdown($idea);

                return [
                    'score'     => $score,
                    'reason'    => $reason !== '' ? $reason : 'Evaluated by AI.',
                    'source'    => 'gemini',
                    'breakdown' => $heuristic['breakdown'],
                ];
            }
        }

        error_log('Gemini score parse failed. Cleaned content: ' . $cleaned);
    }

    // Heuristic fallback
    $result = scoreIdeaWithBreakdown($idea);

    return [
        'score'     => $result['score'],
        'reason'    => buildFallbackReason($result['breakdown']),
        'source'    => 'fallback',
        'breakdown' => $result['breakdown'],
    ];
}

function saveIdeaScore(int $ideaId, int $score, string $reason = ''): void
{
    db()->prepare("UPDATE ideas SET ai_score = ?, ai_reason = ? WHERE id = ?")
       ->execute([$score, $reason, $ideaId]);
}


// ═══════════════════════════════════════════════════════════════════════════════
// REST ENDPOINT
// ═══════════════════════════════════════════════════════════════════════════════

if (basename($_SERVER['PHP_SELF']) !== 'score.php') return;

$user   = requireAuth();
$action = $_GET['action'] ?? 'score';

if ($action === 'score') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT * FROM ideas WHERE id = ?");
    $stmt->execute([$id]);
    $idea = $stmt->fetch();
    if (!$idea) respond(['success' => false, 'error' => 'Idea not found.'], 404);

    $ai = computeAIScoreWithReason($idea);
    saveIdeaScore($id, $ai['score'], $ai['reason']);
    respond([
        'success'   => true,
        'id'        => $id,
        'ai_score'  => $ai['score'],
        'ai_reason' => $ai['reason'],
        'source'    => $ai['source'],
        'breakdown' => $ai['breakdown'] ?? null,
    ]);
}

if ($action === 'batch_rescore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('admin');
    $ideas   = db()->query("SELECT * FROM ideas")->fetchAll();
    $updated = 0;
    foreach ($ideas as $idea) {
        $ai = computeAIScoreWithReason($idea);
        saveIdeaScore((int)$idea['id'], $ai['score'], $ai['reason']);
        $updated++;
    }
    respond(['success' => true, 'updated' => $updated]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
