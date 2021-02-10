<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

/**
 * This class is used by the Search functionality.
 *
 *  - the search controller is used for building/processing multiform
 *    searches.
 *
 * Typically the first form will display the search criteria and it's results
 *
 * The second form is used to process search results with the associated actions.
 */
class CRM_DataprocessorSearch_Controller_ActivitySearch extends CRM_Core_Controller {

  /**
   * Class constructor.
   *
   * @param string $title
   * @param bool $modal
   * @param int|mixed|null $action
   */
  public function __construct($title = NULL, $modal = TRUE, $action = CRM_Core_Action::NONE) {
    parent::__construct($title, $modal);

    $this->set('component_mode', CRM_Contact_BAO_Query::MODE_ACTIVITY);
    $this->_stateMachine = new CRM_DataprocessorSearch_StateMachine_ActivitySearch($this, $action);

    // create and instantiate the pages
    $this->addPages($this->_stateMachine, $action);

    // add all the actions
    $this->addActions();
  }

  /**
   * Process the request, overrides the default QFC run method
   * This routine actually checks if the QFC is modal and if it
   * is the first invalid page, if so it call the requested action
   * if not, it calls the display action on the first invalid page
   * avoids the issue of users hitting the back button and getting
   * a broken page
   *
   * This run is basically a composition of the original run and the
   * jump action
   *
   * @return mixed
   */
  public function run() {

    $actionName = $this->getActionName();
    list($pageName, $action) = $actionName;
    // Hack to replace to userContext for redirecting after a Task has been completed.
    // We want the redirect
    if (!$this->_pages[$pageName] instanceof CRM_DataprocessorSearch_Form_ActivitySearch) {
      $session = CRM_Core_Session::singleton();
      $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
      $urlPath = CRM_Utils_System::currentPath();
      $urlParams = 'force=1';
      if ($qfKey) {
        $urlParams .= "&qfKey=$qfKey";
      }

      $this->setDestination(CRM_Utils_System::url($urlPath, $urlParams));
    }

    return parent::run();
  }

  /**
   * @return mixed
   */
  public function selectorName() {
    return $this->get('selectorName');
  }

}
