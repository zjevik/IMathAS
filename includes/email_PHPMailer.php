<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'Exception.php';
require 'PHPMailer.php';
require 'SMTP.php';

function send_email_PHPMailer($email, $from, $subject, $message, $replyto=array(), $bccList=array()) {
    global $CFG;
    $mail = new PHPMailer(true);

    try {
        //Server settings
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $CFG['email']['Host'];                    // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $CFG['email']['username'];                    // SMTP username
        $mail->Password   = $CFG['email']['password'];                               // SMTP password
        $mail->AuthType = 'LOGIN';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 465;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        //Recipients
        $mail->setFrom($from, $CFG['email']['from']);

        if (!is_array($email)) {
            $email = array($email);
        }
        if (!is_array($replyto)) {
            if ($replyto=='') {
                $replyto = array();
            } else {
                $replyto = array($replyto);
            }
        }
        foreach ($email as $k=>$v) {
            $email[$k] = Sanitize::fullEmailAddress(trim($v));
            if ($email[$k] != '' && $email[$k] != 'none@none.com') {
                $mail->addAddress(preg_replace('/(.*)<(.*)>(.*)/sm', '\2', $email[$k]), preg_replace('/(.*)\"(.*)\"(.*)/sm', '\2', $email[$k]));
            }
        }
        if (count($email)==0) { //if no valid To addresses, bail
            return;
        }
        foreach ($replyto as $k=>$v) {
            $replyto[$k] = Sanitize::fullEmailAddress(trim($v));
            if ($replyto[$k] != '' && $replyto[$k] != 'none@none.com') {
                $mail->addReplyTo(preg_replace('/(.*)<(.*)>(.*)/sm', '\2', $replyto[$k]), preg_replace('/(.*)\"(.*)\"(.*)/sm', '\2', $replyto[$k]));
            }
        }
        foreach ($bccList as $k=>$v) {
            $bccList[$k] = Sanitize::fullEmailAddress(trim($v));
            if ($bccList[$k] != '' && $bccList[$k] != 'none@none.com') {
                $mail->addBCC($bccList[$k]);
            }
        }
        $subject = Sanitize::simpleASCII($subject);

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = $message;

        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Please contact the administrator and forward this message:<br>Mailer Error: {$mail->ErrorInfo}";
    }
}