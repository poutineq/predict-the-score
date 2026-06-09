<?php
// Note: Ensure 'colours.inc' exists in your directory or comment this out
if (file_exists('colours.inc')) {
    require 'colours.inc';
} else {
    // Fallback if file is missing to prevent crash
    function createGlobalColorArray(): array
    { return ['#FFFFFF' => 'White', '#000000' => 'Black', '#FF0000' => 'Red']; }
    function getColorDDLB($name, $selected, &$unused): string
    { return "<select name='$name'><option value='$selected'>$selected</option></select>"; }
}

const SESSION_LIFETIME = 3600 * 24 * 32; // 32 days in seconds
// Ensure server-side session data persists long enough and the session cookie matches the lifetime
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

const CELL_WIDTH = 40;
const CELL_HEIGHT = 40;
const MAX_SCORES = 40;
const MAX_POINTS = 77;
const DEFAULT_POINTS = 34;
const DEFAULT_SCORES = 0;
const OP_SETNUMSCORES = "Set Number of Scores to Enter (max 40)";
const OP_SETMAXSCORE = "Set Maximum Score to Compute (max 77)";
const OP_VALIDATE = "Validate Input";
const OP_DOIT = "Submit";
const CHECK_RANDOMIZECOLORS = "RandomizeColors";
const LABEL_RANDOMIZE_COLORS = "Randomize Colors";

$gFutureLogs = [];
function futureLog($message) {
    /*
    global $gFutureLogs;
    $gFutureLogs[] = $message;
    */
}

function outputFutureLogs() {
    /*
    global $gFutureLogs;
    foreach ($gFutureLogs as $log) {
        console_log($log);
    }
    $gFutureLogs = []; // Clear logs after outputting
    */
}

// The debugging functions below can be turned on or off by commenting out the print statements.
// They are designed to help debug issues with cookies/session data, especially since cookies/session data
// are set at the end of this script but we want to see their values throughout the script execution.
//function console_log($data) {
    /*
    // Convert arrays or objects safely to a JS-readable format
    $js_code = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo "<script>console.log(" . $js_code . ");</script>";
    */
//}

//function print_rSomething($header, $something) {
    /*
    // The implementation is commented out for production.
    // 1. Bold and escape the header safely
    print(b($header) . "<br/>");  
    // 2. Format the data into a readable string
    if (is_array($something) || is_object($something)) {
        // print_r output can contain raw HTML if values contain HTML tags
        $output = print_r($something, true); 
    } else {
        $output = (string)$something;
    }
    // 3. Escape the entire output string before putting it in the DOM
    $escapedOutput = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    // 4. Wrap it in <pre> tags so the print_r formatting is preserved
    print("<pre>" . $escapedOutput . "</pre><br/>");
    */
//}

function sessionValueOrDefaultInt($sessValName, $default) {
    $ret =  (isset($_SESSION[$sessValName]) && $_SESSION[$sessValName] !== "") ? intval($_SESSION[$sessValName]) : $default;
    // futureLog("In sessionValueOrDefaultInt() for [" . $cookieName . "] (session), returning ". $ret);
    return $ret;
}

function encodedSessionValueOrDefaultJson($sessValName, $default) {
    if (isset($_SESSION[$sessValName]) && $_SESSION[$sessValName] !== "") {
        $val = $_SESSION[$sessValName];
        return is_string($val) ? $val : json_encode($val);
    } else {
        return $default;
    }
}

// Global Variables
$gEncodedSessionPredictions = encodedSessionValueOrDefaultJson("predictions", json_encode([]));
//futureLog("gEncodedSessionPredictions after retrieval: " . json_encode($gEncodedSessionPredictions));
$gMaxScore = (isset($_POST['MAXSCORE']) && $_POST['MAXSCORE'] !== "") ? intval($_POST['MAXSCORE']) : sessionValueOrDefaultInt("gMaxScore", DEFAULT_POINTS);
$gNumScores = (isset($_POST['NUMSCORE']) && $_POST['NUMSCORE'] !== "") ? intval($_POST['NUMSCORE']) : sessionValueOrDefaultInt("gNumScores", DEFAULT_SCORES);
$gColorArray = createGlobalColorArray();
$gRandomizeColors = isset($_POST[CHECK_RANDOMIZECOLORS]);
$randomColors = $gRandomizeColors ? getShuffledColorArray() : array(array(), array());
$gRandomBackgroundColors = array_keys($randomColors[0]);
$gRandomTextColors = array_keys($randomColors[1]);
$oldPost = $_POST;

///////////////
// Utilities //
///////////////

