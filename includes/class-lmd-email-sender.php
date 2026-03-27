<?php
/**
 * Envoi d'emails
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Email_Sender {

    public function send($to, $subject, $body) {
        return wp_mail($to, $subject, $body);
    }
}
