<html>
<head>
<title>Graphical Representation of Pick the Score</title>
</head>
<body>

<?php
//print("Request method is [" . $_SERVER['REQUEST_METHOD'] . "]");
require 'colours.inc';


define('CELL_WIDTH', 40);
define('CELL_HEIGHT', 40);
define('MAX_SCORES', 40);
define('MAX_POINTS', 77);
define('DEFAULT_POINTS', 34);
define('DEFAULT_SCORES', 0);
define('OP_SETNUMSCORES',    "Set Number of Scores to Enter (max 40)");
define('OP_SETMAXSCORE',     "Set Maximum Score to Compute (max 77)");
define('OP_VALIDATE',        "Validate Input");
define('OP_DOIT',            "Submit");

// Global Variables:
$gMaxScore  = array_key_exists('MAXSCORE', $_POST) ? $_POST['MAXSCORE'] : DEFAULT_POINTS;
$gMaxScore = ($gMaxScore == "") ? DEFAULT_POINTS : $gMaxScore;
$gNumScores = array_key_exists('NUMSCORE', $_POST) ? $_POST['NUMSCORE'] : DEFAULT_SCORES;
$gNumScores = ($gNumScores == "") ? DEFAULT_SCORES : $gNumScores;
$gValErrors = 0;
$gColourArray = createGlobalColourArray();


///////////////
// Utilities //
///////////////

/**
 * Debugging function.  Comment out the print statement to turn off debugging.
 */
function d($m) {
 //**/  print("DBG[$m]<br>\n");
}

//**/d("_POST is [" . print_r($_POST) . "]");
//**/d("gNumScores is [" . $gNumScores . "]");
//**/d("gMaxScore  is [" . $gMaxScore . "]");

function table($x) {
  return "<table border=\"1\" cellpadding=\"1\" cellspacing=\"1\">$x</table>";
}
function td($x) {
  // return "<td ALIGN=Center>$x</td>";
  return "<td>$x</td>";
}

// td with width and height
function tdwh($x) {
  // return "<td ALIGN=Center>$x</td>";
  return "<td ALIGN=Center WIDTH=" . CELL_WIDTH . " HEIGHT=" . CELL_HEIGHT . ">$x</td>";
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
  return "<pre>$x</pre>";
}
?>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
<?php

$submit = array_key_exists('submit', $_POST) ? $_POST['submit'] : "";

if ( $submit != "" ) {
  print("You selected [" . $submit. "]<br>\n");
}

class Score {
  var $us;
  var $them;
  var $delta; // Absolute value of difference;

  // Constructor
  function __construct($us, $them) {
    //* */d("Score constructor ". $us . ", " . $them);
    $this->us    = intval($us);
    $this->them  = intval($them);
    //* */d("Score constructor ". $this->us . ", " . $this->them);
    
    $this->delta = abs($this->us - $this->them);
  }

  function asString() {
    return "us is [" . $this->us
      . "], them is [" . $this->them
      . "], delta is [" . $this->delta
      . "]";
  }

  function weWin() {
    return $this->us > $this->them;
  }

  function weLose() {
    return $this->us < $this->them;
  }

  function weTie() {
    return $this->us == $this->them;
  }

} // class Score

class Evaluation {

  var $userScore;
  var $testScore;
  var $correctWinner;
  var $sumDeltas;       // Test 1
  var $sumDeltaSquares; // Test 2
  var $deltaDelta;      // Test 3
  var $deltaUs;         // Test 4

  // Constructor
  function __construct($userScore, $testScore) {
    $this->userScore = $userScore;
    $this->testScore = $testScore;

    // Check for correct winner:
    $this->correctWinner = ( $userScore->weWin()  && $testScore->weWin() ||
                 $userScore->weLose() && $testScore->weLose() );

    $this->sumDeltas =
      abs($userScore->us    - $testScore->us) +
      abs($userScore->them  - $testScore->them) +
      abs($userScore->delta - $testScore->delta);

    $this->sumDeltaSquares =
      pow(($userScore->us    - $testScore->us),    2) +
      pow(($userScore->them  - $testScore->them),  2) +
      pow(($userScore->delta - $testScore->delta), 2);

    $this->deltaDelta = abs($userScore->delta - $testScore->delta);

    $this->deltaUs    = abs($userScore->us - $testScore->us);
  } // Evaluation::Evaluation();


  function isSameEval($eval) {
    return $this->correctWinner == $eval->correctWinner &&
      $this->sumDeltas       == $eval->sumDeltas &&
      $this->sumDeltaSquares == $eval->sumDeltaSquares &&
      $this->deltaDelta      == $eval->deltaDelta &&
      $this->deltaUs         == $eval->deltaUs;
  }