function table($x): string
{
    return "<table class='pts-table'>$x</table>";
}
function td($x): string
{
    return "<td>$x</td>";
}
function tdwh($x): string
{
    return "<td class='cell-wh'>$x</td>";
 }
function tr($x): string
{
    return "<tr>$x</tr>";
}
function b($x): string
{
    return "<strong>$x</strong>";
}
function u($x): string
{
    return "<span class='underline'>$x</span>";
}
function tdb($x): string
{
    return td(b($x));
}
function pre($x): string
{
    return "<pre style='margin:0'>$x</pre>";
}

$submit = $_POST['submit'] ?? "";
$selection = ($submit == "") ? "" : htmlspecialchars($submit);

class Score {
    public $us;
    public $them;
    public $delta;

    function __construct($us, $them) {
        $this->us = intval($us);
        $this->them = intval($them);
        $this->delta = abs($this->us - $this->them);
    }

//    function asString(): string
//    {
//        return "us is [$this->us], them is [$this->them], delta is [$this->delta]";
//    }

    function weWin(): bool
    { return $this->us > $this->them; }

    function weLose(): bool
    { return $this->us < $this->them; }

    function weTie(): bool
    { return $this->us == $this->them; }
}

class Evaluation {
    public $userScore;
    public $testScore;
    public $correctWinner;
    public $sumDeltas;       // Test 1
    public $sumDeltaSquares; // Test 2
    public $deltaDelta;      // Test 3
    public $deltaUs;         // Test 4

    function __construct($userScore, $testScore) {
        $this->userScore = $userScore;
        $this->testScore = $testScore;

        $this->correctWinner = ($userScore->weWin() && $testScore->weWin()) ||
                               ($userScore->weLose() && $testScore->weLose()) ||
                               ($userScore->weTie() && $testScore->weTie());

        $this->sumDeltas = abs($userScore->us - $testScore->us) +
                           abs($userScore->them - $testScore->them) +
                           abs($userScore->delta - $testScore->delta);

        $this->sumDeltaSquares = pow(($userScore->us - $testScore->us), 2) +
                                 pow(($userScore->them - $testScore->them), 2) +
                                 pow(($userScore->delta - $testScore->delta), 2);

        $this->deltaDelta = abs($userScore->delta - $testScore->delta);
        $this->deltaUs = abs($userScore->us - $testScore->us);
    }

    function isSameEval($eval): bool
    {
        return $this->correctWinner == $eval->correctWinner &&
               $this->sumDeltas == $eval->sumDeltas &&
               $this->sumDeltaSquares == $eval->sumDeltaSquares &&
               $this->deltaDelta == $eval->deltaDelta &&
               $this->deltaUs == $eval->deltaUs;
    }

    function isCloserThan($eval): bool
    {
        if ($this->correctWinner && !$eval->correctWinner) return true;
        if (!$this->correctWinner && $eval->correctWinner) return false;
        
        if ($this->sumDeltas != $eval->sumDeltas) return $this->sumDeltas < $eval->sumDeltas;
        if ($this->sumDeltaSquares != $eval->sumDeltaSquares) return $this->sumDeltaSquares < $eval->sumDeltaSquares;
        if ($this->deltaDelta != $eval->deltaDelta) return $this->deltaDelta < $eval->deltaDelta;
        if ($this->deltaUs != $eval->deltaUs) return $this->deltaUs < $eval->deltaUs;
        
        return false;
    }
}

class Prediction {
    public $id, $name, $us, $them, $bgCol, $textCol, $score;

    function __construct($aId, $aName, $aUs, $aThem, $aBgCol, $aTextCol) {
        $this->id = $aId;
        $this->name = $aName;
        $this->us = $aUs;
        $this->them = $aThem;
        $this->bgCol = ($aBgCol == "") ? "#FFFFFF" : $aBgCol;
        $this->textCol = ($aTextCol == "") ? "#000000" : $aTextCol;
        $this->score = new Score($aUs, $aThem);
    }

    function exactMatch($score): bool
    {
        return $this->us == $score->us && $this->them == $score->them;
    }

//    function tdTie(): string
//    {
//        return tdwh("TIE");
//    }

    function td($isExact): string
    {
        $myBgCol = $isExact ? $this->textCol : $this->bgCol;
        $myTextCol = $isExact ? $this->bgCol : $this->textCol;

        $style = "background-color: " . htmlspecialchars($myBgCol, ENT_QUOTES, 'UTF-8') . ";";
        $textColor = htmlspecialchars($myTextCol, ENT_QUOTES, 'UTF-8');
        return "<td class='cell-wh' style='" . $style . "'>"
             . "<span style='color: " . $textColor . ";'>"
             . ($isExact ? "<strong>" : "") . htmlspecialchars($this->name) . ($isExact ? "</strong>" : "")
             . "</span></td>";
    }

