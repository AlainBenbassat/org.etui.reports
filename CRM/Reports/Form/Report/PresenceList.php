<?php
use CRM_Reports_ExtensionUtil as E;

class CRM_Reports_Form_Report_PresenceList extends CRM_Report_Form {
  protected $_summary = NULL;
  private $event_date;
  private $number_of_selected_days = 0;
  private $is_multi_day_event = FALSE;

  function __construct() {
    $emptySignatureCell = "'" . $this->getEmptySignatureCell() . "'";

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

    $this->_columns = [
      'civicrm_contact' => [
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'last_name' => [
            'title' => 'Last Name',
            'required' => TRUE,
          ],
          'first_name' => [
            'title' => 'First Name',
            'required' => TRUE,
          ],
          'organization_name' => [
            'title' => 'Organisation',
            'required' => TRUE,
            'dbAlias' => 'cc.organisation_550',
          ],
          'country' => [
            'title' => 'Country',
            'required' => FALSE,
            'dbAlias' => 'cc_ctry.label',
          ],
          'day1' => [
            'title' => 'Day 1',
            'required' => FALSE,
            'default' => TRUE,
            'dbAlias' => $emptySignatureCell,
          ],
          'day2' => [
            'title' => 'Day 2',
            'required' => FALSE,
            'dbAlias' => $emptySignatureCell,
          ],
          'day3' => [
            'title' => 'Day 3',
            'required' => FALSE,
            'dbAlias' => $emptySignatureCell,
          ],
          'day4' => [
            'title' => 'Day 4',
            'required' => FALSE,
            'dbAlias' => $emptySignatureCell,
          ],
          'day5' => [
            'title' => 'Day 5',
            'required' => FALSE,
            'dbAlias' => $emptySignatureCell,
          ],
        ],
        'filters' => [
          'event' => [
            'title' => ts('Event'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->getEventList($event_id),
            'required' => TRUE,
          ],
        ],
      ],
      'civicrm_participant' => [
        'dao' => 'CRM_Event_DAO_Participant',
        'filters' => [
          'rid' => [
            'name' => 'role_id',
            'title' => ts('Participant Role'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Event_PseudoConstant::participantRole(),
          ],
          'presence' => [
            'name' => 'presence',
            'title' => 'Presence',
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => CRM_Core_OptionGroup::values('presence_20210628091316'),
          ],
        ],
      ],
    ];

    parent::__construct();
  }

  function select() {
    $select = $this->_columnHeaders = [];

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
      LEFT OUTER JOIN
        civicrm_value_participant_p_206 pp ON p.id = pp.entity_id
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

    $this->_where .= $this->getWhereRoleFilter();
    $this->_where .= $this->getWherePresenceFilter();

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function getWhereRoleFilter() {
    $filter = '';

    if (array_key_exists('rid_value', $this->_submitValues) && count($this->_submitValues['rid_value']) > 0) {
      $operator = $this->_submitValues['rid_op'];
      if ($operator == 'notin') {
        $operator = 'not in';
      }
      $filter = " and role_id $operator (" . implode(',', $this->_submitValues['rid_value']) . ')';
    }

    return $filter;
  }

  function getWherePresenceFilter() {
    $filter = '';
    $selectedDays = [];

    if (array_key_exists('presence_value', $this->_submitValues)) {
      if (array_key_exists('civicrm_contact_day1', $this->_columnHeaders)) {
        $selectedDays[] = "ifnull(pp.presence_575, 1) = " . $this->_submitValues['presence_value'];
      }
      if (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
        $selectedDays[] = "ifnull(pp.presence_day2_590, 1) = " . $this->_submitValues['presence_value'];
      }
      if (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
        $selectedDays[] = "ifnull(pp.presence_day_3_591, 1) = " . $this->_submitValues['presence_value'];
      }

      $n = count($selectedDays);
      if ($n == 0) {
        $filter = " and ifnull(pp.presence_575, 1) = " . $this->_submitValues['presence_value'];
      }
      elseif ($n == 1) {
        $filter = ' and ' . $selectedDays[0];
      }
      else {
        $filter = ' and (' . implode(' or ', $selectedDays) . ')';
      }
    }

    return $filter;
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

    $event = $this->getEventDetails();
    $this->assign('eventTitle', $event['title']);
    $this->assign('eventanalyticalNumber', 'Analytical no.: ' . $event['analytical_number']);

    $this->setEventDateToShow($event);
    if ($this->number_of_selected_days == 1 && $this->is_multi_day_event) {
      $this->assign('eventDate', $this->event_date->format('l, j F Y'));
    }

    $eventLocation = $this->getEventLocation($event);
    $this->assign('eventLocation', $eventLocation);

    $eventDuration = $this->getEventDuration($event);
    $this->assign('eventDuration', $eventDuration);

    $this->assign('currentYear', date('Y'));

    $this->formatDisplay($rows);
    $this->addBlankRows($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function addBlankRows(&$rows) {
    $numBlankRows = 10;
    $emptySignatureCell = $this->getEmptySignatureCell();
    $blankRow = [];

    foreach ($rows[0] as $k => $v) {
      if (strpos($k, 'civicrm_contact_day') === 0) {
        $blankRow[$k] = $emptySignatureCell;
      }
      else {
        $blankRow[$k] = '';
      }
    }

    for ($i = 0; $i < $numBlankRows; $i++) {
      $rows[] = $blankRow;
    }
  }

  function getEventDuration($event) {
    $eventDuration = 'From ';

    $startDate = new DateTime($event['start_date']);
    $eventDuration .= $startDate->format('d-m-Y H:i');

    if (!empty($event['end_date'])) {
      $endDate = new DateTime($event['end_date']);
      $eventDuration .= ' Until ' . $endDate->format('d-m-Y H:i');
    }

    return $eventDuration;
  }

  function getEventLocation($event) {
    $eventLocationElements = [];

    if ($event['loc_block_id']) {
      $eventLocBlock = civicrm_api3('LocBlock', 'getsingle', ['id' => $event['loc_block_id']]);
      if ($eventLocBlock['address_id']) {
        $eventAddress = civicrm_api3('Address', 'getsingle', ['id' => $eventLocBlock['address_id']]);
        if ($eventAddress['street_address']) {
          $eventLocationElements[] = $eventAddress['street_address'];
        }
        if ($eventAddress['supplemental_address_1']) {
          $eventLocationElements[] = $eventAddress['supplemental_address_1'];
        }
        if ($eventAddress['supplemental_address_2']) {
          $eventLocationElements[] = $eventAddress['supplemental_address_2'];
        }
        if ($eventAddress['postal_code'] || $eventAddress['city']) {
          $eventLocationElements[] = trim($eventAddress['postal_code'] . ' ' . $eventAddress['city']);
        }
        if ($eventAddress['country_id']) {
          if (count($eventLocationElements) > 0 && $eventAddress['street_address'] != 'Online') {
            $country = civicrm_api3('Country', 'getsingle', [
              'id' => $eventAddress['country_id'],
              'return' => ['name'],
            ]);
            $eventLocationElements[] = $country['name'];
          }
        }
      }
    }

    if (count($eventLocationElements) == 0) {
      return '';
    }
    else {
      return implode(', ', $eventLocationElements);
    }
  }

  function getEventDetails() {
    $eventId = $this->getSelectedParam('event_value');

    $event = civicrm_api3('Event', 'getsingle', [
      'id' => $eventId,
    ]);

    // add custom field containing analytical number
    $result = civicrm_api3('CustomValue', 'get', [
      'sequential' => 1,
      'return' => ['custom_618'],
      'entity_id' => $eventId,
    ]);

    if ($result['count'] > 0) {
      $event['analytical_number'] = $result['values'][0]['latest'];
    }
    else {
      $event['analytical_number'] = '';
    }

    return $event;
  }

  function setEventDateToShow($event) {
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

    // if more than one day is selected, we show the start date
    // otherwise we show the start date + day offset
    $date = new DateTime($event['start_date']);
    if ($this->number_of_selected_days > 1) {
      $this->event_date = $date;
    }
    else {
      $this->event_date = $date->add(new DateInterval('P' . $dayOffset . 'D'));;
    }

    if (substr($event['start_date'], 0, 10) != substr($event['end_date'], 0, 10)) {
      $this->is_multi_day_event = TRUE;
    }
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
        $this->_columnHeaders['civicrm_contact_day1']['title'] = $this->event_date->format('j F Y');
      }
      if (array_key_exists('civicrm_contact_day2', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day2']['title'] = $d->add(new DateInterval('P1D'))->format('j F Y');
      }
      if (array_key_exists('civicrm_contact_day3', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day3']['title'] = $d->add(new DateInterval('P2D'))->format('j F Y');
      }
      if (array_key_exists('civicrm_contact_day4', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day4']['title'] = $d->add(new DateInterval('P3D'))->format('j F Y');
      }
      if (array_key_exists('civicrm_contact_day5', $this->_columnHeaders)) {
        $d = clone $this->event_date;
        $this->_columnHeaders['civicrm_contact_day5']['title'] = $d->add(new DateInterval('P4D'))->format('j F Y');
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

  function getEmptySignatureCell() {
    $cell = '';

    $numWidth = 80;
    $numHeight = 4;

    for ($i = 0; $i < $numWidth; $i++) {
      $cell .= '&nbsp;';
    }

    for ($i = 0; $i < $numHeight; $i++) {
      $cell .= '<br>';
    }

    return $cell;
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
