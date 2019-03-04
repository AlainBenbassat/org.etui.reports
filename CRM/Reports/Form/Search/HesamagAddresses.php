<?php
use CRM_Reports_ExtensionUtil as E;

class CRM_Reports_Form_Search_HesamagAddresses extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  public function buildForm(&$form) {
    // add language filter
    $form->addCheckBox('langFilter', 'Magazine Language', ['English' => 'en', 'French' => 'fr']);

    $form->assign('elements', ['langFilter']);

    CRM_Utils_System::setTitle(E::ts('HesaMag Addresses'));
  }

  public function &columns() {
    $columns = array(
      //E::ts('Contact Id') => 'contact_id',
      E::ts('Name') => 'sort_name',
      E::ts('Address line 1') => 'supplemental_address_1',
      E::ts('Address line 2') => 'supplemental_address_2',
      E::ts('Address line 3') => 'supplemental_address_3',
      E::ts('Street') => 'street_address',
      E::ts('Postal Code') => 'postal_code',
      E::ts('City') => 'city',
      E::ts('Country') => 'country',
      'EN' => 'EN',
      'FR' => 'FR',
    );
    return $columns;
  }

 public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    //die($sql);
    return $sql;
  }

  public function select() {
    $select = "
      contact_a.id as contact_id
      , contact_a.sort_name
      , a.supplemental_address_1
      , a.supplemental_address_2
      , a.supplemental_address_3
      , a.street_address
      , a.postal_code
      , a.city
      , ctry.name country
      , if(hesa_en.id is null, '', 'EN') EN
      , if(hesa_fr.id is null, '', 'FR') FR
    ";

    return $select;
  }


  public function from() {
    $MAGAZINE_ADDRESS_TYPE_ID = 8;
    $MEMBERSHIP_STATUS_CURRENT = 2;
    $HESA_EN = 1;
    $HESA_FR = 2;

    $from = "
      FROM
        civicrm_contact contact_a
      INNER JOIN
        civicrm_address a on a.contact_id = contact_a.id and a.location_type_id = $MAGAZINE_ADDRESS_TYPE_ID
      LEFT OUTER JOIN
        civicrm_country ctry on ctry.id = a.country_id
      LEFT OUTER JOIN
        civicrm_membership hesa_en on hesa_en.contact_id = contact_a.id and hesa_en.membership_type_id = $HESA_EN and hesa_en.status_id = $MEMBERSHIP_STATUS_CURRENT
      LEFT OUTER JOIN
        civicrm_membership hesa_fr on hesa_fr.contact_id = contact_a.id and hesa_fr.membership_type_id = $HESA_FR and hesa_fr.status_id = $MEMBERSHIP_STATUS_CURRENT
    ";

    return $from;
  }

  public function where($includeContactIDs = FALSE) {
    $values = $this->_formValues;

    // check if the language filter is set
    if (array_key_exists('langFilter', $values)) {
      $where = '';

      if (array_key_exists('en', $values['langFilter'])) {
        $where = "hesa_en.id is not null";
      }

      if (array_key_exists('fr', $values['langFilter'])) {
        if ($where) {
          $where .= ' or ';
        }
        $where .= "hesa_fr.id is not null";
      }
    }
    else {
      // no language filter is set, select all
      $where = "hesa_en.id is not null or hesa_fr.id is not null";
    }

    $params = [];
    return $this->whereClause($where, $params);
  }

  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }
}
