# predict-the-score
Simple tool to find who had the closest prediction of an American college football score, using a pre-defined algorithm.

## What is This?
Once upon a time, people at work would come to my office and write their predictions on my whiteboard for the upcoming game for the big local college football team.  On Monday morning, we'd check the scores and decide whose prediction was the closest.  Admittedly, part of this was a plot to get people to stop by and chat about the game.  (In later years, a private Slack channel replaced the whiteboard.)

Determining whose prediction was closest was generally pretty simple, but sometimes it wasn't.  Eventually, I came up with the following algorithm, which is hard-coded here.

Well, it wasn't quite the code here.  When I first uploaded the source to GitHub, it didn't work, due to being developed on an older version of PHP.  I've made some updates and I hope it works for you now.

## Algorithm
### Definitions
| Term       | Definition                                                     |
|------------|----------------------------------------------------------------|
| **abs(x)** | Absolute Value of _x_                                          |
| **ΔUs**    | abs((Prediction for our team) - (Our team's score))            |
| **ΔThem**  | abs((Prediction for other team) - (Other team's score))        |
| **ΔΔ**     | abs((Predicted margin of victory) - (Actual margin of victory) |

### Finding the winner
1. Pick correct winner.  If still tied,
2. minimum(ΔUs + ΔThem + ΔΔ).  If still tied,
3. minimum(ΔUs^2 + ΔThem^2 + ΔΔ^2).  If still tied,
4. minimum(ΔΔ).  If still tied,
5. minimum(ΔUs)

## Usage
It should be pretty straightforward.  If it isn't clear, you need to input the number of scores you want to enter, and then enter names or initials and each entrant's predicted score.  You can pick colors for each entry, or select "Randomize Colors" to try your luck.  The colors are intended to make the final chart easier to comprehend.  Once you have a chart, you can see which score outcomes correspond to which entrant, and after the game, you can see which entrant is closest to the actual result.

## Notable and Recent Changes
### June 9, 2026
* A "Randomize Colors" checkbox has been added to aid in visibility of the areas, instead of defaulting all the cells to a white background with black text.  An effort is made to ensure that:
  * No background color is repeated.
  * No text color is repeated.
  * The same prediction's background and text colors are different.

  However, no effort is made to ensure highly-contrasting colors, and sometimes the effect can dazzle the eye.  But at least selecting the checkbox and submitting a few times should give you a good starting point that you can tweak as desired.
  
* Page styling has moved to CSS and a separate _style.css_ file.
### June 3, 2026
* Replacing cookies with sessions.  That is, there is a small cookie saved locally, but the bulk of the data is stored on the server.  This means that more than ~15 scores can actually be saved.
### June 1, 2026: Now with Cookies!
* State data is now saved locally with cookies, so that if you refresh or return to the page, your entered scores should be there when you return, instead of the page resetting to blank.  Note that there's a 4K limit on cookie size, and it's possible to go past this limit.  If you do, your values won't all be retained.  There will be an item added to "Ideas for Improvement" to address this.
* A limit on maximum name length (10 characters) has been added.


## Ideas for Improvement
1. The ability to create a game on the server, and allowing entrants to enter their own score.  For whatever it's worth, my thinking is that people can change their score as often as they'd like until the game starts, but should never take a score that's someone else currently has.
2. Persist the game (probably on the server) longer than is allowed by Sessions.
3. Enter predictions by clicking on the graph, instead of by entering numbers.
4. Automatically recalculate the graph when hovering, in order to show the effect of clicking on a specific outcome.
5. Make the algorithm configurable.
6. Allow for a higher number of scores or points.
7. I'm sure there are others.

Let me know what you think!
