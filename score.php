<?php
// Note: Ensure 'colours.inc' exists in your directory or comment this out
if (file_exists('colours.inc')) {
    require 'colours.inc';
} else {
    // Fallback if file is missing to prevent crash
    function createGlobalColourArray(): array
    { return ['#FFFFFF' => 'White', '#000000' => 'Black', '#FF0000' => 'Red']; }
    function getColourDDLB($name, $selected, &$unused): string
    { return "<select name='$name'><option value='$selected'>$selected</option></select>"; }
}

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
define('COOKIE_EXPIRY', time() + 3600 * 24 * 32); // 32 days from now, so more than 1 month

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
// They are designed to help debug issues with cookies, especially since cookies are set at the
// end of this script but we want to see their values throughout the script execution.
function console_log($data) {
    /*
    // Convert arrays or objects safely to a JS-readable format
    $js_code = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo "<script>console.log(" . $js_code . ");</script>";
    */
}

function print_rSomething($header, $something) {
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
}

function cookieValueOrDefaultInt($cookieName, $default) {
    $ret =  (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== "") ? intval($_COOKIE[$cookieName]) : $default;
    futureLog("In cookieValueOrDefaultInt() for [" . $cookieName . "], returning ". $ret);
    return $ret;
}

function encodedCookieValueOrDefaultJson($cookieName, $default) {
    $ret =  (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== "") ? json_decode($_COOKIE[$cookieName]) : $default;
//    futureLog("In encodedCookieValueOrDefaultJson() for [" . $cookieName . "], returning ". json_encode($ret));
//    if (is_array($ret)) {
//        futureLog("encodedCookieValueOrDefaultJson() returning array of length " . count($ret));
//        foreach ($ret as $prediction) {
//            futureLog("prediction: " . json_encode($prediction));
//        }
//    }
    return json_encode($ret);
}

// Global Variables
$gEncodedCookiePredictions = encodedCookieValueOrDefaultJson("predictions", json_encode([]));
futureLog("gEncodedCookiePredictions after retrieval: " . json_encode($gEncodedCookiePredictions));
$gMaxScore = (isset($_POST['MAXSCORE']) && $_POST['MAXSCORE'] !== "") ? intval($_POST['MAXSCORE']) : cookieValueOrDefaultInt("gMaxScore", DEFAULT_POINTS);
$gNumScores = (isset($_POST['NUMSCORE']) && $_POST['NUMSCORE'] !== "") ? intval($_POST['NUMSCORE']) : cookieValueOrDefaultInt("gNumScores", DEFAULT_SCORES);
$gColourArray = createGlobalColourArray();

///////////////
// Utilities //
///////////////

function table($x): string
{
    return "<table border=\"1\" cellpadding=\"1\" cellspacing=\"1\">$x</table>";
}
function td($x): string
{
    return "<td>$x</td>";
}
function tdwh($x): string
{
    return "<td align=\"center\" width=\"" . CELL_WIDTH . "\" height=\"" . CELL_HEIGHT . "\">$x</td>";
 }
function tr($x): string
{
    return "<tr>$x</tr>";
}
function b($x): string
{
    return "<b>$x</b>";
}
function u($x): string
{
    return "<u>$x</u>";
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

    function asString(): string
    {
        return "us is [$this->us], them is [$this->them], delta is [$this->delta]";
    }

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

    function tdTie(): string
    {
        return tdwh("TIE");
    }

    function td($isExact): string
    {
        $myBgCol = $isExact ? $this->textCol : $this->bgCol;
        $myTextCol = $isExact ? $this->bgCol : $this->textCol;

        return "<td align=\"center\" width=\"" . CELL_WIDTH . "\" height=\"" . CELL_HEIGHT . "\" bgcolor=\"" . $myBgCol . "\">"
             . "<font color=\"" . $myTextCol . "\">"
             . ($isExact ? "<b>" : "") . htmlspecialchars($this->name) . ($isExact ? "</b>" : "")
             . "</font></td>";
    }

    function fromString(): string
    {
        global $gColourArray;
        $i = $this->id - 1;
        return td($this->id)
             . td("<input type=\"text\" name=\"playerName$i\" value=\"".htmlspecialchars($this->name)."\" size=\"10\" maxlength=\"10\"/>")
             . td("<input type=\"text\" name=\"us$i\" value=\"$this->us\"/>")
             . td("<input type=\"text\" name=\"them$i\" value=\"$this->them\"/>")
             . td(getColourDDLB("bgDDLB$i", $this->bgCol, $gColourArray))
             . td(getColourDDLB("textDDLB$i", $this->textCol, $gColourArray));
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
    global $gNumScores, $gEncodedCookiePredictions;
    futureLog("constructPredictions() gNumScores is " . $gNumScores);
    $predictionRows = "";
    $predictions = [];

    $usePost = !empty($_POST);
    futureLog("In constructPredictions(),$gEncodedCookiePredictions is " . ($gEncodedCookiePredictions ?? "not set"));

    $decodedCookiePredictions = json_decode($gEncodedCookiePredictions, true);
    $useCookie = !$usePost && is_array($decodedCookiePredictions) && count($decodedCookiePredictions) > 0;
    futureLog("In constructPredictions(), usePost is " . ($usePost ? "true" : "false"));
    futureLog("In constructPredictions(), useCookie is " . ($useCookie ? "true" : "false") . ", is_array($gEncodedCookiePredictions) is " . (is_array($decodedCookiePredictions) ? "true" : "false") . ", count(decodedCookiePredictions) is " . (is_array($decodedCookiePredictions) ? count($decodedCookiePredictions) : "N/A"));

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

function mySetcookie($name, $value, $expiry = COOKIE_EXPIRY, $path = "/") {
    setcookie($name, $value, $expiry, $path);
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
    tr(td("<input type='submit' name='submit' value='".OP_DOIT."'>"))
);

// Important: Set cookies before any output is sent to the browser.
// If you try to set cookies after any HTML output (like echo or print statements), it will cause an error,
// or at least unexpected functionality.
mySetcookie("gMaxScore", $gMaxScore);
mySetcookie("gNumScores", $gNumScores);
mySetcookie("predictions", json_encode($predictions));
outputFutureLogs();


?>
<html>
<head>
<title>Graphical Representation of Pick the Score</title>
</head>
<body>
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
<?php

//print($selection);
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
 print_rSomething("Cookie Predictions", $gEncodedCookiePredictions);
*/
?>
</form>
</body>
</html>