  function isCloserThan($eval) {
    /*
    d("Eval::isCloserThan(): this->userScore is [" . $this->userScore->asString()
      . "], this->testScore is [" . $this->testScore->asString()
      . "], eval->userScore is [" . $eval->userScore->asString()
      . "], eval->testScore is [" . $eval->testScore->asString()
      . "]");
    */

    if ( $this->correctWinner && !$eval->correctWinner ) {
      //**/d("Eval::isCloserThan(): Only this is correctWinner");
      return true;
    }
    // else...
    //**/d("Eval::isCloserThan(): sumDeltas: [" .  $this->sumDeltas . "," . $eval->sumDeltas . "]");
    if ( $this->sumDeltas != $eval->sumDeltas ) {
      return $this->sumDeltas < $eval->sumDeltas;
    }
    // else they're equal...
    //**/d("Eval::isCloserThan(): sumDeltasSquares: [" .  $this->sumDeltaSquares . "," . $eval->sumDeltaSquares . "]");
    if ( $this->sumDeltaSquares != $eval->sumDeltaSquares ) {
      return $this->sumDeltaSquares < $eval->sumDeltaSquares;
    }
    // else...
    //**/d("Eval::isCloserThan(): deltaDelta: [" .  $this->deltaDelta . "," . $eval->deltaDelta . "]");
    if ( $this->deltaDelta != $eval->deltaDelta ) {
      return $this->deltaDelta < $eval->deltaDelta;
    }
    // else...
    //**/d("Eval::isCloserThan(): deltaUs: [" .  $this->deltaUs . "," . $eval->deltaUs . "]");
    if ( $this->deltaUs != $eval->deltaUs ) {
      return $this->deltaUs < $eval->deltaUs;
    }
    // else...
    return false;

  } // Evaluation::isCloserThan;

} // class Evaluation


class Prediction
{
  var $id;
  var $name;    // String.  That is, the initials of the picker.
  var $us;      // Predicted score for us.
  var $them;    // Predicted score for them.
  var $bgCol;   // Background Colour to use.
  var $textCol; // Text Colour to use.
  var $score;   // (us, them)

  // Constructors:

  // Non-Null Constructor:
  function __construct($aId, $aName, $aUs, $aThem, $aBgCol, $aTextCol)
  {
    $this->id      = $aId;
    $this->name    = $aName;
    $this->us      = $aUs;
    $this->them    = $aThem;

    // Set defaults if NULL is passed
    $this->bgCol   = ($aBgCol == "") ? "#FFFFFF" : $aBgCol;
    $this->textCol = ($aTextCol == "") ? "#000000" : $aTextCol;

    $this->score = new Score($aUs, $aThem);
    /*d("Prediction(): id is [" . $this->id
      . "], name is [" . $this->name
      . "], us is [" . $this->us
      . "], them is [" . $this->them
      . "], bgCol is [" . $this->bgCol
      . "], textCol is [" . $this->textCol
      . "]");
    */
  } // Prediction::Prediction

  function exactMatch($score) {
    return $this->us == $score->us && $this->them == $score->them;
  }

  function tdTie() {
    return tdwh("TIE");
  }

  function td($isExact) {
    // If it's exact, we use bold reverse colour
    //**/d("Prediction::td(): isExact is [" . $isExact . "]; or is [" . ($isExact ? "true" : "false") . "]");

    // Swap them only if it's an exact match
    $myBgCol   = $isExact ? $this->textCol : $this->bgCol;
    $myTextCol = $isExact ? $this->bgCol   : $this->textCol ;

    /*
     d("Prediction::td(): isExact is [" . $isExact . "]; or is [" . ($isExact ? "true" : "false")
      . "], this->bgCol is [" . $this->bgCol
      . "], myBgCol is [" . $myBgCol
      . "], this->textCol is [" . $this->textCol
      . "], myTextCol is [" . $myTextCol
      . "]");
    */

    $ret = "<td ALIGN=Center WIDTH=" . CELL_WIDTH . " HEIGHT=" . CELL_HEIGHT . " bgcolor=\"" . $myBgCol . "\">"
      . "<font color=\"" . $myTextCol . "\">"
      . ($isExact ? "<b>" : "")
      . $this->name
      . ($isExact ? "</b>" : "")
      . "</font>"
      . "</td>";

    return $ret;
  }

  function fromString() {
    //**/d("Prediction:fromString()");

    global $gColourArray;
    //**/d(fromString(): count($gColourArray) is [" . count($gColourArray) . "]");

    $i = $this->id - 1;

    $rowString = td($this->id)
      . td("  <input type=\"text\" "
       . " name=\"playerName$i\" "
       . " value=\"$this->name\"/> ")
      . td("  <input type=\"text\" "
       . " name=\"us$i\" "
       . " value=\"$this->us\"/> ")
      . td("  <input type=\"text\" "
       . " name=\"them$i\" "
       . " value=\"$this->them\"/> ")
      . td(getColourDDLB("bgDDLB" . $i, $this->bgCol, $gColourArray))
      . td(getColourDDLB("textDDLB" . $i, $this->textCol, $gColourArray))
      . "\n";

    return $rowString;

  } // Prediction:fromString()

} // class Prediction

