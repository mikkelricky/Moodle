<?php defined('MOODLE_INTERNAL') || die();
/**
 * Defines the editing form for the fixthetext question type.
 *
 * @package    qtype
 * @subpackage fixthetext
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fix the text question editing form definition.
 *
 * @copyright  2012 Mikkel Ricky
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_fixthetext_edit_form extends question_edit_form {
	protected function definition_inner($mform) {
		$mform->addElement('textarea', 'correct_text',
											 get_string('correct_text', 'qtype_fixthetext'),
											 array('rows' => 10,
														 'cols' => 80)
											 );

		$mform->addElement('textarea', 'initial_text',
											 get_string('initial_text', 'qtype_fixthetext'),
											 array('rows' => 10,
														 'cols' => 80)
											 );

		$menu = array();
		for ($i = 0; $i < 3; $i++) {
			$menu[$i] = get_string('evaluate_verbosity_level_'.$i, 'qtype_fixthetext');
		}
		$mform->addElement('select', 'evaluate_verbosity_level',
											 get_string('evaluate_verbosity_level', 'qtype_fixthetext'), $menu);

		// $mform->closeHeaderBefore('answersinstruct');

		// $this->add_per_answer_fields($mform, get_string('answerno', 'qtype_fixthetext', '{no}'),
		//         question_bank::fraction_options());

		$this->add_interactive_settings();
	}

	protected function data_preprocessing($question) {
		$question = parent::data_preprocessing($question);
		// $question = $this->data_preprocessing_answers($question);
		$question = $this->data_preprocessing_hints($question);

		// if (!empty($question->options)) {
		// 	$question->usecase = $question->options->usecase;
		// }

		return $question;
	}

	public function validation($data, $files) {
		$errors = parent::validation($data, $files);

		if (!(isset($data['correct_text']) && trim($data['correct_text']))) {
			$errors['correct_text'] = get_string('missing_correct_text', 'qtype_fixthetext', 1);
		}
		if (!(isset($data['initial_text']) && trim($data['initial_text']))) {
			$errors['initial_text'] = get_string('missing_initial_text', 'qtype_fixthetext', 1);
		}
		return $errors;
	}

	public function qtype() {
		return 'fixthetext';
	}
}
