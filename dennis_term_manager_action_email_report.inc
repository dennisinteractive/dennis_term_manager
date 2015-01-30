<?php

/**
 * @file
 */

/**
 * Helper to send error report via email.
 * 
 * @param $data
 *   
 */
function _dennis_term_manager_process_action_email_report($data) {
  // Send Email.
  print_r('email_sent');
  $subject = 'Report';
  $body = '';
  $from = 'noreply@dennis.co.uk';
  $to = 'tariq_ghori@dennis.co.uk';
  
  $headers = array(
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/html; charset=UTF-8; format=flowed',
    'Content-Transfer-Encoding' => '8Bit',
    'X-Mailer' => 'Drupal',
    'Return-Path' => 'noreply@dennis.co.uk'
  );
  
  $attachments = $data['report_filename'];
  
  // Create an e-mail.
  $message = array(
    'id' => 'report',
    'headers' => $headers,
    'subject' => $subject,
    'from' => $from,
    'to' => $to,
    'body' => $body,
    'params' => array(
      'attachments' => $attachments
    )
  );
  
  // Send the e-mail.
  try {
    if (! $response_ok = drupal_mail_system('smtp', $key)->mail($message)) {}
  } catch (exeption $e) {
    drupal_set_message($e->errorMessage());
  }
}