function makeRows() {
}

function constructPredictions()
{
  global $gNumScores;
  //**/d("constructPredictions(): gNumScores is [" . $gNumScores . "]");
  $headerRow = tdb("ID") . tdb("Name") . tdb("Score: Us")
    . tdb("Score: Them") . tdb("BG Colour") . tdb("Text Colour");
  $predictionRows = "";
  $predictions = [];

  //**/d("constructPredictions(): about to loop through scores, gNumScores is [" . $gNumScores . "]");
  for ( $i = 0; $i < $gNumScores; $i++ ) {
    //**/d("constructPredictions(): i is [" . $i . "]");
    $id = $i + 1;
    $playerNamei = array_key_exists("playerName$i", $_POST) ? $_POST["playerName$i"] : "";
    $bgDDLBi = array_key_exists("bgDDLB$i", $_POST) ? $_POST["bgDDLB$i"] : "";
    $usi = array_key_exists("us$i", $_POST) ? $_POST["us$i"] : "";
    $themi = array_key_exists("them$i", $_POST) ? $_POST["them$i"] : "";
    $textDDLBi = array_key_exists("textDDLB$i", $_POST) ? $_POST["textDDLB$i"] : "";
    
    $prediction = new Prediction($id,
                 $playerNamei,
                 $usi,
                 $themi,
                 $bgDDLBi,
                 $textDDLBi);
    $predictionRows .= tr($prediction->fromString());
    $predictions[$i] = $prediction;
  }

  $predictionTable = table(tr($headerRow) . $predictionRows);

  print($predictionTable);
  return $predictions;
}

$numScore = array_key_exists('NUMSCORE', $_POST) ? $_POST['NUMSCORE'] : DEFAULT_SCORES;
$maxScore = array_key_exists('MAXSCORE', $_POST) ? $_POST['MAXSCORE'] : DEFAULT_POINTS;
$controlTable =
  table(tr(td("<INPUT TYPE=SUBMIT NAME=\"submit\" VALUE=\"" . OP_SETNUMSCORES . "\">") .
       td("<INPUT TYPE=TEXT   NAME=\"NUMSCORE\" value=\"" . $numScore . "\">")) .
    tr(td("<INPUT TYPE=SUBMIT NAME=\"submit\" VALUE=\"" . OP_SETMAXSCORE . "\">") .
       td("<INPUT TYPE=TEXT   NAME=\"MAXSCORE\" value=\"" . $maxScore . "\">")) .
    tr(td("<INPUT TYPE=SUBMIT NAME=\"submit\" VALUE=\"" . OP_VALIDATE . "\">")) .
    tr(td("<INPUT TYPE=SUBMIT NAME=\"submit\" VALUE=\"" . OP_DOIT . "\">"))
    );

print($controlTable);

function setNumScores() {
  //**/d("ENTER setNumScores()");
  //  d("numScores is [" . stripslashes(htmlspecialchars($_POST['NUMSCORE'])) . "]");
}

function setMaxScore() {
  //**/d("ENTER setMaxScores()"); 
  //**/d("numScores is [" . stripslashes(htmlspecialchars($_POST['MAXSCORE'])) . "]");
}

function validate() {
  global $gNumScores;
  global $gMaxScore;
  global $gValErrors;

  if ( $gNumScores > MAX_SCORES ) {
    print("<h2>The number of scores to include in the contest must be less than or equal to [" . MAX_SCORES
      . "]!  We have [" . $gNumScores
      . "]!\n<br>");
    $gNumScores = DEFAULT_SCORES;
    $gValErrors++;
  }

  if ( $gNumScores < 0 ) {
    print("<h2>The number of scores to include in the contest must be greater than or equal to [0"
      . "]!  We have [" . $gNumScores
      . "]!\n<br>");
    $gNumScores = DEFAULT_SCORES;
    $gValErrors++;
  }

  if ( $gMaxScore > MAX_POINTS ) {
    print("<h2>The highest point total in this contest must be less than or equal to [" . MAX_POINTS
      . "]!  We have [" . $gMaxScore
      . "]!\n<br>");
    $gMaxScore = DEFAULT_POINTS;
    $gValErrors++;
  }

  if ( $gMaxScore < 1) {
    print("<h2>The lowest point total in this contest must be greater than or equal to [1"
      . "]!  We have [" . $gMaxScore
      . "]!\n<br>");
    $gMaxScore = DEFAULT_POINTS;
    $gValErrors++;
  }

  // Loop through the scores

  return $gValErrors;
} // validate()

