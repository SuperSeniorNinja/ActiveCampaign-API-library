<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once 'application/third_party/activecampaign-api-php/includes/ActiveCampaign.class.php';

// API v1 docs: https://www.activecampaign.com/api/overview.php

class ActiveCampaignAPI {

  protected $CI;

  public function __construct() {
    $this->CI = &get_instance();
    $this->active_campaign_url = $this->CI->config->item('active_campaign_url');
    $this->active_campaign_api_key = $this->CI->config->item('active_campaign_api_key');

    $this->activeCampaign = new ActiveCampaign($this->active_campaign_url, $this->active_campaign_api_key);

    if ( !(int)$this->activeCampaign->credentials_test() ) {
      error_log("ActiveCampaign access denied: Invalid credentials (URL and/or API key).");
      //exit();
    }
    else {
      // error_log('ActiveCampaign credentials valid. Proceeding...');
    }
  }

  public function addContact(Users $user, $listId = 0) {
    $defaultList = $this->CI->config->item('active_campaign_default_list_id');
    if (!$listId) $listId = $defaultList;

    $contact = array(
      "email" => $user->email,
      "first_name" => $user->first_name,
      "last_name" => $user->last_name,
      "p[{$listId}]" => $listId,
      "status[{$listId}]" => 1 // "Active" status
    );

    $response = $this->activeCampaign->api("contact/sync", $contact);

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact synced successfully: {$response->subscriber_id}");
      return (int)$response->subscriber_id;
    }
    else {
      // request failed
      error_log("ActiveCampaign syncing contact failed. Error returned: " . $response->error);
      return FALSE;
    }
  }

  public function deleteContact(Users $user) {
    $response = $this->activeCampaign->api("contact/delete?id={$user->active_campaign_contact_id}");

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact successfully deleted. Contact ID: {$active_campaign_contact_id}");
      return TRUE;
    }
    else {
      // request failed
      error_log("ActiveCampaign contact deletion failed. Error returned: " . $response->error);
      return FALSE;
    }
  }

  public function deleteContactById($active_campaign_contact_id) {
    $response = $this->activeCampaign->api("contact/delete?id={$active_campaign_contact_id}");

    return $response;

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact successfully deleted. Contact ID: {$active_campaign_contact_id}");
      return TRUE;
    }
    else {
      // request failed
      error_log("ActiveCampaign contact deletion failed. Error returned: " . $response->error);
      return FALSE;
    }
  }

  public function editContactEmailAddress(Users $user, $newEmail, $listId = 0) {
    $defaultList = $this->CI->config->item('active_campaign_default_list_id');
    if (!$listId) $listId = $defaultList;

    // Check to see if a contact already exists for this email address:
    $existingContact = $this->getContactByEmail($newEmail);

    // If the new email address belongs to an existing Active Campaign contact, then
    // delete the contact with the new email address and update the email address on
    // the contact with the old email address.
    if ( $existingContact ) {
      $existingContactId = $existingContact->id;
      $this->deleteContactById($existingContactId);
      // error_log("ActiveCampaign contact email address successfully via existing contact ({$user->email} -> {$newEmail}). Contact ID: {$existingContactId}");
    }

    // Edit the email address for the contact
    $contact = array(
      "id" => $user->active_campaign_contact_id,
      "email" => $newEmail,
      "first_name" => $user->first_name,
      "last_name" => $user->last_name,
      "p[{$listId}]" => $listId,
      "status[{$listId}]" => 1 // "Active" status
    );

    $response = $this->activeCampaign->api("contact/edit", $contact);

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact email address successfully edited ({$user->email} -> {$newEmail}). Contact ID: {$response->subscriber_id}");
      return (int)$response->subscriber_id;
    }
    else {
      // request failed
      error_log("ActiveCampaign editing contact email address failed ({$user->email} -> {$newEmail}). Error returned: " . $response->error);
      return FALSE;
    }
  }

  public function editContactFields(Users $user, $fields = []) {
    /*
    Example $fields array:

    NOTE: The "0" after the personalization tag is REQUIRED.

    $fields = array(
      '%FIELD_PERSONALIZATION_TAG%,0' => 'FIELD_VALUE',
      '%FIELD_PERSONALIZATION_TAG%,0' => 'FIELD_VALUE'
    );
    */

    if ($fields) {
      $contact = array(
        "email" => $user->email,
        "first_name" => $user->first_name,
        "last_name" => $user->last_name
      );
      $contact['field'] = $fields;

      $response = $this->activeCampaign->api("contact/sync", $contact);

      if ( (int)$response->success ) {
        // error_log("ActiveCampaign contact fields synced successfully. Contact ID: {$response->subscriber_id}");
        return (int)$response->subscriber_id;
      }
      else {
        // request failed
        error_log("ActiveCampaign syncing contact fields failed. Error returned: " . $response->error);
        return FALSE;
      }
    }
    else {
      error_log('ActiveCampaign syncing contact fields failed. Missing/empty fields array.');
      return FALSE;
    }

  }

  public function getContact(Users $user) {
    $response = $this->activeCampaign->api("contact/view?email={$user->email}");

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact retrieved successfully. Contact ID: " . $response->id);
      return $response;
    }
    else {
      error_log("ActiveCampaign contact retrieval by user ({$user->id}) failed. " . $response->error);
      return FALSE;
    }
  }

  public function getContactByEmail($email) {
    $response = $this->activeCampaign->api("contact/view?email={$email}");

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact retrieved successfully. Contact ID: " . $response->id);
      return $response;
    }
    else {
      error_log("ActiveCampaign contact retrieval by email address ({$email}) failed. " . $response->error);
      return FALSE;
    }
  }

  public function getContactById($active_campaign_contact_id) {
    $response = $this->activeCampaign->api("contact/view?id={$active_campaign_contact_id}");

    if ( (int)$response->success ) {
      // error_log("ActiveCampaign contact retrieved successfully. Contact ID: " . $response->id);
      return $response;
    }
    else {
      error_log("ActiveCampaign contact retrieval by contact ID ({$active_campaign_contact_id}) failed. " . $response->error);
      return FALSE;
    }
  }

  public function trackEvent(Users $user, $event_name = '') {
    $this->activeCampaign->track_actid = $this->CI->config->item('active_campaign_account_id');
    $this->activeCampaign->track_key = $this->CI->config->item('active_campaign_tracking_event_key');
    $this->activeCampaign->track_email = $user->email;

    if ($event_name) {
      $data = array("event" => $event_name);
      $response = $this->activeCampaign->api("tracking/log", $data);

      if ( (int)$response->success ) {
        // error_log("ActiveCampaign event ({$event_name}) tracked successfully: " . $response->message);
        $returnVal = TRUE;
      }
      else {
        error_log("ActiveCampaign event tracking failed. Error returned: " . $response->message);
        $returnVal = FALSE;
      }

      // Add log entry to email_log
      $emailData = [
          'subject' => $event_name,
          'recipient_email' => $user->email,
          'sent_via' => 'active_campaign',
          'response_message' => $response->message ? $response->message : '',
          'created_at' => date("Y-m-d H:i:s")
      ];
      $this->CI->db->insert('email_log', $emailData);

      return $returnVal;
    }
    else {
      error_log('ActiveCampaign event tracking failed. Missing event name.');
      return FALSE;
    }
  }

}
