<html>
<head>
<title>Graphical Representation of Pick the Score</title>
</head>
<body>

<?php
// Note: Ensure 'colours.inc' exists in your directory or comment this out
if (file_exists('colours.inc')) {
    require 'colours.inc';
} else {
    // Fallback if file is missing to prevent crash
    function createGlobalColourArray() { return ['#FFFFFF' => 'White', '#000000' => 'Black', '#FF0000' => 'Red']; }
    function getColourDDLB($name, $selected, $array) { return "<select name='$name'><option value='$selected'>$selected</option></select>"; }
}

define('CELL_WIDTH', 40);
define('CELL_HEIGHT', 40);
define('MAX_SCORES', 40);
define('MAX_POINTS', 77);
define('DEFAULT_POINTS', 34);
define('DEFAULT_SCORES', 0);
define('OP_SETNUMSCORES', "Set Number of Scores to Enter (max 40)");
define('OP_SETMAXSCORE',  "Set Maximum Score to Compute (max 77)");
define('OP_VALIDATE',     "Validate Input");
define('OP_DOIT',         "Submit");

// Global Variables
$gMaxScore = (isset($_POST['MAXSCORE']) && $_POST['MAXSCORE'] !== "") ? intval($_POST['MAXSCORE']) : DEFAULT_POINTS;
$gNumScores = (isset($_POST['NUMSCORE']) && $_POST['NUMSCORE'] !== "") ? intval($_POST['NUMSCORE']) : DEFAULT_SCORES;
$gValErrors = 0;
$gColourArray = createGlobalColourArray();

///////////////
// Utilities //
///////////////

/**
 * Debugging function.  Comment out the print statement to turn off debugging.
 */
function d($m) {
 //   print("DBG[$m]<br>\n");
}

function table($x) {
    return "<table border=\"1\" cellpadding=\"1\" cellspacing=\"1\">$x</table>";
}
function td($x) {
    return "<td>$x</td>";
}
function tdwh($x) {
    return "<td align=\"center\" width=\"" . CELL_WIDTH . "\" height=\"" . CELL_HEIGHT . "\">$x</td>";
 }
function tr($x) {
    return "<tr>$x</tr>";
}
function b($x) {
    return "<b>$x</b>";
}
function u($x) {
    return "<u>$x</u>";
}
function tdb($x) {
    return td(b($x));
}
function pre($x) {
    return "<pre style='margin:0'>$x</pre>";
}
?>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">

<?php
$submit = isset($_POST['submit']) ? $_POST['submit'] : "";

if ($submit != "") {
    print("You selected [" . htmlspecialchars($submit) . "]<br>\n");
}

class Score {
    public $us;
    public $them;
    public $delta;

    function __construct($us, $them) {
        $this->us = intval($us);
        $this->them = intval($them);
        $this->delta = abs($this->us - $this->them);
    }

    function asString() {
        return "us is [{$this->us}], them is [{$this->them}], delta is [{$this->delta}]";
    }
    function weWin() { return $this->us > $this->them; }
    function weLose() { return $this->us < $this->them; }
    function weTie() { return $this->us == $this->them; }
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

    function isSameEval($eval) {
        return $this->correctWinner == $eval->correctWinner &&
               $this->sumDeltas == $eval->sumDeltas &&
               $this->sumDeltaSquares == $eval->sumDeltaSquares &&
               $this->deltaDelta == $eval->deltaDelta &&
               $this->deltaUs == $eval->deltaUs;
    }

    function isCloserThan($eval) {
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

    function exactMatch($score) {
        return $this->us == $score->us && $this->them == $score->them;
    }

    function tdTie() {
        return tdwh("TIE");
    }

    function td($isExact) {
        $myBgCol = $isExact ? $this->textCol : $this->bgCol;
        $myTextCol = $isExact ? $this->bgCol : $this->textCol;

        return "<td align=\"center\" width=\"" . CELL_WIDTH . "\" height=\"" . CELL_HEIGHT . "\" bgcolor=\"" . $myBgCol . "\">"
             . "<font color=\"" . $myTextCol . "\">"
             . ($isExact ? "<b>" : "") . htmlspecialchars($this->name) . ($isExact ? "</b>" : "")
             . "</font></td>";
    }

    function fromString() {
        global $gColourArray;
        $i = $this->id - 1;
        return td($this->id)
             . td("<input type=\"text\" name=\"playerName$i\" value=\"".htmlspecialchars($this->name)."\" size=\"10\"/>")
             . td("<input type=\"text\" name=\"us$i\" value=\"$this->us\"/>")
             . td("<input type=\"text\" name=\"them$i\" value=\"$this->them\"/>")
             . td(getColourDDLB("bgDDLB$i", $this->bgCol, $gColourArray))
             . td(getColourDDLB("textDDLB$i", $this->textCol, $gColourArray));
    }
}

function constructPredictions() {
    global $gNumScores;
    $headerRow = tdb("ID") . tdb("Name") . tdb("Score: Us")
        . tdb("Score: Them") . tdb("BG Color") . tdb("Text Color");
    $predictionRows = "";
    $predictions = [];

    for ($i = 0; $i < $gNumScores; $i++) {
        $id = $i + 1;
        $name = $_POST["playerName$i"] ?? "";
        $bg = $_POST["bgDDLB$i"] ?? "";
        $us = $_POST["us$i"] ?? 0;
        $them = $_POST["them$i"] ?? 0;
        $txt = $_POST["textDDLB$i"] ?? "";

        $prediction = new Prediction($id, $name, $us, $them, $bg, $txt);
        $predictionRows .= tr($prediction->fromString());
        $predictions[$i] = $prediction;
    }
    print(table(tr($headerRow) . $predictionRows));
    return $predictions;
}

function validate() {
    global $gNumScores, $gMaxScore, $gValErrors;
    if (($gNumScores > MAX_SCORES) || ($gNumScores < 0)) {
            print("<h2>The number of scores to include in the contest must be greater than or equal to 0"
      . " and less than or equal to " . MAX_SCORES . "!  We have [" . $gNumScores
      . "]!\n<br>");
    $gNumScores = DEFAULT_SCORES;
    $gValErrors++;
    }
    if (($gMaxScore > MAX_POINTS) || ($gMaxScore < 1)) {
    print("<h2>The highest point total in this contest must be greater than 0 and less than or equal to [" . MAX_POINTS
      . "]!  We have [" . $gMaxScore
      . "]!\n<br>");
    $gMaxScore = DEFAULT_POINTS;
    $gValErrors++;
  }

    return $gValErrors;
}

function horizRowPrefix($count) {
    return tdwh(b(pre($count)));
}

function finalCountRow() {
    global $gMaxScore;
    $ret = td("");
    for ($i = 0; $i <= $gMaxScore; $i++) { $ret .= tdwh(b(pre($i))); }
    return tr($ret . td(""));
}

function getClosest($testScore, $predictions) {
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

// Control Table
$controlTable = table(
    tr(td("<input type='submit' name='submit' value='".OP_SETNUMSCORES."'>") . td("<input type='text' name='NUMSCORE' value='$gNumScores'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_SETMAXSCORE."'>") . td("<input type='text' name='MAXSCORE' value='$gMaxScore'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_VALIDATE."'>")) .
    tr(td("<input type='submit' name='submit' value='".OP_DOIT."'>"))
);
print($controlTable);

validate();
$predictions = constructPredictions();
graphicalDisplay($predictions);

?>
</form>
</body>
</html>