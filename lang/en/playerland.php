<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.LineLength

$string['addquestion'] = 'Add question';
$string['answercorrect'] = 'Correct! Well done.';
$string['answerincorrect'] = 'Incorrect. Keep trying!';
$string['collected'] = 'Collected: {$a->cherries} cherries, {$a->gems} gems';
$string['confirmdeletequestion'] = 'Are you sure you want to delete this question?';
$string['controlcrouch'] = 'Down - crouch';
$string['controlfullscreen'] = 'F - fullscreen';
$string['controljump'] = 'Space or Up - jump (hold for a higher jump)';
$string['controlmove'] = 'Arrow keys - move';
$string['controlroll'] = 'Shift - roll';
$string['err_lessontoolong'] = 'A mini-lesson can have at most {$a} characters.';
$string['err_positiveint'] = 'Enter a number greater than zero.';
$string['error_no_correct_option'] = 'You must select one correct option.';
$string['exitlocked'] = 'Answer {$a} more question(s) to unlock the exit.';
$string['exitunlocked'] = 'Exit unlocked!';
$string['fullscreen'] = 'Fullscreen (F)';
$string['gamesettings'] = 'Game settings';
$string['gotit'] = 'Got it';
$string['introtitle'] = 'How to play';
$string['invalidquestion'] = 'Invalid question.';
$string['iscorrect'] = 'Correct answer';
$string['lesson'] = 'Mini-lesson';
$string['lessoncharcount'] = '{$a->count}/{$a->max} characters';
$string['lessonnum'] = 'Mini-lesson {$a}';
$string['lessons'] = 'Mini-lessons';
$string['lessons_help'] = 'Up to three short plain-text explanations. Each is shown in the game by a "!" lesson block placed on the map (marker "lesson", property n = 1 to 3). No formatting, images or video.';
$string['levelcomplete'] = 'Level complete!';
$string['levels'] = 'Number of levels';
$string['managequestions'] = 'Manage questions';
$string['map'] = 'Game map';
$string['map001'] = 'Level 1 - The Trail';
$string['map009'] = 'Level 9 - Woodland Ruins (draft)';
$string['map_help'] = 'Select the map to load for this game instance.';
$string['mapdemoa'] = 'Prototype A (tests)';
$string['mapdemob'] = 'Prototype B (eagle & frog)';
$string['modulename'] = 'PlayerLand';
$string['modulename_help'] = 'The PlayerLand activity allows students to play a 2D platformer game (like Super Mario). Hitting question blocks will trigger a Moodle question modal. Correct answers yield rewards and progress. The teacher can configure multiple levels and proportional grading.';
$string['modulenameplural'] = 'PlayerLands';
$string['noquestions'] = 'No questions have been added yet.';
$string['option'] = 'Option';
$string['optiontext'] = 'Option text';
$string['playerland:addinstance'] = 'Add a new PlayerLand';
$string['playerland:manage'] = 'Manage PlayerLand';
$string['playerland:view'] = 'View PlayerLand';
$string['pluginadministration'] = 'PlayerLand administration';
$string['pluginname'] = 'PlayerLand: The Adventures of Huddy';
$string['pressenter'] = 'Press ENTER to play again';
$string['privacy:metadata:blocksresolved'] = 'The number of blocks the user has resolved.';
$string['privacy:metadata:currentlevel'] = 'The level the user has reached.';
$string['privacy:metadata:playerland_ans'] = 'Stores the distinct questions each student has answered correctly in a PlayerLand activity.';
$string['privacy:metadata:playerland_atmpt'] = 'Stores the progress each student has made in a PlayerLand activity.';
$string['privacy:metadata:questionid'] = 'The ID of the question answered correctly by the user.';
$string['privacy:metadata:timecreated'] = 'The time at which this record was created.';
$string['privacy:metadata:timemodified'] = 'The time at which this record was last modified.';
$string['privacy:metadata:userid'] = 'The ID of the user this record belongs to.';
$string['question'] = 'Question';
$string['questiondeleted'] = 'Question deleted.';
$string['questionsaved'] = 'Question saved successfully.';
$string['questionsprogress'] = 'Questions: {$a->resolved}/{$a->target}';
$string['questiontext'] = 'Question text';
$string['questiontopic'] = 'Linked to';
$string['questiontopic_help'] = 'Choose a mini-lesson to make this question part of its practice pool. A question block with property n = 1 to 3 in the map draws only from the matching mini-lesson, falling back to the general pool. "General pool" questions can appear at any question block.';
$string['questiontopicgeneral'] = 'General pool';
$string['targetquestions'] = 'Questions required to unlock the exit';
$string['targetquestions_help'] = 'The number of distinct questions the student must answer correctly before the exit flag can complete the level. The activity grade is calculated proportionally from this target.';
