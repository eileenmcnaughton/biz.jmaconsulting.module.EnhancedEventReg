<?php
/*
 +--------------------------------------------------------------------+
 | EnhancedEventReg version 0.2                                       |
 +--------------------------------------------------------------------+
 | Copyright Joseph Murray & Associates Consulting Ltd. (c) 2012      |
 +--------------------------------------------------------------------+
 | This file is not a part of CiviCRM, but a separate work that       |
 | is designed to integrate with CiviCRM's extension framework        |
 | API.
 |                                                                    |
 | EnhancedEventReg is free software; you can copy, modify, and       |
 | distribute itunder the terms of the GNU Affero General Public      |
 | License Version 3, 19 November 2007.                               |
 |                                                                    |
 | EnhancedEventReg is distributed in the hope that it will be        |
 | useful, butWITHOUT ANY WARRANTY; without even the implied warranty |
 | of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.            |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License with this program; if not, contact JMA Consulting          |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package EnhancedEventReg
 * @copyright Joseph Murray & Associates Consulting Ltd. (c) 2012
 * $Id$
 *
 */

/**
   * Implementation of hook_civicrm_install( )
   */
function EnhancedEventReg_civicrm_install( ) {
  $dirRoot =dirname( __FILE__ );
  $dirSQL = $dirRoot . DIRECTORY_SEPARATOR .'EnhancedEventReg.sql';
  CRM_Utils_File::sourceSQLFile( CIVICRM_DSN, $dirSQL );
  CRM_Core_Invoke::rebuildMenuAndCaches( );
  }

/**
 * Implementation of hook_civicrm_uninstall( )
 */
function EnhancedEventReg_civicrm_uninstall( ) {
  $dirRoot =dirname( __FILE__ );
  $dirSQL =$dirRoot . DIRECTORY_SEPARATOR .'EnhancedEventReg.uninstall.sql';
  CRM_Utils_File::sourceSQLFile( CIVICRM_DSN,$dirSQL );
}

function EnhancedEventReg_civicrm_config( &$config ) {
  $dirRoot = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
  // fix php include path
  $include_path = $dirRoot .'php'. PATH_SEPARATOR . get_include_path( );
  set_include_path( $include_path );
  // fix template path
  $template = & CRM_Core_Smarty::singleton( );
  $templateDir = $dirRoot .'template'.DIRECTORY_SEPARATOR;
  array_unshift( $template->template_dir, $templateDir );
}