    function fromString(): string
    {
        global $gColorArray, $gRandomizeColors, $gRandomBackgroundColors, $gRandomTextColors;
//        print("<br/>Background: ");
//        foreach($gRandomBackgroundColors as $key => $value) { print("(" . $key . ", " . $value . "), ");}
//        print("<br/>Text: ");
//        foreach($gRandomTextColors as $key => $value) { print("(" . $key . ", " . $value . "), ");}

        $i = $this->id - 1;
//        print("<br/>Before: bg: ". $this->bgCol . ", text: " . $this->textCol);
        $this->bgCol = ($gRandomizeColors && (count($gRandomBackgroundColors) > $i)) ? $gRandomBackgroundColors[$i] : $this->bgCol;
        $this->textCol = ($gRandomizeColors && (count($gRandomTextColors) > $i)) ? $gRandomTextColors[$i] : $this->textCol;
//        print("<br/>After: bg: ". $this->bgCol . ", text: " . $this->textCol);
        return td($this->id)
             . td("<input type=\"text\" name=\"playerName$i\" value=\"".htmlspecialchars($this->name)."\" size=\"10\" maxlength=\"10\"/>")
             . td("<input type=\"text\" name=\"us$i\" value=\"$this->us\"/>")
             . td("<input type=\"text\" name=\"them$i\" value=\"$this->them\"/>")
             . td(getColorDDLB("bgDDLB$i", $this->bgCol, $gColorArray))
             . td(getColorDDLB("textDDLB$i", $this->textCol, $gColorArray));
    }
}

function constructPredictionFromPost($i): Prediction
{
    $id = $i + 1;
    $name = $_POST["playerName$i"] ?? "";
    $bg = $_POST["bgDDLB$i"] ?? "";
    $us = $_POST["us$i"] ?? 0;
    $them = $_POST["them$i"] ?? 0;
    $txt = $_POST["textDDLB$i"] ?? "";

    return new Prediction($id, $name, $us, $them, $bg, $txt);
}

function constructPredictionFromCookie($decodedPrediction, $i): Prediction
{
    $id = $i + 1;
    $name = $decodedPrediction["name"] ?? "";
    $bg = $decodedPrediction["bgCol"] ?? "";
    $us = $decodedPrediction["us"] ?? 0;
    $them = $decodedPrediction["them"] ?? 0;
    $txt = $decodedPrediction["textCol"] ?? "";

    return new Prediction($id, $name, $us, $them, $bg, $txt);
}

function constructPredictions(): array
{
    global $gNumScores, $gEncodedSessionPredictions;
    futureLog("constructPredictions() gNumScores is " . $gNumScores);
    $predictionRows = "";
    $predictions = [];

    $usePost = !empty($_POST);
    futureLog("In constructPredictions(),$gEncodedSessionPredictions is " . ($gEncodedSessionPredictions ?? "not set"));

    $decodedCookiePredictions = json_decode($gEncodedSessionPredictions, true);
    $useCookie = !$usePost && is_array($decodedCookiePredictions) && count($decodedCookiePredictions) > 0;
    futureLog("In constructPredictions(), usePost is " . ($usePost ? "true" : "false"));
    futureLog("In constructPredictions(), useCookie is " . ($useCookie ? "true" : "false") . ", is_array($gEncodedSessionPredictions) is " . (is_array($decodedCookiePredictions) ? "true" : "false") . ", count(decodedCookiePredictions) is " . (is_array($decodedCookiePredictions) ? count($decodedCookiePredictions) : "N/A"));

    for ($i = 0; $i < $gNumScores; $i++) {
        $prediction = $useCookie ? constructPredictionFromCookie($decodedCookiePredictions[$i], $i) : constructPredictionFromPost($i);
        $predictionRows .= tr($prediction->fromString());
        $predictions[$i] = $prediction;
    }
    return [$predictionRows, $predictions];
}

function validate(): array
{
    global $gNumScores, $gMaxScore;
    $errors = [];
    if (($gNumScores > MAX_SCORES) || ($gNumScores < 0)) {
        $errors[] = "<h2>The number of scores to include in the contest must be greater than or equal to 0"
                . " and less than or equal to " . MAX_SCORES . "!  We have [" . $gNumScores . "]!\n<br>";
        $gNumScores = DEFAULT_SCORES;
    }
    if (($gMaxScore > MAX_POINTS) || ($gMaxScore < 1)) {
        $errors[] = "<h2>The highest point total in this contest must be greater than 0 and less than or equal to [" . MAX_POINTS
          . "]!  We have [" . $gMaxScore
        . "]!\n<br>";
        $gMaxScore = DEFAULT_POINTS;
    }
    return $errors;
}