function horizRowPrefix($count)
{
  //**/ d("horizRowPrefix($count)");
  return tdwh(b(pre($count)));
}

function finalCountRow()
{
  global $gMaxScore;
  $ret = td("");
  for ( $i = 0; $i <= $gMaxScore; $i++ ) {
    $ret .= tdwh(b(pre($i)));
  }
  return $ret;
}

//
// getClosest()
//
// Returns the name of the closest Prediction
//
function getClosest($testScore, $predictions) {
        // **/d("getClosest(): testScore is [" . $testScore->asString() . "]");
    $closestPrediction = new Prediction(0, "-", 0, 0, "#FFFFFF", "black");
    
    $candidateFound = false;
    $isTieSituation = false;
    $closestEvalSoFar = 0;

    if (!is_null($predictions)) {
        foreach ($predictions as $predict) {
            // **/d("getClosest(): predictedScore is [" . $predict->score->asString() . "]");
            $eval = new Evaluation($predict->score, $testScore);
            if ($eval->correctWinner) {
                if (! $candidateFound) {
                    // This is the first candidate.
                    $closestPrediction = $predict;
                    $candidateFound = true;
                    $closestEvalSoFar = $eval;
                    continue;
                }
                // else... (This isn't the first candidate)
                // Are we closer than the closestPrediction?
                if ($eval->isCloserThan($closestEvalSoFar)) {
                    $closestEvalSoFar = $eval;
                    $closestPrediction = $predict;
                    $isTieSituation = false;
                    // **/d("getClosest(): new closest is [" . $closestPrediction->score->asString() . "]");
                } else if ($eval->isSameEval($closestEvalSoFar)) {
                    // We've tied the closest Prediction
                    $isTieSituation = true;
                }
            } // foreach prediction
        }
  } // $predictions is not null
  $isExactMatch = $closestPrediction->exactMatch($testScore);
  return $isTieSituation ? $closestPrediction->tdTie() : $closestPrediction->td($isExactMatch);
}

function createCell($testScore, $predictions) {
  //**/d("ENTER createCell(): testScore is [" . $testScore->asString() . "]");
  $text = "";
  if ( $testScore->weTie() ) {
    //**/d("createCell(): weTie");
    $text = tdwh("XX");
  } else {
    //**/d("createCell(): weDoNotTie");
    $text = getClosest($testScore, $predictions);
  }
  /** 
  d("createCell(): text is [" . $text
    . "], us is [" . $us
    . "], them is [" . $them
    ,"]");
   */
  // For now, overwrite with an exact match only.
  /*
  foreach ( $predictions as $predict ) {
    if ( $predict->exactMatch($testScore) ) {
      $text = b($predict->name);
      break;
    }
  }
  */
  /*
  d("createCell(): text is [" . $text
    . "], us is [" . $us
    . "], them is [" . $them
    ,"]");
  */
  return $text;
} // createCell()

function graphicalDisplay($predictions) {
  //**/d("ENTER graphicalDisplay()");
  global $gMaxScore;
  //**/d("gMaxScore is [" . $gMaxScore . "]");

  ///////////////
    // Big Table //
    ///////////////
    $allRows = finalCountRow();
    for ( $scoreUs = $gMaxScore; $scoreUs >= 0; --$scoreUs ) {
      //**/d("scoreUs is [" . $scoreUs . "]");
      $curRow = horizRowPrefix($scoreUs);
      for ( $scoreThem = 0; $scoreThem <= $gMaxScore; $scoreThem++ ) {
      //**/d("scoreThem is [" . $scoreThem . "]");
        $testScore = new Score($scoreUs, $scoreThem);
        //**/d("testScore is [" . $testScore->asString() . "]");
        $curRow .= createCell($testScore, $predictions);
      }
      $curRow .= horizRowPrefix($scoreUs);

      //**/d("curRow is [" . $curRow . "]");
      $allRows .= tr($curRow) . "\n";
    }
    $allRows .= finalCountRow();
    print(table($allRows) . "\n");
} // graphicalDisplay()

function doIt() {
  //**/d("ENTER doIt()"); 
}
// Begin main()

// Command Loop
if ( $submit == OP_SETNUMSCORES ) {
  setNumScores();
 } else if ( $submit == OP_SETMAXSCORE ){
  setMaxScore();
 } else if ( $submit == OP_VALIDATE ) {
  validate();
 } else if ( $submit == OP_DOIT ) {
  doIt();
 } else {
  //print("Invalid command received [" . $_POST['submit'] . "]!");
 }

validate();

// Construct Prediction Table
$predictions = constructPredictions();
graphicalDisplay($predictions);


// End Command Loop

?>
</body>
</html>