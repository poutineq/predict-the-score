# predict-the-score
Simple tool to find who had the closest prediction of an American college football score, using a pre-defined algorithm.

## What is This?
Once upon a time, people at work would come to my office and write their predictions on my whiteboard for the upcoming game for the big local college football team.  On Monday morning, we'd check the scores and decide whose prediction was the closest.  Admittedly, part of this was a plot to get people to stop by and chat about the game.  (In later years, a private Slack channel replaced the whiteboard.)

Determining whose prediction was closest was generally pretty simple, but sometimes it wasn't.  Eventually, I came up with the following algorithm, which is hard-coded here.

Well, it wasn't quite the code here.  When I first uploaded the source to Github, it didn't work, due to being developed on an older version of PHP.  I've made some updates and I hope it works for you now.

## Algorithm
### Definitions
| Term | Definition |
| ---- | ---------- |
| **abs(x)** | Absolute Value of _x_ |
| **ΔUs** | abs((Prediction for our team) - (Our team's score)) |
| **ΔThem** | abs((Prediction for other team) - (Other team's score)) |
| **ΔΔ** | abs((Predicted margin of victory) - (Actual margin of victory) |

### Finding the winner
1. Pick correct winner.  If still tied,
2. minimum(ΔUs + ΔThem + ΔΔ).  If still tied,
3. minimum(ΔUs^2 + ΔThem^2 + ΔΔ^2).  If still tied,
4. minimum(ΔΔ).  If still tied,
5. minimum(ΔUs)

## Usage
It should be pretty straightforward.  If it isn't clear, you need to input the number of scores you want to enter, and then enter names or initials and each entrant's predicted score.  You can pick colors for each entry.  This is intended to make the final chart easier to comprehend.  Once you have a chart, you can see which score outcomes correspond to which entrant, and after the game, you can see which entrant is closest to the actual result.

## Ideas for Improvement
1. Introduce some kind of memory, perhaps through cookies or storage on the server, so you can go back to a game without having to retype everything in.
2. The ability to create a game on the server, and allowing entrants to enter their own score.  For whatever it's worth, my thinking is that people can change their score as often as they'd like until the game starts, but should never take a score that's someone else currently has.
4. Auto-populate the colors for people's predictions.
5. Enter predictions by clicking on the graph, instead of by entering numbers.
6. Automatically recalculate the graph when hovering, in order to show the effect of clicking on a specific outcome.
7. Make the algorithm configurable.
8. Allow for a higher number of scores or points.
9. I'm sure there are others.

Let me know what you think!
