<?php
use CRM_Reports_ExtensionUtil as E;

class CRM_Reports_Form_Report_PresenceList extends CRM_Report_Form {
  protected $_summary = NULL;

  function __construct() {
    // see if we have an event id
    if (($event_id = CRM_Utils_Request::retrieve('event_id', 'Positive'))) {
      // OK, found in the url
      $_SESSION['event_value'] = $event_id;
    }
    else {
      // not found, check submit values
      $event_id = $this->getSelectedParam('event_value');
      if (!$event_id) {
        $event_id = 0;
      }
    }

    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'last_name' => array(
            'title' => 'Last Name',
            'required' => TRUE,
          ),
          'first_name' => array(
            'title' => 'First Name',
            'required' => TRUE,
          ),
          'organization_name' => array(
            'title' => 'Organisation',
            'required' => TRUE,
          ),
          'signature' => array(
            'title' => 'Signature',
            'required' => TRUE,
            'dbAlias' => "'<br><br><br>'",
          ),
          'day2' => array(
            'title' => 'Day 2',
            'required' => FALSE,
            'dbAlias' => "''"
          ),
          'day3' => array(
            'title' => 'Day 3',
            'required' => FALSE,
            'dbAlias' => "''"
          ),
        ),
        'filters' => array(
          'event' => array(
            'title' => ts('Event'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->getEventList($event_id),
            'required' => TRUE,
          ),
        ),
      )
    );

    parent::__construct();
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($tableName == 'civicrm_address') {
              $this->_addressField = TRUE;
            }
            elseif ($tableName == 'civicrm_email') {
              $this->_emailField = TRUE;
            }
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $this->_from = "
      FROM
        civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
      INNER JOIN
        civicrm_participant p
      ON
        {$this->_aliases['civicrm_contact']}.id = p.contact_id 
        AND p.role_id = 1 and p.status_id not in (4,7,8,9,10,11,12) 
    ";
  }

  function where() {
    $this->_where = " WHERE {$this->_aliases['civicrm_contact']}.is_deleted = 0 and {$this->_aliases['civicrm_contact']}.is_deceased = 0 and event_id = " . $this->getSelectedParam('event_value');

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function groupBy() {
  }

  function orderBy() {
    $this->_orderBy = " ORDER BY {$this->_aliases['civicrm_contact']}.sort_name ";
  }


  function preProcess() {
    $this->assign('reportTitle', E::ts('Presence List'));
    parent::preProcess();
  }

  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  public function whereClause(&$field, $op, $value, $min, $max) {
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  function postProcess() {
    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);
    //die($sql);
    $rows = array();
    $this->buildRows($sql, $rows);

    // get the selected event
    $params = ['id' => $this->getSelectedParam('event_value')];
    $event = civicrm_api3('Event', 'getsingle', $params);
    $eventDate = date_format(date_create($event['start_date']), 'l, j F Y');
    $eventHour = date_format(date_create($event['start_date']), 'H')
      . 'h' . date_format(date_create($event['start_date']), 'i')
      . '-' . date_format(date_create($event['end_date']), 'H')
      . 'h' . date_format(date_create($event['end_date']), 'i');
    $this->assign('eventTitle', $event['title']);
    $this->assign('eventDate', $eventDate);
    $this->assign('eventHour', $eventHour);
    $this->assign('currentYear', date('Y'));

    // add location
    $eventLocation = '';
    if ($event['loc_block_id']) {
      $eventLocBlock = civicrm_api3('LocBlock', 'getsingle', ['id' => $event['loc_block_id']]);
      if ($eventLocBlock['address_id']) {
        $eventAddress = civicrm_api3('Address', 'getsingle', ['id' => $eventLocBlock['address_id']]);
        if ($eventAddress['street_address']) {
          $eventLocation = $eventAddress['street_address'] . '<br>';
        }
        if ($eventAddress['supplemental_address_1']) {
          $eventLocation = $eventAddress['supplemental_address_1'] . '<br>';
        }
        if ($eventAddress['supplemental_address_2']) {
          $eventLocation = $eventAddress['supplemental_address_2'] . '<br>';
        }
        if ($eventAddress['postal_code'] || $eventAddress['city']) {
          $eventLocation = $eventAddress['postal_code'] . ' ' . $eventAddress['city'];
        }
      }
    }
    $this->assign('eventLocation', $eventLocation);


    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    if (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
      // change "Signature" to "Signature Day 1"
      $this->_columnHeaders['civicrm_contact_signature']['title'] = 'Signature Day 1';

      $this->_columnHeaders['civicrm_contact_day2']['title'] = 'Signature Day 2';
    }

    if (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
      $this->_columnHeaders['civicrm_contact_day3']['title'] = 'Signature Day 3';
    }
  }

  function getEventList($event_id) {
    $eventList = [];

    $sql = "
      SELECT
        id
        , concat(
          DATE_FORMAT(start_date, '%d/%m/%Y')
          , ' - '
          , title
        ) event_name
      FROM
        civicrm_event
      WHERE
    ";

    if ($event_id > 0) {
      // we have a default event, select it
      $sql .= " id = $event_id";
    }
    else {
      // select all events
      $sql .= "
        start_date >= DATE_FORMAT(now(), '%Y-%m-%d')
      ORDER BY
        start_date
      ";
    }

    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
      $eventList[$dao->id] = $dao->event_name;
    }

    return $eventList;
  }

  function getSelectedParam($name) {
    if (array_key_exists($name, $this->_params) && $this->_params[$name]) {
      return $this->_params[$name];
    }
    elseif (array_key_exists($name, $this->_submitValues) && $this->_submitValues[$name]) {
      return $this->_submitValues[$name];
    }
    elseif ($_SESSION['event_value']) {
      return $_SESSION['event_value'];
    }
    else {
      return '';
    }
  }

}
