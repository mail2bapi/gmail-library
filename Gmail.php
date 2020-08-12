<?php
/**
 * Author: Bapi Roy <mail2bapi@astrosoft.co.in>
 * Date: 30/07/19
 * Time: 2:19 AM
 **/
namespace ReferralSource;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_ModifyMessageRequest;

class Gmail
{

    /**
     * @var Google_Client
     */
    private $client;
    /**
     * @var Google_Service_Gmail
     */
    private $service;
    /**
     * @var string
     */
    private $user = 'me';

    // Email address
    /**
     * @var string
     */
    private $recepient = '';

    // Email Body
    /**
     * @var string
     */
    private $messageBody = '';
    
    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setScopes([
            'https://www.googleapis.com/auth/gmail.modify'
            ]);
        $this->client->setApplicationName('LAMP-PLG');
        $this->client->setSubject(getenv('USER_TO_IMPERSONATE'));
        $this->client->setAuthConfig(getenv('GOOGLE_AUTH_CONFIG'));
        $this->client->setAccessType('offline');

        $this->service = new Google_Service_Gmail($this->client);
    }

    /**
     * List all unread emails present in the inbox
     * @param string $email
     * @return array
     */
    public function listEmails($email='')
    {
        $emails = [];
        $pageToken = null;
        $messages = [];
        $success = true;

        try {
            $response = $this->service->users_messages->listUsersMessages(
                $this->user,
                [
                    'q' => 'from:'.$email.', is:unread',
                    'maxResults' => 15,
                ]);
            if ($response->getMessages()) {
                $messages = array_merge($messages, $response->getMessages());
            }
        } catch (Exception $e) {
            //echo "< h2>you don’t have email access </h2 > ";
            //echo $e;
            $success = false;
        }

        if ($success) {
            foreach ($messages as $message) {
                $this->getMessage($message->getId());
                $emails[] = [
                    'body' => $this->getMessageBody(),
                    'recipient' => $this->getRecepient(),
                ];
            }
        }

        return $emails;
    }

    /**
     * Read an individual email message
     * @param string $messageId
     */
    private function getMessage($messageId='')
    {
        $this->recepient = '';
        $this->messageBody = '';

        try {
            $message = $this->service->users_messages->get(
                $this->user,
                $messageId,
                ['format' => 'full']
            );

            // Message Body
            $playload = $message->getPayload();

            $this->messageBody = $playload->getBody()->getData();

            if(empty($this->messageBody)){
                $parts = $playload->getParts();

                foreach ($parts as $key => $part) {
                    if ($key == 0) {
                        $part_subs = $part->getParts();
                        if (empty($part_subs)) {
                            $this->messageBody = $part->getBody()->getData();
                        } else {
                            foreach ($part_subs as $part_sub) {
                                $body = $part_sub->getBody();
                                $this->messageBody = $body->getData();
                            }
                        }
                    }
                }
            }
            $this->messageBody = $this->base64url_decode($this->messageBody);

            // Get Recipient
            $headers = $playload->getHeaders();
            foreach ($headers as $single) {
                if ($single->getName() == 'To') {
                    $this->recepient = str_replace('"', '', $single->getValue());
                }
            }

            // Mark it Read
            $this->markRead($messageId);

        } catch (Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    }

    /**
     * Mark email as read
     * @param string $messageId
     */
    private function markRead($messageId='')
    {
        $mods = new Google_Service_Gmail_ModifyMessageRequest();
        $mods->setRemoveLabelIds(["UNREAD"]);
        $this->service->users_messages->modify(
            $this->user,
            $messageId,
            $mods
        );
    }

    /**
     * @param $data
     * @return false|string
     */
    private function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * @return string
     */
    public function getRecepient(): string
    {
        return $this->recepient;
    }

    /**
     * @return string
     */
    public function getMessageBody(): string
    {
        return $this->messageBody;
    }


}