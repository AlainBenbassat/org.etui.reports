<?php
use CRM_Reports_ExtensionUtil as E;

class CRM_Reports_Form_Report_PresenceList extends CRM_Report_Form {
  protected $_summary = NULL;
  private $event_date;
  private $number_of_selected_days = 0;

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
            'dbAlias' => 'cc.organisation_550',
          ),
          'country' => array(
            'title' => 'Country',
            'required' => FALSE,
            'dbAlias' => 'cc_ctry.label',
          ),
          'day1' => array(
            'title' => 'Day 1',
            'required' => FALSE,
            'default' => TRUE,
            'dbAlias' => "'<br><br><br>'"
          ),
          'day2' => array(
            'title' => 'Day 2',
            'required' => FALSE,
            'dbAlias' => "'<br><br><br>'"
          ),
          'day3' => array(
            'title' => 'Day 3',
            'required' => FALSE,
            'dbAlias' => "'<br><br><br>'"
          ),
          'day4' => array(
            'title' => 'Day 4',
            'required' => FALSE,
            'dbAlias' => "'<br><br><br>'"
          ),
          'day5' => array(
            'title' => 'Day 5',
            'required' => FALSE,
            'dbAlias' => "'<br><br><br>'"
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
      ),
      'civicrm_participant' => array(
        'dao' => 'CRM_Event_DAO_Participant',
        'filters' => array(
          'rid' => array(
            'name' => 'role_id',
            'title' => ts('Participant Role'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantRole(),
          ),
        ),
      ),
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
        civicrm_participant p ON {$this->_aliases['civicrm_contact']}.id = p.contact_id
      LEFT OUTER JOIN
        civicrm_value_belonging_to_221 cc ON {$this->_aliases['civicrm_contact']}.id = cc.entity_id
      LEFT OUTER JOIN
        civicrm_option_value cc_ctry ON cc.origin_552 = cc_ctry.value and cc_ctry.option_group_id = 135
    ";
  }

  function where() {
    $this->_where = "
      WHERE
        {$this->_aliases['civicrm_contact']}.is_deleted = 0
      and
        {$this->_aliases['civicrm_contact']}.is_deceased = 0
      and
        p.status_id not in (4,7,8,9,10,11,12)
      and
        event_id = " . $this->getSelectedParam('event_value');

    // check if we have to filter on role id as well
    if (array_key_exists('rid_value', $this->_submitValues) && count($this->_submitValues['rid_value']) > 0) {
      $operator = $this->_submitValues['rid_op'];
      if ($operator == 'notin') {
        $operator = 'not in';
      }
      $this->_where .= " and role_id $operator (" . implode(',', $this->_submitValues['rid_value']) . ')';
    }

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
    $sql = $this->buildQuery(TRUE);
    //die($sql);
    $rows = [];
    $this->buildRows($sql, $rows);

    // get the selected days
    if (array_key_exists('civicrm_contact_day1', $this->_columnHeaders)) {
      $dayOffset = 0;
      $this->number_of_selected_days++;
    }
    if (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
      $this->number_of_selected_days++;
      $dayOffset = 1;
    }
    if (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
      $this->number_of_selected_days++;
      $dayOffset = 2;
    }
    if (array_key_exists('civicrm_contact_day4', $this->_columnHeaders)) {
      $this->number_of_selected_days++;
      $dayOffset = 3;
    }
    if (array_key_exists('civicrm_contact_day5', $this->_columnHeaders)) {
      $this->number_of_selected_days++;
      $dayOffset = 4;
    }

    // get the selected event
    $params = ['id' => $this->getSelectedParam('event_value')];
    $event = civicrm_api3('Event', 'getsingle', $params);

    // if more than one day is selected, we show the start date
    // otherwise we show the start date + day offset
    $date = new DateTime($event['start_date']);
    if ($this->number_of_selected_days > 1) {
      $this->event_date = $date;
    }
    else {
      $this->event_date = $date->add(new DateInterval('P' . $dayOffset . 'D'));;
    }

    $this->assign('eventTitle', $event['title']);
    $this->assign('eventDate', $date->format('l, j F Y'));
    $this->assign('currentYear', date('Y'));

    // add location
    $eventLocation = '';
    if ($event['loc_block_id']) {
      $eventLocBlock = civicrm_api3('LocBlock', 'getsingle', ['id' => $event['loc_block_id']]);
      if ($eventLocBlock['address_id']) {
        $eventAddress = civicrm_api3('Address', 'getsingle', ['id' => $eventLocBlock['address_id']]);
        if ($eventAddress['street_address']) {
          $eventLocation .= $eventAddress['street_address'] . '<br>';
        }
        if ($eventAddress['supplemental_address_1']) {
          $eventLocation .= $eventAddress['supplemental_address_1'] . '<br>';
        }
        if ($eventAddress['supplemental_address_2']) {
          $eventLocation .= $eventAddress['supplemental_address_2'] . '<br>';
        }
        if ($eventAddress['postal_code'] || $eventAddress['city']) {
          $eventLocation .= $eventAddress['postal_code'] . ' ' . $eventAddress['city'] . '<br>';
        }
        if ($eventAddress['country_id']) {
          $country = civicrm_api3('Country', 'getsingle', [
            'id' => $eventAddress['country_id'],
            'return' => ['name'],
          ]);
          $eventLocation .= $country['name'];
        }
      }
    }
    $this->assign('eventLocation', $eventLocation);


    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    // the signature columns are: day1, day2, day3
    // the user can select 1 column (e.g. day2) or multiple
    if ($this->number_of_selected_days == 1) {
      // change the column title to "Signature"
      if (array_key_exists('civicrm_contact_day1', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day1']['title'] = 'Signature';
      }
      elseif (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day2']['title'] = 'Signature';
      }
      elseif (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day3']['title'] = 'Signature';
      }
      elseif (array_key_exists('civicrm_contact_day4', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day4']['title'] = 'Signature';
      }
      elseif (array_key_exists('civicrm_contact_day5', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day5']['title'] = 'Signature';
      }
    }
    else {
      // change the column titles with date
      if (array_key_exists('civicrm_contact_day1', $this->_columnHeaders)) {
        $this->_columnHeaders['civicrm_contact_day1']['title'] = $this->event_date->format('j F');
      }
      if (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day2']['title'] = $d->add(new DateInterval('P1D'))->format('j F');
      }
      if (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day3']['title'] = $d->add(new DateInterval('P2D'))->format('j F');
      }
      if (array_key_exists('civicrm_contact_day4', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day4']['title'] = $d->add(new DateInterval('P3D'))->format('j F');
      }
      if (array_key_exists('civicrm_contact_day5', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day5']['title'] = $d->add(new DateInterval('P4D'))->format('j F');
      }
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
