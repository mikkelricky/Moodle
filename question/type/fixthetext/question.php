<?php
/**
 * Fix the text question definition class.
 *
 * @package    qtype
 * @subpackage fixthetext
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a fix the text question.
 *
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_fixthetext_question extends
question_graded_automatically // implements question_grading_strategy
// question_graded_by_strategy implements question_response_answer_comparer
 {
	public $evaluate_verbosity_level;
	public $correct_text;
	public $initial_text;

	public function __construct() {
		spl_autoload_register(array($this, 'autoload'));
	}

	private function autoload($classname) {
		$classfile = str_replace('_', '/', $classname).'.php';
		include_once $classfile;
	}

	public function get_expected_data() {
		return array('answer' => PARAM_RAW_TRIMMED);
	}

	public function start_attempt(question_attempt_step $step, $variant) {}
	public function apply_attempt_state(question_attempt_step $step) {}

	public function summarise_response(array $response) {
		if (isset($response['answer'])) {
			return $response['answer'];
		} else {
			return null;
		}
	}

	public function is_gradable_response(array $response) {
		return array_key_exists('answer', $response) &&
			$response['answer'];
	}

	public function is_complete_response(array $response) {
		if (!$this->is_gradable_response($response)) {
			return false;
		}

		return true;
	}

	public function get_validation_error(array $response) {
		if (!$this->is_gradable_response($response)) {
			return get_string('pleaseenterananswer', 'qtype_fixthetext');
		}

		return '';
	}

	public function is_same_response(array $prevresponse, array $newresponse) {
		if (!question_utils::arrays_same_at_key_missing_is_blank(
																														 $prevresponse, $newresponse, 'answer')) {
			return false;
		}

		return false;
	}

	public function get_correct_response() {
		$answer = $this->get_correct_answer();
		if (!$answer) {
			return array();
		}

		$response = array('answer' => $answer->answer);

		return $response;
	}

	public function get_matching_answer(array $response) {
		$answer = '';
		$fraction = 0.0;
		$feedback = '';
		$feedback = 'Specific feedback '.$this->evaluate_verbosity_level;


		$answer = $response['answer'];
		$fraction = $this->computeScore($response);
		$feedback = $this->renderFeedback();
		// foreach ($this->correct_texts as $text) {
		// 	if ($text == $response['answer']) {
		// 		$answer = $response['answer'];
		// 		$fraction = 1.0;
		// 		$feedback = '@TODO: a perfect score ('.__METHOD__.')';
		// 	}
		// }

		return new question_answer(0, $answer, $fraction, $feedback, FORMAT_HTML);
	}

	public function get_correct_answer() {
		$answer = $this->get_correct_text();
		$fraction = $answer ? 1.0 : 0.0;
		$feedback = '';

		return new question_answer(0, $answer, $fraction, $feedback, FORMAT_HTML);
	}

	public function has_multiple_correct_answers() {
		return count($this->correct_texts) > 1;
	}

	public function get_correct_text() {
		if (count($this->correct_texts) > 0) {
			$index = rand(0, count($this->correct_texts)-1);
			return $this->correct_texts[$index];
		}
	}

	public function get_initial_text() {
		if (count($this->initial_texts) > 0) {
			$index = rand(0, count($this->initial_texts)-1);
			return $this->initial_texts[$index];
		}
	}

	public function grade_response(array $response) {
		$answer = $this->get_matching_answer($response);
		if (!$answer) {
			return array(0, question_state::$gradedwrong);
		}

		return array($answer->fraction, question_state::graded_state_for_fraction($answer->fraction));
	}

	// public function grade(array $response) {}

	private function computeScore(array $response) {
		$answer = $response['answer'];
		$answerTokens = self::splitText($answer);

		$bestMatch = null;

		$maxScore = -1;
		foreach ($this->correct_texts as $correctText) {
			$correctTokens = $this->splitText($correctText);
			$diff = new Horde_Text_Diff('auto', array($correctTokens, $answerTokens));
			$score = $this->_computeScore($correctTokens, $diff);
			if ($score > $maxScore) {
				$maxScore = $score;
				$this->bestMatchText = $correctText;
				$this->bestMatch = $diff;
			}
		}

		return ($maxScore < 0) ? 0 : $maxScore;
	}

	private function _computeScore(array $correctTokens, Horde_Text_Diff $diff) {
		$ops = $diff->getDiff();

		if ((count($ops) == 1) && ($ops[0] instanceof Horde_Text_Diff_Op_copy)) {
			return 1;
		}

		$maximumScore = 0;
		foreach ($correctTokens as $token) {
			if ($token != ' ') {
				$maximumScore += 1;
			}
		}

		$actualScore = 0;

		foreach ($ops as $op) {
			if ($op instanceof Horde_Text_Diff_Op_copy) {
				foreach ($op->final as $token) {
					if ($token != ' ') {
						$actualScore += 1;
					}
				}
			} else if ($op instanceof Horde_Text_Diff_Op_add) {
				$actualScore += -1;
			} else if ($op instanceof Horde_Text_Diff_Op_delete) {
				$actualScore += -1;
			} else if ($op instanceof Horde_Text_Diff_Op_change) {
				$actualScore += -1;
			}
		}

		if ($actualScore < 0) {
			$actualScore = 0;
		}

		return ($maximumScore > 0) ? $actualScore/$maximumScore : 0;
	}

	private function renderFeedback() {
		if ($this->bestMatch) {
			$renderer = new qtype_fixthetext_diff_renderer($this->evaluate_verbosity_level);
			return $renderer->render($this->bestMatch);
		}
	}

	private static $wordPattern = '/([\p{L}\p{N}-]+)/mu';
	private static $punctuationPattern = '/([\p{P}]+)/mu';

	private static function splitText($text) {
		$result = array();

		$text = trim($text);

		foreach (preg_split(self::$wordPattern, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $token) {
			if (preg_match(self::$wordPattern, $token)) {
				$result[] = $token;
			} else {
				foreach (preg_split(self::$punctuationPattern, $token, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $token) {
					if (preg_match(self::$punctuationPattern, $token)) {
						$result[] = $token;
					} else {
						$result[] = ' ';
					}
				}
			}
		}

		return $result;
	}
}

require_once 'Horde/Text/Diff/Renderer.php';

class qtype_fixthetext_diff_renderer extends Horde_Text_Diff_Renderer {
	private $verbosityLevel = 0;
	private $view = null;

	public function __construct($verbosityLevel = 0, $params = array()) {
		parent::__construct($params);
		$this->verbosityLevel = $verbosityLevel;
	}

	public function render(Horde_Text_Diff $diff) {
		$diffs = $diff->getDiff();

		$content = '';

		$numberOfErrors = $this->getNumberOfErrors($diffs);

		if ($numberOfErrors > 0) {
			$content .= html_writer::tag('div',
																	 // get_string('Number of errors: %d', 'qtype_fixthetext', array($numberOfErrors)),
																	 get_string('number_of_errors', 'qtype_fixthetext', $numberOfErrors),
																	 array('class' => 'number-of-errors'));
		}

		switch ($this->verbosityLevel) {
		case 1:
			$content .= $this->render1($diffs);
			break;
		case 2:
			$content .= $this->render2($diffs);
			break;
		default:
			$content .= $this->render0($diffs);
			break;
		}

		if ($content) {
			$content = html_writer::tag('div', $content, array('class' => 'diff'));
		}

 		return $content;
	}

	private function getNumberOfErrors($diffs) {
		$numberOfErrors = 0;
		foreach ($diffs as $op) {
			if (!($op instanceof Horde_Text_Diff_Op_copy)) {
				$numberOfErrors += 1;
			}
		}

		return $numberOfErrors;
	}

	private function render0(array $diffs) {
		return '';
	}

	private function render1(array $diffs) {
		$content = '';

		$content .= html_writer::start_tag('div', array('class' => 'sentence'));

		foreach ($diffs as $op) {
			$final = $op->final ? implode('', $op->final) : self::$blank;
			$attributes = array();
			if ($op instanceof Horde_Text_Diff_Op_copy) {
				$attributes['class'] = 'copy';
			} else if ($op instanceof Horde_Text_Diff_Op_add) {
				$attributes['class'] = 'add';
			} else if ($op instanceof Horde_Text_Diff_Op_delete) {
				$attributes['class'] = 'delete';
			} else if ($op instanceof Horde_Text_Diff_Op_change) {
				$attributes['class'] = 'change';
			}
			if ($attributes) {
				$content .= html_writer::tag('span', $final, $attributes);
			}
		}

		$content .= html_writer::end_tag('div');

		return $content;
	}

	private static $blank = '&#xA0;';

	private function render2(array $diffs) {
		$content = '';

		$content .= html_writer::start_tag('div', array('class' => 'sentence clearfix'));

		$attributes = array('class' => 'headers');
		$content .= html_writer::start_tag('table', $attributes)
			.html_writer::start_tag('tr')
			.html_writer::start_tag('td', array('class' => 'orig'))
			.get_string('diff_header_correct_text', 'qtype_fixthetext')
			.html_writer::end_tag('td')
			.html_writer::end_tag('tr')
			.html_writer::start_tag('tr')
			.html_writer::start_tag('td', array('class' => 'final'))
			.get_string('diff_header_your_text', 'qtype_fixthetext')
			.html_writer::end_tag('td')
			.html_writer::end_tag('tr')
			.html_writer::end_tag('tr')
			.html_writer::end_tag('table');

		foreach ($diffs as $op) {
			$orig = $op->orig ? implode('', $op->orig) : self::$blank;
			$final = $op->final ? implode('', $op->final) : self::$blank;
			$attributes = array();
			if ($op instanceof Horde_Text_Diff_Op_copy) {
				$attributes['class'] = 'copy';
				$attributes['title'] = get_string('diff_title_copy', 'qtype_fixthetext');
				$orig = self::$blank;
			} else if ($op instanceof Horde_Text_Diff_Op_add) {
				$attributes['class'] = 'add';
				$attributes['title'] = get_string('diff_title_add', 'qtype_fixthetext');
			} else if ($op instanceof Horde_Text_Diff_Op_delete) {
				$attributes['class'] = 'delete';
				$attributes['title'] = get_string('diff_title_delete', 'qtype_fixthetext');
			} else if ($op instanceof Horde_Text_Diff_Op_change) {
				$attributes['class'] = 'change';
				$attributes['title'] = get_string('diff_title_change', 'qtype_fixthetext');
			}

			if ($attributes) {
				$content .= html_writer::start_tag('table', $attributes)
					.html_writer::start_tag('tr')
					.html_writer::start_tag('td', array('class' => 'orig'))
					.$orig
					.html_writer::end_tag('td')
					.html_writer::end_tag('tr')
					.html_writer::start_tag('tr')
					.html_writer::start_tag('td', array('class' => 'final'))
					.$final
					.html_writer::end_tag('td')
					.html_writer::end_tag('tr')
					.html_writer::end_tag('tr')
					.html_writer::end_tag('table');
			}
		}

		$content .= html_writer::end_tag('div');

		return $content;
	}
}

// class qtype_fixthetext_evaluator {

// }

// class qtype_fixthetext_answer extends question_answer {
//     /**
//      * Constructor.
//      * @param int $id the answer.
//      * @param string $answer the answer.
//      * @param number $fraction the fraction this answer is worth.
//      * @param string $feedback the feedback for this answer.
//      * @param int $feedbackformat the format of the feedback.
//      */
//     public function __construct($id, $answer, $fraction, $feedback, $feedbackformat) {
//         $this->id = $id;
//         $this->answer = $answer;
//         $this->fraction = $fraction;
//         $this->feedback = $feedback;
//         $this->feedbackformat = $feedbackformat;
//     }

// }
