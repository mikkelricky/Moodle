<?php
/**
 * Fix the text question renderer class.
 *
 * @package    qtype
 * @subpackage fixthetext
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for fix the text questions.
 *
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_fixthetext_renderer extends qtype_renderer {
	public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
		$question = $qa->get_question();
		$currentanswer = $qa->get_last_qt_var('answer');
		$inputname = $qa->get_qt_field_name('answer');
		$inputattributes = array(
														 'name' => $inputname,
														 'id' => $inputname,
														 'rows' => 10,
														 'cols' => 80,
														 );

		if ($options->readonly) {
			$inputattributes['readonly'] = 'readonly';
		}

		$feedbackimg = '';
		if ($options->correctness) {
			$answer = $question->get_matching_answer(array('answer' => $currentanswer));
			if ($answer) {
		 		$fraction = $answer->fraction;
		 	} else {
		 		$fraction = 0;
		 	}
		 	$inputattributes['class'] = $this->feedback_class($fraction);
		 	$feedbackimg = $this->feedback_image($fraction);
		}

		$content = $currentanswer ? $currentanswer : $question->get_initial_text();
		$input = html_writer::tag('textarea', $content, $inputattributes).$feedbackimg;

		$result = html_writer::tag('div', $input, array('class' => 'qtext'));

		return $result;
	}

	public function specific_feedback(question_attempt $qa) {
		$feedback = '';
		$question = $qa->get_question();
		$answer = $question->get_matching_answer(array('answer' => $qa->get_last_qt_var('answer')));
		if ($answer && $answer->feedback) {
			$feedback = $answer->feedback;
		}

		return $feedback;
	}

	public function correct_response(question_attempt $qa) {
		$question = $qa->get_question();

		$answer = $question->get_correct_answer();
		if (!$answer) {
			return '';
		}

		if ($question->has_multiple_correct_answers()) {
			return get_string('A_correct_answer_is', 'qtype_fixthetext', s($answer->answer));
		} else {
			return get_string('The_correct_answer_is', 'qtype_fixthetext', s($answer->answer));
		}
	}
}
