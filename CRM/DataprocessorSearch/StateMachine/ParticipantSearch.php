<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_DataprocessorSearch_StateMachine_ParticipantSearch extends CRM_Core_StateMachine {

  /**
   * The task that the wizard is currently processing
   *
   * @var string
   */
  protected $_task;

  /**
   * Class constructor.
   *
   * @param object $controller
   * @param \const|int $action
   */
  public function __construct($controller, $action = CRM_Core_Action::NONE) {
    parent::__construct($controller, $action);

    $this->_pages = array();
    $this->_pages['Basic'] = array(
      'className' => 'CRM_DataprocessorSearch_Form_ParticipantSearch',
    );
    list($task, $result) = $this->taskName($controller);
    $this->_task = $task;
    if (is_array($task)) {
      foreach ($task as $t) {
        $this->_pages[$t] = NULL;
      }
    }
    else {
      $this->_pages[$task] = NULL;
    }

    $this->addSequentialPages($this->_pages, $action);
  }

  /**
   * Determine the form name based on the action. This allows us
   * to avoid using  conditional state machine, much more efficient
   * and simpler
   *
   * @param CRM_Core_Controller $controller
   *   The controller object.
   *
   * @return array
   *   the name of the form that will handle the task
   */
  public function taskName($controller) {
    // total hack, check POST vars and then session to determine stuff
    $value = CRM_Utils_Array::value('task', $_POST);
    if (!isset($value)) {
      $value = $controller->get('task');
    }
    $this->_controller->set('task', $value);

    return CRM_Event_Task::getTask($value);
  }

  /**
   * Return the form name of the task.
   *
   * @return string
   */
  public function getTaskFormName() {
    if (is_array($this->_task)) {
      // return first page
      return CRM_Utils_String::getClassName($this->_task[0]);
    }
    else {
      return CRM_Utils_String::getClassName($this->_task);
    }
  }

  /**
   * Since this is a state machine for search and we want to come back to the same state
   * we dont want to issue a reset of the state session when we are done processing a task
   */
  public function shouldReset() {
    return FALSE;
  }

}
