<?php
	/**
	 * Question type class for the fix the text question type.
	 *
	 * @package    qtype
	 * @subpackage fixthetext
	 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
	 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
	 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/fixthetext/question.php');


/**
 * The fix the text question type.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_fixthetext extends question_type {
	public function extra_question_fields() {
		return array('question_fixthetext',
								 'correct_text', 'initial_text', 'evaluate_verbosity_level');
	}

	public function save_question_options($question) {
		$db = $GLOBALS['DB'];

		$values = $db->get_record('question_fixthetext', array('questionid' => $question->id));
		if (!$values) {
			$values = new stdClass();
		}

		$values->questionid = $question->id;
		$values->correct_text = trim($question->correct_text);
		$values->initial_text = trim($question->initial_text);
		$values->evaluate_verbosity_level = intval($question->evaluate_verbosity_level);

		if (isset($values->id)) {
			$db->update_record('question_fixthetext', $values);
		} else {
			$values->id = $db->insert_record('question_fixthetext', $values);
		}
	}

	protected function initialise_question_instance(question_definition $question, $questiondata) {
		parent::initialise_question_instance($question, $questiondata);
		$this->initialise_fixthetext_answers($question, $questiondata);
		$question->evaluate_verbosity_level = $questiondata->options->evaluate_verbosity_level;
	}

	private function initialise_fixthetext_answers(question_definition $question, $questiondata) {
		$question->correct_texts = qtype_fixthetext_processor::getTexts($questiondata->options->correct_text);
		$question->initial_texts = qtype_fixthetext_processor::getTexts($questiondata->options->initial_text);
	}
}

class qtype_fixthetext_processor {
	public static function getTexts($text) {
		$pattern = '/\{{3}((?:[^}]|\}{1,2}[^}])+)\}{3}/mu';

		$alternativeBlocks = array();
		$numberOfTexts = 1;

		$tokens = array();
		foreach (preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $index => $token) {
			if ($index%2 == 0) {
				$tokens[] = $token;
			} else {
				$alternatives = array_map('trim', preg_split('/\s*\|+\s*/', $token));
				$alternativeBlocks[] = $alternatives;
				$numberOfTexts *= count($alternatives);
				$tokens[] = $alternatives;
			}
		}

		if (count($alternativeBlocks) < 1) {
			return array($text);
		}

		$texts = array();

		for ($textIndex = 0; $textIndex < $numberOfTexts; $textIndex++) {
			$text = '';

			$alternativeIndexes = array_fill(0, count($alternativeBlocks), 0);

			$i = $textIndex;
			$index = 0;
			while (($i > 0) && ($index < count($alternativeBlocks))) {
				$max = count($alternativeBlocks[$index]);
				if ($i < $max) {
					$alternativeIndexes[$index] = $i;
					break;
				}
				$alternativeIndexes[$index] = $i%$max;
				$i = floor($i/$max);
				$index += 1;
			}

// 			debug(array('index' => $textIndex,
// 									'indexes' => $alternativeIndexes), __METHOD__);

			$alternativeIndex = 0;
			foreach ($tokens as $token) {
				if (is_array($token)) {
					$text .= $token[$alternativeIndexes[$alternativeIndex]];
					$alternativeIndex += 1;
				} else {
					$text .= $token;
				}
			}
			$texts[] = $text;
		}

		// debug(array($tokens, $texts), __METHOD__);

		// debug_die($texts);

		return $texts;
	}
}
