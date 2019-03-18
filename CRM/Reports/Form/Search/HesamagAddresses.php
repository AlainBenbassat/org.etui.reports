<?php

class CRM_Reports_Form_Search_HesamagAddresses extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  private $MEMBERSHIP_STATUS_CURRENT = 2;
  private $MEMBERSHIP_STATUS_NEW = 1;
  private $HESA_EN = 1;
  private $HESA_FR = 2;
  private $MAGAZINE_ADDRESS_TYPE_ID = 8;
  private $MAGAZINE2_ADDRESS_TYPE_ID = 9;
  private $INDIVIDUAL_PREFIX_GROUP_ID = 6;

  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  public function buildForm(&$form) {
    // add language filter
    $filter = [
      'EN' => 'English HesaMag',
      'FR' => 'French HesaMag',
      'EN+FR' => 'English and French HesaMag',
    ];
    $form->addRadio('langFilter', 'Subscribers of:', $filter,NULL,'<br>',TRUE);

    // add start date filter
    $form->add('datepicker', 'startDate', 'Membership start date from:', '', FALSE,['time' => FALSE]);

    $form->assign('elements', ['langFilter', 'startDate']);

    CRM_Utils_System::setTitle('HesaMag Addresses');
  }

  public function &columns() {
    $columns = array(
      'Contact Id' => 'contact_id',
      'Organization' => 'organization_name',
      'Prefix' => 'prefix',
      'First Name' => 'first_name',
      'Last Name' => 'last_name',
      'Address line 1' => 'supplemental_address_1',
      'Address line 2' => 'supplemental_address_2',
      'Address line 3' => 'supplemental_address_3',
      'Street' => 'street_address',
      'Postal Code' => 'postal_code',
      'City' => 'city',
      'Country' => 'country',
      'Magazine language' => 'magazine_lang',
    );
    return $columns;
  }

 public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //die($sql);
    return $sql;
  }

  public function select() {
    $values = $this->_formValues;

    $select = "
      contact_a.id as contact_id
      , contact_a.id
      , if(a.location_type_id = {$this->MAGAZINE2_ADDRESS_TYPE_ID}, '', contact_a.organization_name) organization_name
      , px.name prefix
      , contact_a.first_name
      , contact_a.last_name
      , a.supplemental_address_1
      , a.supplemental_address_2
      , a.supplemental_address_3
      , a.street_address
      , a.postal_code
      , a.city
      , ctry.name country
      , '" . $values['langFilter'] . "' magazine_lang
    ";

    return $select;
  }

  public function from() {
    $from = "
      FROM
        civicrm_contact contact_a
      LEFT OUTER JOIN
        civicrm_option_value px on px.value = contact_a.prefix_id and px.option_group_id = {$this->INDIVIDUAL_PREFIX_GROUP_ID}      
      LEFT OUTER JOIN
        civicrm_address a on a.contact_id = contact_a.id and a.location_type_id in ({$this->MAGAZINE_ADDRESS_TYPE_ID}, {$this->MAGAZINE2_ADDRESS_TYPE_ID})
      LEFT OUTER JOIN
        civicrm_country ctry on ctry.id = a.country_id
    ";

    return $from;
  }

  public function where($includeContactIDs = FALSE) {
    $values = $this->_formValues;

    if (array_key_exists('startDate', $values) && $values['startDate']) {
      $startDateFilter = "'{$values['startDate']}'";
    }
    else {
      $startDateFilter = "'2000-01-01'";
    }

    if ($values['langFilter'] == 'EN') {
      $where = "
        exists (
          select
            *
          from
            civicrm_membership hesa_en
          where
            hesa_en.contact_id = contact_a.id
          and
            hesa_en.membership_type_id = {$this->HESA_EN}
          and
            hesa_en.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_en.start_date >= $startDateFilter
        )
        and not exists (
          select
            *
          from
            civicrm_membership hesa_fr
          where
            hesa_fr.contact_id = contact_a.id
          and
            hesa_fr.membership_type_id = {$this->HESA_FR}
          and
            hesa_fr.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_fr.start_date >= $startDateFilter                    
        )
      ";
    }
    elseif ($values['langFilter'] == 'FR') {
      $where = "
        not exists (
          select
            *
          from
            civicrm_membership hesa_en
          where
            hesa_en.contact_id = contact_a.id
          and
            hesa_en.membership_type_id = {$this->HESA_EN}
          and
            hesa_en.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_en.start_date >= $startDateFilter
        )
        and exists (
          select
            *
          from
            civicrm_membership hesa_fr
          where
            hesa_fr.contact_id = contact_a.id
          and
            hesa_fr.membership_type_id = {$this->HESA_FR}
          and
            hesa_fr.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_fr.start_date >= $startDateFilter                    
        )
      ";
    }
    else {
      $where = "
        exists (
          select
            *
          from
            civicrm_membership hesa_en
          where
            hesa_en.contact_id = contact_a.id
          and
            hesa_en.membership_type_id = {$this->HESA_EN}
          and
            hesa_en.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_en.start_date >= $startDateFilter
        )
        and exists (
          select
            *
          from
            civicrm_membership hesa_fr
          where
            hesa_fr.contact_id = contact_a.id
          and
            hesa_fr.membership_type_id = {$this->HESA_FR}
          and
            hesa_fr.status_id in ({$this->MEMBERSHIP_STATUS_NEW}, {$this->MEMBERSHIP_STATUS_CURRENT})
          and
            hesa_fr.start_date >= $startDateFilter                    
        )
      ";
    }

    $where .= ' and contact_a.is_deleted = 0';

    $params = [];
    return $this->whereClause($where, $params);
  }

  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }
}
