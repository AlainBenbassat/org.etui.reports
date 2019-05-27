<?php

class CRM_Reports_Task_ShowPresenceList extends CRM_Event_Form_Task {

  public function __construct() {
    $queryParams = [];
    $queryParams[] = 'reset=1';
    $queryParams[] = 'output=criteria';

    // try to get the event id
    // step 1: check if we have event= in the entryURL of the session
    $event_id = 0;
    $session = CRM_Core_Session::singleton();
    $entryURL = $session->get('entryURL');
    if (strpos($entryURL, '&amp;event=')) {
      $urlParts = explode('&amp;', $entryURL);
      foreach ($urlParts as $urlPart) {
        $splitUrlPart = explode('=', $urlPart);
        if ($splitUrlPart[0] == 'event') {
          $event_id = $splitUrlPart[1];
          break;
        }
      }
    }
    else {
      // step 2: no event id in the url, check previous search in session
      $allVars = [];
      $session->getVars($allVars);
      foreach ($allVars as $sessionKey => $sessionValue) {
        if (strpos($sessionKey, 'CRM_Event_Controller_Search_') !== FALSE) {
          if (array_key_exists('formValues', $sessionValue)) {
            if (array_key_exists('event_id', $sessionValue['formValues'])) {
              $event_id = $sessionValue['formValues']['event_id'];
            }
          }
        }
      }
    }

    if ($event_id > 0) {
      $queryParams[] = 'event_id=' . $event_id;
    }

    // redirect to the presence report instance (i.e. id = 61)
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/report/instance/61', implode('&', $queryParams)));
  }

}
