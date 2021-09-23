<?php
require_once "edu-sharing-helper-abstract.php";

class EduSharingAuthHelper extends EduSharingHelperAbstract  {

    /**
     * Gets detailed information about a ticket
     * Will throw an exception if the given ticket is not valid anymore
     * @param string $ticket
     * The ticket, obtained by @getTicketForUser
     * @return array
     * Detailed information about the current session
     * @throws Exception
     * Thrown if the ticket is not valid anymore
     */
    public function getTicketAuthenticationInfo(string $ticket) {
        $curl = curl_init($this->base->baseUrl . '/rest/authentication/v1/validateSession');
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                $this->getRESTAuthenticationHeader($ticket),
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => 1
        ]);
        $data = json_decode(curl_exec($curl), true);
        curl_close($curl);
        if($data['statusCode'] !== 'OK') {
            throw new Exception('The given ticket is not valid anymore');
        }
        return $data;
    }

    /**
     * Fetches the edu-sharing ticket for a given username
     * @param string $username
     * The username you want to generate a ticket for
     * @return string
     * The ticket, which you can use as an authentication header, see @getRESTAuthenticationHeader
     * @throws Exception
     */
    public function getTicketForUser(string $username) {
        $curl = curl_init($this->base->baseUrl . '/rest/authentication/v1/appauth/' . rawurlencode($username));
        curl_setopt_array($curl, [
            CURLOPT_POST => 1,
            CURLOPT_FAILONERROR => false,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $this->getSignatureHeaders($username)
        ]);
        $data = json_decode(curl_exec($curl), true);
        $err     = curl_errno( $curl );
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($err === 0 && $info["http_code"] === 200 && $data['userId'] === $username) {
            return $data['ticket'];
        } else {
            throw new Exception('edu-sharing ticket could not be retrieved: HTTP-Code ' .
                $info["http_code"] . ': ' . $data['error']);
        }
    }
}