function horizRowPrefix($count): string
{
    return tdwh(b(pre($count)));
}

function finalCountRow(): string
{
    global $gMaxScore;
    $ret = td("");
    for ($i = 0; $i <= $gMaxScore; $i++) { $ret .= tdwh(b(pre($i))); }
    return tr($ret . td(""));
}

function getClosest($testScore, $predictions): string
{
    $closestPrediction = new Prediction(0, "-", 0, 0, "#FFFFFF", "black");
   
    $closestEvalSoFar = null;
    $isTieSituation = false;

    if (!empty($predictions)) {
        foreach ($predictions as $predict) {
            $eval = new Evaluation($predict->score, $testScore);
            if ($eval->correctWinner) {
                if ($closestEvalSoFar === null) {
                    $closestPrediction = $predict;
                    $closestEvalSoFar = $eval;
                } else {
                    if ($eval->isCloserThan($closestEvalSoFar)) {
                        $closestEvalSoFar = $eval;
                        $closestPrediction = $predict;
                        $isTieSituation = false;
                    } else if ($eval->isSameEval($closestEvalSoFar)) {
                        $isTieSituation = true;
                    }
                }
            }
        }
    }

    if (!$closestPrediction) return tdwh("&nbsp;");
    $isExactMatch = $closestPrediction->exactMatch($testScore);
    return $isTieSituation ? tdwh("TIE") : $closestPrediction->td($isExactMatch);
}

function graphicalDisplay($predictions) {
    global $gMaxScore;
    $allRows = finalCountRow();
    for ($scoreUs = $gMaxScore; $scoreUs >= 0; --$scoreUs) {
        $curRow = horizRowPrefix($scoreUs);
        for ($scoreThem = 0; $scoreThem <= $gMaxScore; $scoreThem++) {
            $testScore = new Score($scoreUs, $scoreThem);
            if ($testScore->weTie()) {
                $curRow .= tdwh("XX");
            } else {
                $curRow .= getClosest($testScore, $predictions);
            }
        }
        $curRow .= horizRowPrefix($scoreUs);
        $allRows .= tr($curRow) . "\n";
    }
    $allRows .= finalCountRow();
    print(table($allRows) . "\n");
}

function mySetSessionValue($name, $value) {
    // Store values in session instead of cookies
    $_SESSION[$name] = $value;
}

// Control Table
$errors = validate();
$prediction_array = constructPredictions();
$predictionRows = $prediction_array[0];
$predictions = $prediction_array[1];

$controlTable = table(
    tr(td("<input type='submit' name='submit' value='".OP_SETNUMSCORES."'>") . td("<input type='text' name='NUMSCORE' value='$gNumScores'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_SETMAXSCORE."'>") . td("<input type='text' name='MAXSCORE' value='$gMaxScore'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_VALIDATE."'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_DOIT."'>"
            . "<input type='checkbox' id='" . CHECK_RANDOMIZECOLORS . "' name='" . CHECK_RANDOMIZECOLORS . "' value='1'><label for='" . CHECK_RANDOMIZECOLORS . "'>" . LABEL_RANDOMIZE_COLORS. "</label>"))
);

// Important: Set cookies before any output is sent to the browser.
// If you try to set cookies after any HTML output (like echo or print statements), it will cause an error,
// or at least unexpected functionality.
mySetSessionValue("gMaxScore", $gMaxScore);
mySetSessionValue("gNumScores", $gNumScores);
mySetSessionValue("predictions", json_encode($predictions));
outputFutureLogs();


?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Graphical Representation of Pick the Score</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="body" style="--cell-width: <?php echo CELL_WIDTH; ?>px; --cell-height: <?php echo CELL_HEIGHT; ?>px;">
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">

<?php
foreach ($errors as $error) {
    print($error);
}

$headerRow = tdb("ID") . tdb("Name") . tdb("Score: Us") . tdb("Score: Them") . tdb("BG Color") . tdb("Text Color");

print($controlTable);
print(table(tr($headerRow) . $predictionRows));
graphicalDisplay($predictions);

// More debugging stuff:
//
/*
 print_rSomething("Predictions", json_encode($predictions));
 print_rSomething("_COOKIE", $_COOKIE);
 print_rSomething("Current Headers", getallheaders());
 print_rSomething("Cookie Predictions", $gEncodedSessionPredictions);
*/
?>
</form>
</body>
</html>