function EnhancedEventReg_civicrm_buildForm( $formName, &$form  )  {
  require_once 'api/api.php';
  $childName  = array( 1 => 'Second', 2 => 'Third', 3 => 'Fourth', 4 => 'Fifth', 5 => 'Sixth', 6 => 'Seventh');
  $paramNames =  array( 'first_name', 'last_name' );
 
  if( $formName == 'CRM_Event_Form_Registration_AdditionalParticipant' ) {
    $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = {$form->_eventId}" );
    if ( $is_enhanced ) {
      foreach( $form->_fields as $fieldKey => $fieldValues ) {
          $partcipantCount = $form->getVar('_name');
          $pCount = explode('_', $partcipantCount);
          $fieldValues['groupTitle'] = $childName[$pCount[1]].' Child';
          $firstChild[$fieldKey] = $fieldValues;
      } 
      $form->assign( 'additionalCustomPre', $firstChild );
    }
  }


  if( $formName == 'CRM_Event_Form_Registration_Confirm' || $formName == 'CRM_Event_Form_Registration_ThankYou' ) { 
    
    $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = {$form->_eventId}" );
    if ( $is_enhanced ) {
      $profiles = CRM_Core_DAO::executeQuery(" SELECT id FROM civicrm_uf_group WHERE title = 'Current User Profile' OR  title = 'Other Parent Or Guardian' OR  title = 'First Emergency Contacts' OR  title = 'Second Emergency Contacts'");
      
      while( $profiles->fetch( ) ) {
        $ids[] = $profiles->id;
      }
      $contacts = $form->getVar('_params');
      foreach ( $ids as $profileKey => $profileID ) {
        $id[$profileKey] = $profileID;
        $form->buildCustom( $id , 'customPost' );
        unset($id[$profileKey]);
        $fields = CRM_Core_BAO_UFGroup::getFields( $profileID, false, CRM_Core_Action::ADD,
                                                   null , null, false, null,
                                                   false, null, CRM_Core_Permission::CREATE,
                                                   'field_name', true );
        foreach( $fields as $key => $value ) {
          if (in_array( $key, $paramNames ) ) {
            $newKey = $key.$profileKey;
          } elseif ( strstr( $key , 'custom' ) ){ 
            $newKey = $key.'-'.$profileKey;
          } else {
            $fieldName = explode( '-', $key );
            $newKey = $fieldName[0].$profileKey;
          }
          $form->_fields[$key]['name'] = $newKey;
          $form->_fields[$key]['where'] = '';
          if ($profileKey == 0 ) {
            $form->_fields[$key]['groupTitle'] = $value['groupTitle'];
            $form->_fields[$key]['group_id'] = $value['group_id'];
          }
          if ( !empty( $contacts[0][$newKey] ) ) {
            $form->_fields[$newKey] = $form->_fields[$key];
            $form->_elementIndex[$newKey] = $form->_elementIndex[$key];
            $form->_elements[$form->_elementIndex[$key]]->_attributes['name'] = $newKey;
            $form->_elements[$form->_elementIndex[$key]]->_attributes['value'] = $contacts[0][$newKey];
            $form->_elements[$form->_elementIndex[$key]]->_values = array( $contacts[0][$newKey] );
            if ( array_key_exists('multiple', $form->_elements[$form->_elementIndex[$key]]->_attributes) ) {
              $form->_elements[$form->_elementIndex[$newKey]]->_values = $contacts[0][$newKey];
            } else {
              $form->_elements[$form->_elementIndex[$newKey]]->_values = array( $contacts[0][$newKey] );
            }
            if ( array_key_exists('_elements', (array) $form->_elements[$form->_elementIndex[$key]]) ) {
              foreach ($form->_elements[$form->_elementIndex[$newKey]]->_elements as $readioKey => $radioVal ) {
                if ($radioVal->_type == 'radio' ) {
                  if ( $contacts[0][$newKey] == $readioKey+1 ) {
                    $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
                  } else {
                    $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = '';
                  }
                }
                if ($radioVal->_type == 'checkbox' ) {
                  if ( array_key_exists( $readioKey+1, $contacts[0][$newKey]) ) {
                    if ( !empty($contacts[0][$newKey][$readioKey+1]) ) {
                      $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
                    } else {
                      $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = '';
                    }
                  }
                } 
              }
              $form->_elements[$form->_elementIndex[$key]]->_name = $newKey;
            }
          }
          //$form->_rules[$newKey] = $form->_rules[$key];
          unset( $form->_elementIndex[$key] );
          unset( $form->_fields[$key] );
          unset( $form->_rules[$key] );
        } 
      }
      $myFields = $form->_fields;
      foreach($myFields as $myKey => $myVal ) {
        if( strstr( $myKey, 'billing') || strstr( $myKey, 'credit') || strstr( $myKey, 'cvv') ) {
          unset($myFields[$myKey]);
        }
      }
      
      $form->assign( 'customPost', $myFields );

      $form->buildCustom( 4 , 'customPre' ); 
      $fields = CRM_Core_BAO_UFGroup::getFields( 4, false, CRM_Core_Action::ADD,
                                                 null , null, false, null,
                                                 false, null, CRM_Core_Permission::CREATE,
                                                 'field_name', true );
      foreach( $fields as $fieldKey => $fieldValues ) {
        $form->_fields[$fieldKey]['groupTitle'] = 'First Child';
        $firstChild[$fieldKey] = $form->_fields[$fieldKey];
        $form->_elements[$form->_elementIndex[$fieldKey]]->_attributes['value'] = $contacts[0][$fieldKey]; 
        if ( array_key_exists('multiple', $form->_elements[$form->_elementIndex[$fieldKey]]->_attributes) ) {
          $form->_elements[$form->_elementIndex[$fieldKey]]->_values = $contacts[0][$fieldKey];
        } else {
          $form->_elements[$form->_elementIndex[$fieldKey]]->_values = array( $contacts[0][$fieldKey] );
        }
          
        if ( array_key_exists('_elements', (array) $form->_elements[$form->_elementIndex[$fieldKey]]) ) {
          foreach ($form->_elements[$form->_elementIndex[$fieldKey]]->_elements as $readioKey => $radioVal ) {
            if ($radioVal->_type == 'radio' ) {
              if ( $contacts[0][$fieldKey] == $readioKey+1 ) {
                $form->_elements[$form->_elementIndex[$fieldKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
              } else {
                $form->_elements[$form->_elementIndex[$fieldKey]]->_elements[$readioKey]->_attributes['checked'] = '';
              }
            }
            if ($radioVal->_type == 'checkbox' ) {
              if ( array_key_exists( $readioKey+1, $contacts[0][$fieldKey]) ) {
                if ( !empty($contacts[0][$fieldKey][$readioKey+1]) ) {
                  $form->_elements[$form->_elementIndex[$fieldKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
                } else {
                  $form->_elements[$form->_elementIndex[$fieldKey]]->_elements[$readioKey]->_attributes['checked'] = '';
                }
              }
            } 
          }
        }
        if ( $form->_elements[$form->_elementIndex[$fieldKey]]->_type == 'file' ) {
          unset($form->_elements[$form->_elementIndex[$fieldKey]]);
          unset($firstChild[$fieldKey]);
        }
      } 
      
      $form->assign( 'customPre', $firstChild );
      
      foreach ( $contacts as $contactKey => $contactValue ) {
        if ( $contactKey != 0 ) {
          foreach( $contactValue as $contKey => $contVal ) {
            if( array_key_exists( $contKey, $form->_elementIndex ) ) {
              if ( array_key_exists('_elements', (array) $form->_elements[$form->_elementIndex[$contKey]]) ) {
                $checkCount = 1;
                foreach ( $form->_elements[$form->_elementIndex[$contKey]]->_elements as $addKey  => $addVal ) {
                  if ($addVal->_type == 'radio' ) {
                    if ( $addKey+1 == $contVal ) {
                      $additionalParticipants[$contactKey]['additionalCustomPre'][$form->_elements[$form->_elementIndex[$contKey]]->_label] = $addVal->_text;
                    }
                  } 
                  if ( $addVal->_type == 'checkbox' ) {
                    $checkBoxFields[$checkCount] = $addVal->_text;
                    $checkCount++;
                  }
                }
                $check =  null;
                if ( !empty ( $checkBoxFields ) ) {
                  foreach ( $contVal as $checkKey => $checkVal ) {
                    if (!empty($checkVal))  {
                      $check = $check.', '.$checkBoxFields[$checkKey];
                    }
                  }
                  $check = ltrim($check, ', ');
                  $additionalParticipants[$contactKey]['additionalCustomPre'][$form->_elements[$form->_elementIndex[$contKey]]->_label] = $check;
                  $checkBoxFields =  Array( );
                }
              
              } else {
              
                foreach ( $form->_elements[$form->_elementIndex[$contKey]] as $addKey  => $addVal ) {
                  if ( $form->_elements[$form->_elementIndex[$contKey]]->_type == 'select' ) {
                    if ( array_key_exists('multiple', $form->_elements[$form->_elementIndex[$contKey]]->_attributes) ) { 
                      $checkCount = 1;
                      foreach( $form->_elements[$form->_elementIndex[$contKey]]->_options as $optionVal ) {
                      
                        $multipleFields[$checkCount] = $optionVal['text'];
                        $checkCount++;
                      }
                    } else {
                      $additionalParticipants[$contactKey]['additionalCustomPre'][$form->_elements[$form->_elementIndex[$contKey]]->_label] = $form->_elements[$form->_elementIndex[$contKey]]->_options[$contVal]['text'];
                    }
                  } elseif( $form->_elements[$form->_elementIndex[$contKey]]->_type != 'hidden' ) {
                    if ( !empty($contVal) && !empty( $form->_elements[$form->_elementIndex[$contKey]]->_label ) ) {
                      $additionalParticipants[$contactKey]['additionalCustomPre'][$form->_elements[$form->_elementIndex[$contKey]]->_label] = $contVal;
                    }
                  } 
                  $check = null;
                  if ( !empty ( $multipleFields ) ) {
                    foreach ( $contVal as $checkKey => $checkVal ) {
                      $check = $check.', '.$multipleFields[$checkVal];
                    }
                    $check = ltrim($check, ', ');
                    $additionalParticipants[$contactKey]['additionalCustomPre'][$form->_elements[$form->_elementIndex[$contKey]]->_label]= $check;
                    $multipleFields = Array();
                  }
                }
              }
            }
            $additionalParticipants[$contactKey]['additionalCustomPreGroupTitle'] = $childName[$contactKey].' Child';
          } 
        }
      }
      if ( !empty($additionalParticipants) ) {
        $form->assign( 'addParticipantProfile', $additionalParticipants );
      }
    } 
    require_once 'api/api.php';
    $config =& CRM_Core_Config::singleton( );
    $config->_form = $form;
    $config->_is_shareAdd = $contacts[0]['is_shareAdd'];
    $config->_is_spouse   = $contacts[0]['is_spouse'];
  }

  if( $formName == 'CRM_Event_Form_Registration_Register' ) {
    $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = {$form->_eventId}" );
    if ( $is_enhanced ) {
      $profiles = CRM_Core_DAO::executeQuery(" SELECT id FROM civicrm_uf_group WHERE title = 'Current User Profile' OR  title = 'Other Parent Or Guardian' OR  title = 'First Emergency Contacts' OR  title = 'Second Emergency Contacts'");
      
      while( $profiles->fetch( ) ) {
        $ids[] = $profiles->id;
      }
      $option = array( '1' =>'Yes', '0' => "No" );
      $form->addRadio('is_spouse' , ts( 'Is my Spouse' ),$option, NULL,  NULL, FALSE);
      $form->addRadio('is_shareAdd',ts( 'Shares My Address' ),$option, NULL,  NULL, FALSE);
      $form->assign('addshareNspouse' , $is_enhanced );
      $contacts = $form->_submitValues;
      foreach ( $ids as $profileKey => $profileID ) {
        $id[$profileKey] = $profileID;
        $form->buildCustom( $id , 'customPost' );
        unset($id[$profileKey]);
        $fields = CRM_Core_BAO_UFGroup::getFields( $profileID, false, CRM_Core_Action::ADD,
                                                   null , null, false, null,
                                                   false, null, CRM_Core_Permission::CREATE,
                                                   'field_name', true );
       
        foreach( $fields as $key => $value ) {
          if (in_array( $key, $paramNames ) ) {
            $newKey = $key.$profileKey;
          } elseif ( strstr( $key , 'custom' ) ){ 
            $newKey = $key.'-'.$profileKey;
          } else {
            $fieldName = explode( '-', $key );
            $newKey = $fieldName[0].$profileKey;
          }
          $form->_fields[$key]['name'] = $newKey;
          $form->_fields[$key]['where'] = '';
          if ($profileKey == 0 ) {
            $form->_fields[$key]['groupTitle'] = $value['groupTitle'];
            $form->_fields[$key]['group_id'] = $value['group_id'];
          }
          $form->_fields[$newKey] = $form->_fields[$key];
          $form->_elementIndex[$newKey] = $form->_elementIndex[$key];
          $form->_elements[$form->_elementIndex[$key]]->_attributes['name'] = $newKey;
          if ( !empty( $form->_submitValues ) ) {
            $form->_elements[$form->_elementIndex[$key]]->_attributes['value'] = $form->_submitValues[$newKey];
          }
          $form->_elements[$form->_elementIndex[$key]]->_name = $newKey;
          //$form->_rules[$newKey] = $form->_rules[$key];
          if ( !empty( $contacts )) {
            if ( array_key_exists('multiple', $form->_elements[$form->_elementIndex[$newKey]]->_attributes) ) {
              $form->_elements[$form->_elementIndex[$newKey]]->_values = $contacts[$newKey];
            } else {
              $form->_elements[$form->_elementIndex[$newKey]]->_values = array( $contacts[$newKey] );
            }
            if ( array_key_exists('_elements', (array) $form->_elements[$form->_elementIndex[$key]]) ) {
              foreach ($form->_elements[$form->_elementIndex[$newKey]]->_elements as $readioKey => $radioVal ) {
                if ($radioVal->_type == 'radio' ) {
                  if ( $contacts[$newKey] == $readioKey+1 ) {
                    $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
                  } else {
                    $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = '';
                  }
                }
                if ($radioVal->_type == 'checkbox' ) {
                  if ( array_key_exists( $readioKey+1, $contacts[$newKey]) ) {
                    if ( !empty($contacts[$newKey][$readioKey+1]) ) {
                      $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = 'checked';
                    } else {
                      $form->_elements[$form->_elementIndex[$newKey]]->_elements[$readioKey]->_attributes['checked'] = '';
                    }
                  }
                } 
              }
            }
          }
          foreach ( $form->_required as $reqKey => $reqValue ) {
            if ( in_array( $reqValue, $paramNames ) ) {
              $form->_required[$reqKey] = $reqValue.$profileKey;
            } elseif ( $reqValue == 'email-Primary') {
              $fieldName = explode( '-', $reqValue );
              $form->_required[$reqKey] = $fieldName[0].$profileKey;
            }
          }   
          unset( $form->_defaultValues[$key] );
          unset( $form->_defaults[$key] );
          unset( $form->_elementIndex[$key] );
          unset( $form->_fields[$key] );
          unset( $form->_rules[$key] );
        } 
      }
      
      $myFields = $form->_fields;
      foreach($myFields as $myKey => $myVal ) {
        if( strstr( $myKey, 'billing') || strstr( $myKey, 'credit') || strstr( $myKey, 'cvv') ) {
          unset($myFields[$myKey]);
        }
      }
      $form->assign( 'customPost', $myFields );
      //$form->assign( 'customPost', $form->_fields );
      $form->buildCustom( 4 , 'customPre' );
      $fields = CRM_Core_BAO_UFGroup::getFields( 4, false, CRM_Core_Action::ADD,
                                                 null , null, false, null,
                                                 false, null, CRM_Core_Permission::CREATE,
                                                 'field_name', true );
      
      foreach( $fields as $fieldKey => $fieldValues ) {
        $form->_fields[$fieldKey]['groupTitle'] = 'First Child';
        $firstChild[$fieldKey] = $form->_fields[$fieldKey];
      } 
      $form->assign( 'customPre', $firstChild );
    } 
  }
  
  if( $formName == 'CRM_Event_Form_ManageEvent_Registration' ) {
    $form->addElement( 'checkbox', 'is_enhanced', ts( 'Use Enhanced Registration?' ) );
    $eventID = $form->_id;
    $is_enhanced = null;
    // $is_multiple = null;
    $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = $eventID" );
    //$is_multiple = CRM_Core_DAO::singleValueQuery( "SELECT is_multiple_registrations FROM civicrm_event WHERE id = $eventID" );
    $defaults['is_enhanced'] = $is_enhanced;
    //$defaults['is_multiple_registrations'] = $is_multiple;
    $form->setDefaults( $defaults );  
  }
}

// function EnhancedEventReg_civicrm_validate( $formName, &$fields, &$files, &$form )  {
 
//   if( $formName == 'CRM_Event_Form_Registration_Register' ) {
//     $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = {$form->_eventId}" );
//     if ( $is_enhanced ) {
//       $getContactsParams0 = array(
//                                   'first_name' => $form->_submitValues['first_name0'] ,
//                                   'last_name' => $form->_submitValues['last_name0'] ,
//                                   'contact_type' => 'Individual',
//                                   'email' => $form->_submitValues['email0'] ,
//                                   'version' => 3
//                                   );
 
//       require_once 'api/api.php';
//       $getContactsResult0 = civicrm_api('contact', 'get', $getContactsParams0 );
//       if( $getContactsResult0['values'] ) {
//         $errors['email0'] = ts( 'Contact is already exists!' );
//       }

//       $getContactsParams1 = array(
//                                   'first_name' => $form->_submitValues['first_name1'] ,
//                                   'last_name' => $form->_submitValues['last_name1'],
//                                   'contact_type' => 'Individual',
//                                   'email' => $form->_submitValues['email1'] ,
//                                   'version' => 3
//                                   );
    
//       $getContactsResult1 = civicrm_api('contact', 'get', $getContactsParams1 );
//       if( $getContactsResult1['values'] ) {
//         $errors['email1'] = ts( 'Contact is already exists!' );
//       }

//       $getContactsParams2 = array(
//                                   'first_name' => $form->_submitValues['first_name2']  ,
//                                   'last_name' => $form->_submitValues['last_name2'],
//                                   'contact_type' => 'Individual',
//                                   'email' => $form->_submitValues['email2'],
//                                   'version' => 3
//                                   );
//       require_once 'api/api.php';
//       $getContactsResult2 = civicrm_api('contact', 'get', $getContactsParams2 );
//       if( $getContactsResult2['values'] ) {
//         $errors['email2'] = ts( 'Contact is already exists!' );
//       }

//       $getContactsParams3 = array(
//                                   'first_name' => $form->_submitValues['first_name3'] ,
//                                   'last_name' => $form->_submitValues['last_name3'],
//                                   'contact_type' => 'Individual',
//                                   'email' => $form->_submitValues['email3'],
//                                   'version' => 3
//                                   );
//       require_once 'api/api.php';
//       $getContactsResult3 = civicrm_api('contact', 'get', $getContactsParams3 );
//       if( $getContactsResult3['values'] ) {
//         $errors['email3'] = ts( 'Contact is already exists!' );
//       }
//     }
//   }
//   return empty($errors) ? true : $errors;
// }

function EnhancedEventReg_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  if ( $objectName == 'Participant' && $op == 'create' ) {
    require_once 'api/api.php';
    $config =& CRM_Core_Config::singleton( );
    $form   = $config->_form;
    $parts  = count($form->_part);
    $config->_participants[] = $objectId;
    $participants = $config->_participants;
    $config->_pContactId[$objectId] = $objectRef->contact_id;
    $pContactIds = $config->_pContactId;
    $pCount      = count($participants);
    $is_shareAdd = $config->_is_shareAdd;
    $is_spouse   = $config->_is_spouse;
    if ( $parts == $pCount ) {
      $is_enhanced = CRM_Core_DAO::singleValueQuery( "SELECT is_enhanced FROM civicrm_event_enhanced WHERE event_id = {$objectRef->event_id}" );
      if ( $is_enhanced ) {
        require_once 'api/api.php';
        $participantIds = $participants;
        foreach( $participantIds as $partKey => $partID ) {
          $contactA = $pContactIds[$partID];
          unset($participantIds[$partKey]);
          foreach( $participantIds as $partKey => $partID ) {
            $contactB = $pContactIds[$partID];
            $siblingParams = array( 
                                   'contact_id_a'         => $contactA,
                                   'contact_id_b'         => $contactB,
                                   'relationship_type_id' => 3,
                                   'is_active'            => 1,
                                   'version'              => 3,
                                    );
            $siblingResult = civicrm_api( 'relationship', 'create', $siblingParams );
          }
        }
    
        $participantIds = $participants;
        foreach( $participantIds as $key => $pID ) {
          if ( $key == 0 ) {
            $otherParams = array();
            for( $i = 0; $i < 4; $i++ ) { 
              $createContactsParams = 'createContactsParams'.$i;
              $createContactsResult = 'createContactsResult'.$i;
              $contactsCustomParams = $otherParams = array();
              $checkData = array(
                                 'first_name'   => $form->_submitValues['first_name'.$i] ,
                                 'last_name'    => $form->_submitValues['last_name'.$i],
                                 'contact_type' => 'Individual',
                                 'email'        => $form->_submitValues['email'.$i],
                                 'version'      => 3
                                 );
              $checkDupResult = civicrm_api( 'contact', 'get', $checkData );
              if( !empty($checkDupResult['values'] ) ) {
                $checkData['id'] = $checkDupResult['id'];
                $addressParams['contact_id'] = $checkDupResult['id'];
                $addressParams['version']    = 3;
                $addressParams['location_type_id'] = 1;
                $addressResult = civicrm_api( 'address', 'get', $addressParams );
                if( !empty($addressResult['values'] ) ) {
                  $otherParams[$i]['api.address.create']['id'] = $addressResult['id'];
                }
              }
              $$createContactsParams = $checkData;
            
              foreach( $form->_submitValues as $contactsKeys => $contactsValues ) {
                $search = '-'.$i;
                if ( strstr( $contactsKeys, $search) ) {
                  $contactsCustomParams[$contactsKeys] = $contactsValues;
                } else {
                  if ( ! strstr($contactsKeys, 'custom') && ! strstr($contactsKeys, 'first_name') && ! strstr($contactsKeys, 'last_name') && ! strstr($contactsKeys, 'email')) {
                    if ( $i == 0 ) {
                      $otherParams[$i]['api.address.create']['street_address'] = $form->_submitValues['street_address0'];
                      $otherParams[$i]['api.address.create']['city']           = $form->_submitValues['city0'];
                      $otherParams[$i]['api.address.create']['state_province'] = $form->_submitValues['state_province0'];
                      $otherParams[$i]['api.address.create']['postal_code']    = $form->_submitValues['postal_code0'];
                      $otherParams[$i]['api.address.create']['location_type_id'] = 1;
                    }
                    if ( strstr( $contactsKeys, (string)$i )) {
                      $newKey = rtrim( $contactsKeys, $i );
                      $otherParams[$i]['api.address.create'][$newKey] = $contactsValues;
                      $otherParams[$i]['api.address.create']['location_type_id'] = 1;
                    }
                  }
                }
              }
              if ( $i == 1 && $is_shareAdd ) {
                $addressGet['contact_id'] =  $createContactsResult0['id'];
                $addressGet['version']    =  3;
                $addressGet['location_type_id'] = 1;
                $result = civicrm_api( 'address','get' , $addressGet );
                $otherParams[$i]['api.address.create']['master_id'] = $result['id'];
                $shareOtherParams = $otherParams[$i];
                $shareOtherParams['id'] = $pContactIds[$pID];
                $shareOtherParams['version'] = 3;
                $childAddress = civicrm_api( 'contact', 'create', $shareOtherParams );
              }
              $contactData = $$createContactsParams;
              if ( !empty($contactsCustomParams) ) {
                $contactData = array_merge($contactData, $contactsCustomParams );
              }
              if( !empty( $otherParams )) {
                $contactData = array_merge($contactData, $otherParams[$i] );
              }
            
              $contactsCustomParams = array();
              $$createContactsResult = civicrm_api( 'contact', 'create', $contactData );
            }
            $child   = $pContactIds[$pID];
            $current = $createContactsResult0['id'];
            $other   = $createContactsResult1['id'];
            $firstEmergency  = $createContactsResult2['id'];
            $secondEmergency = $createContactsResult3['id'];
            if( $is_shareAdd == 1 ) { 
              if ( $form->_submitValues['last_name0'] == $form->_submitValues['last_name1'] ) {
                $houseHoldName = $form->_submitValues['first_name0'].' & '.$form->_submitValues['first_name1'].' '.$form->_submitValues['last_name0'].' Household';
              } else {
                $houseHoldName = $form->_submitValues['first_name0'].' '.$form->_submitValues['last_name0'].', '.$form->_submitValues['first_name1'].' '.$form->_submitValues['last_name1'].' Household';
              }
              
              $createHouseholdParams = array(
                                             'household_name' => $houseHoldName,
                                             'contact_type'   => 'Household',
                                             'email'          => $form->_submitValues['email1'],
                                             'version'        => 3
                                             );
              $getHouseholdResult = civicrm_api( 'contact', 'get', $createHouseholdParams ); 
              if( !empty($getHouseholdResult['values']) ) {
                $createHouseholdParams['id'] = $getHouseholdResult['id'];
              }
              if( !empty( $shareOtherParams )) {
                unset( $shareOtherParams['id'] );
                $createHouseholdParams= array_merge( $createHouseholdParams, $shareOtherParams );
              }
              $createHouseholdResult = civicrm_api( 'contact', 'create', $createHouseholdParams );  
        
              $household = $createHouseholdResult['id'];
              $householdHeadRelationshipParams = array( 
                                                       'contact_id_a'         => $current,
                                                       'contact_id_b'         => $household,
                                                       'relationship_type_id' => 6,
                                                       'is_permission_a_b'    => 1,
                                                       'is_permission_b_a'    => 1,
                                                       'is_active'            => 1,
                                                       'version'              => 3,
                                                        );
          
              $householdCurrentHeadRelationshipResult = civicrm_api( 'relationship', 'create', $householdHeadRelationshipParams );
          
              $householdHeadRelationshipParams['contact_id_a'] = $other;
              $householdHeadRelationshipParams['relationship_type_id'] = 7;
              $householdOtherHeadRelationshipResult = civicrm_api( 'relationship', 'create', $householdHeadRelationshipParams );
          
              $householdMemberRelationshipParams = array( 
                                                         'contact_id_a'         => $child,
                                                         'contact_id_b'         => $household,
                                                         'relationship_type_id' => 7,
                                                         'is_permission_b_a'    => 1,
                                                         'is_active'            => 1,
                                                         'version'              => 3,
                                                          );
        
              $householdMemberRelationshipResult = civicrm_api( 'relationship', 'create', $householdMemberRelationshipParams );
            }
      
            if( $is_spouse == 1 ) {
              $spouseRelationshipParams = array( 
                                                'contact_id_a'         => $current,
                                                'contact_id_b'         => $other,
                                                'relationship_type_id' => 2,
                                                'is_active'            => 1,
                                                'is_permission_a_b'    => 1,
                                                'is_permission_b_a'    => 1,
                                                'version'              => 3,
                                                 );
              $spouseRelationshipResult = civicrm_api( 'relationship', 'create', $spouseRelationshipParams);
            }
       
            $parentRelationshipParams = array( 
                                              'contact_id_a'         => $child,
                                              'contact_id_b'         => $current,
                                              'relationship_type_id' => 1,
                                              'is_active'            => 1,
                                              'is_permission_b_a'    => 1,
                                              'version'              => 3,
                                               );
      
            $parentRelationshipResult = civicrm_api( 'relationship', 'create', $parentRelationshipParams );
      
            // create relationship between child and other parent
            $otherParentRelationshipParams = array( 
                                                   'contact_id_a'         => $child,
                                                   'contact_id_b'         => $other,
                                                   'relationship_type_id' => 1,
                                                   'is_active'            => 1,
                                                   'is_permission_b_a'    => 1,
                                                   'version'              => 3,
                                                    );
      
            $otherParentRelationshipResult = civicrm_api( 'relationship', 'create', $otherParentRelationshipParams );
      
            // create a new relationship type 'is emergency contact of'
            $emergencyTypeParams = array( 
                                         'name_a_b'       => 'emergency contact is',
                                         'name_b_a'       => 'emergency contact for',
                                         'contact_type_a' => 'Individual',
                                         'contact_type_b' => 'Individual',
                                         'is_reserved'    => 0,
                                         'is_active'      => 1,
                                         'version'        => 3,
                                          );
      
            $getEmergencyTypeResult = civicrm_api( 'relationship_type', 'get', $emergencyTypeParams );
            if( $getEmergencyTypeResult['count'] == 0 ) {
              $createEmergencyTypeResult = civicrm_api( 'relationship_type', 'create', $emergencyTypeParams );
              $relId = $createEmergencyTypeResult['id'];
            }
            else {
              $relId = $getEmergencyTypeResult['id'];
            }
            // create relationship of first emergency contact and child
            $emergency1ChildRelationshipParams = array( 
                                                       'contact_id_a'         => $child,
                                                       'contact_id_b'         => $firstEmergency,
                                                       'relationship_type_id' => $relId.'_a_b',
                                                       'is_active'            => 1,
                                                       'contact_check'        => array( $firstEmergency => $firstEmergency ),
                                                        );
            $ids['contact'] = $child;
            CRM_Contact_BAO_Relationship::create(&$emergency1ChildRelationshipParams, &$ids);
      
            // create relationship of second emergency contact and child
            $emergency2ChildRelationshipParams = array( 
                                                       'contact_id_a'         => $child,
                                                       'contact_id_b'         => $secondEmergency,
                                                       'relationship_type_id' => $relId.'_a_b',
                                                       'is_active'            => 1,
                                                       'contact_check'        => array( $secondEmergency => $secondEmergency ),
                                                        );

            CRM_Contact_BAO_Relationship::create(&$emergency2ChildRelationshipParams, &$ids);
          } else {
            $otherChildId = $ids['contact'] = $pContactIds[$pID];
            $parentRelationshipParams['contact_id_a'] = $otherChildId;
            $otherParentRelationshipParams['contact_id_a'] = $otherChildId;
            $emergency1ChildRelationshipParams['contact_id_a'] = $otherChildId;
            $emergency2ChildRelationshipParams['contact_id_a'] = $otherChildId;
            $householdMemberRelationshipParams['contact_id_a'] = $otherChildId;
            $parentRelationshipResult      = civicrm_api( 'relationship', 'create', $parentRelationshipParams );
            $otherParentRelationshipResult = civicrm_api( 'relationship', 'create', $otherParentRelationshipParams );
            CRM_Contact_BAO_Relationship::create(&$emergency1ChildRelationshipParams, &$ids);
            CRM_Contact_BAO_Relationship::create(&$emergency2ChildRelationshipParams, &$ids);
            if ( $is_shareAdd == 1 ) {
              $householdMemberRelationshipResult = civicrm_api( 'relationship', 'create', $householdMemberRelationshipParams );
              $shareOtherParams['id'] = $otherChildId;
              $childAddress = civicrm_api( 'contact', 'create', $shareOtherParams );
            }
          }
        }
      }
    } 
  }
}
function EnhancedEventReg_civicrm_postProcess( $formName, &$form  ) { 
                
  if( $formName == 'CRM_Event_Form_ManageEvent_Registration' ) {                    
    $eventId = $form->_id;
    $isenhanced = $form->_submitValues['is_enhanced'];
    if( !$isenhanced ) { 
      $isenhanced = 0; 
    }
    if( $isenhanced ) {
      $isEnhanced = CRM_Core_DAO::singleValueQuery( "SELECT id FROM civicrm_event_enhanced WHERE event_id = $eventId" );
      if (!empty($isEnhanced) ) {
        
      } else {
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced( id, event_id, is_enhanced ) values( null,'$eventId','$isenhanced' )" );
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced_profile(event_id, uf_group_id, contact_position, shares_address ) values({$eventId}, 4, null,0)" );
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced_profile(event_id, uf_group_id, contact_position, shares_address ) values({$eventId}, 11, null,0)" );
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced_profile(event_id, uf_group_id, contact_position, shares_address ) values({$eventId}, 12, null,0)" );
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced_profile(event_id, uf_group_id, contact_position, shares_address ) values({$eventId}, 13, null,0)" );
        CRM_Core_DAO::executeQuery( "INSERT INTO civicrm_event_enhanced_profile(event_id, uf_group_id, contact_position, shares_address ) values({$eventId}, 14, null,0)" );
      }
    }
  }
}



  

